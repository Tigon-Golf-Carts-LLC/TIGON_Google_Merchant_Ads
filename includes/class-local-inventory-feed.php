<?php
/**
 * Local Inventory Feed generator.
 *
 * Produces a supplemental XML feed describing per-store inventory for
 * Google Free Local Listings and Local Inventory Ads. Each <item> represents
 * one product-at-one-store, keyed by [store_code] + [item_id].
 *
 * Google Local Inventory Required Fields:
 *   g:store_code, g:id (item_id), g:quantity and/or g:availability, g:price
 *
 * Recommended:
 *   g:sale_price, g:sale_price_effective_date,
 *   g:pickup_method, g:pickup_sla, g:link_template, g:mobile_link_template
 *
 * @see https://support.google.com/merchants/answer/3061342
 * @see https://support.google.com/merchants/answer/7174375
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Local_Inventory_Feed extends TMF_Feed_Generator {

	protected $slug  = 'google-local-inventory';
	protected $label = 'Google Local Inventory';

	/**
	 * Generate the Local Inventory XML feed.
	 *
	 * One row is emitted per (product, store) pair. Products that either
	 * carry an _tmf_store assignment or appear in per-store stock overrides
	 * are included; products not stocked in any physical store are skipped.
	 *
	 * @return string XML content.
	 */
	public function generate() {
		$products  = $this->get_products();
		$shop_name = get_bloginfo( 'name' );
		$shop_url  = home_url( '/' );
		$stores    = get_option( 'tmf_stores', array() );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
		$xml .= "<channel>\n";
		$xml .= '  <title>' . $this->xml_escape( $shop_name . ' — Local Inventory' ) . "</title>\n";
		$xml .= '  <link>' . esc_url( $shop_url ) . "</link>\n";
		$xml .= "  <description>Local inventory data for physical stores.</description>\n";

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );

			if ( empty( $data['id'] ) ) {
				continue;
			}

			// Skip if merchant explicitly excluded this product from local destinations.
			$excluded = isset( $data['excluded_destinations'] ) ? (array) $data['excluded_destinations'] : array();
			$excluded = array_map( 'strtolower', $excluded );
			if ( in_array( 'local_inventory_ads', $excluded, true )
				&& in_array( 'free_local_listings', $excluded, true ) ) {
				continue;
			}

			$rows = $this->build_store_rows( $product, $data, $stores );
			foreach ( $rows as $row ) {
				$xml .= $this->render_item( $row, $data );
			}
		}

		$xml .= "</channel>\n</rss>\n";

		return apply_filters( 'tmf_local_inventory_feed_xml', $xml );
	}

	/**
	 * Build one inventory row per store for a given product.
	 *
	 * Priority order when picking which stores carry a product:
	 *   1. Per-product override meta `_tmf_store_inventory`
	 *      (serialized array of [store_code => [quantity, availability, price, sale_price]]).
	 *   2. Single `_tmf_store` assignment — inherits product-level stock/availability.
	 *   3. If the product carries no store assignment but the site has exactly
	 *      one store configured, fall back to that store.
	 *
	 * @param WC_Product $product Product.
	 * @param array      $data    Mapped product data.
	 * @param array      $stores  Configured stores (store_code => name).
	 * @return array    List of inventory rows to emit.
	 */
	protected function build_store_rows( WC_Product $product, array $data, array $stores ) {
		$rows = array();

		$inventory_map = $product->get_meta( '_tmf_store_inventory', true );
		if ( ! is_array( $inventory_map ) ) {
			$inventory_map = array();
		}

		if ( ! empty( $inventory_map ) ) {
			foreach ( $inventory_map as $store_code => $info ) {
				$store_code = sanitize_key( $store_code );
				if ( empty( $store_code ) ) {
					continue;
				}
				$info = is_array( $info ) ? $info : array();
				$rows[] = array(
					'store_code'   => $store_code,
					'item_id'      => $data['id'],
					'quantity'     => isset( $info['quantity'] ) ? (int) $info['quantity'] : $data['stock_quantity'],
					'availability' => ! empty( $info['availability'] ) ? self::normalize_availability( $info['availability'] ) : $data['availability'],
					'price'        => isset( $info['price'] ) && '' !== $info['price'] ? $info['price'] : ( $data['regular_price'] ?: $data['price'] ),
					'sale_price'   => $info['sale_price'] ?? $data['sale_price'],
					'currency'     => $data['currency'],
					'pickup_method'=> $info['pickup_method'] ?? $product->get_meta( '_tmf_pickup_method', true ),
					'pickup_sla'   => $info['pickup_sla']    ?? $product->get_meta( '_tmf_pickup_sla', true ),
				);
			}
			return $rows;
		}

		$assigned = $data['store_location'];
		if ( empty( $assigned ) && 1 === count( $stores ) ) {
			$assigned = (string) array_key_first( $stores );
		}

		if ( ! empty( $assigned ) ) {
			$rows[] = array(
				'store_code'   => sanitize_key( $assigned ),
				'item_id'      => $data['id'],
				'quantity'     => $data['stock_quantity'],
				'availability' => $data['availability'],
				'price'        => $data['regular_price'] ?: $data['price'],
				'sale_price'   => $data['sale_price'],
				'currency'     => $data['currency'],
				'pickup_method'=> $product->get_meta( '_tmf_pickup_method', true ),
				'pickup_sla'   => $product->get_meta( '_tmf_pickup_sla', true ),
			);
		}

		return $rows;
	}

	/**
	 * Render a single <item> for a store/product row.
	 */
	protected function render_item( array $row, array $data ) {
		if ( empty( $row['store_code'] ) || empty( $row['item_id'] ) ) {
			return '';
		}

		$xml  = "<item>\n";
		$xml .= '  <g:store_code>' . $this->xml_escape( $row['store_code'] ) . "</g:store_code>\n";
		$xml .= '  <g:id>' . $this->xml_escape( $row['item_id'] ) . "</g:id>\n";

		// g:quantity — integer inventory count (0 is allowed and means out of stock).
		if ( null !== $row['quantity'] && '' !== $row['quantity'] ) {
			$xml .= '  <g:quantity>' . (int) $row['quantity'] . "</g:quantity>\n";
		}

		// g:availability — in stock | out of stock | limited availability | on display to order
		if ( ! empty( $row['availability'] ) ) {
			$xml .= '  <g:availability>' . esc_xml( $row['availability'] ) . "</g:availability>\n";
		}

		// g:price — include when set, Google uses it to override online price at this store.
		if ( ! empty( $row['price'] ) ) {
			$xml .= '  <g:price>' . esc_xml( $this->format_price( $row['price'], $row['currency'] ) ) . "</g:price>\n";
		}

		if ( ! empty( $row['sale_price'] ) && (float) $row['sale_price'] < (float) $row['price'] ) {
			$xml .= '  <g:sale_price>' . esc_xml( $this->format_price( $row['sale_price'], $row['currency'] ) ) . "</g:sale_price>\n";
			if ( ! empty( $data['sale_price_effective_date'] ) ) {
				$xml .= '  <g:sale_price_effective_date>' . esc_xml( $data['sale_price_effective_date'] ) . "</g:sale_price_effective_date>\n";
			}
		}

		if ( ! empty( $row['pickup_method'] ) ) {
			$xml .= '  <g:pickup_method>' . esc_xml( $row['pickup_method'] ) . "</g:pickup_method>\n";
		}
		if ( ! empty( $row['pickup_sla'] ) ) {
			$xml .= '  <g:pickup_sla>' . esc_xml( $row['pickup_sla'] ) . "</g:pickup_sla>\n";
		}

		// g:link_template — store-aware product URL. Required for LIA to serve.
		$link_template = get_option( 'tmf_link_template', '' );
		if ( ! empty( $link_template ) ) {
			$xml .= '  <g:link_template>' . esc_url( self::expand_template( $link_template, $row, $data ) ) . "</g:link_template>\n";
		}
		$mobile_link_template = get_option( 'tmf_mobile_link_template', '' );
		if ( ! empty( $mobile_link_template ) ) {
			$xml .= '  <g:mobile_link_template>' . esc_url( self::expand_template( $mobile_link_template, $row, $data ) ) . "</g:mobile_link_template>\n";
		}

		$xml .= "</item>\n";
		return $xml;
	}

	/**
	 * Expand {store_code} / {item_id} placeholders in a link template.
	 *
	 * Google requires the template to literally contain {store_code} when it is
	 * submitted to Merchant Center, but for preview purposes we also support
	 * local expansion so admins can sanity-check the URL.
	 */
	public static function expand_template( $template, array $row, array $data ) {
		return str_replace(
			array( '{store_code}', '{item_id}', '{id}' ),
			array( rawurlencode( $row['store_code'] ), rawurlencode( $row['item_id'] ), rawurlencode( $row['item_id'] ) ),
			$template
		);
	}

	/**
	 * Normalize free-form availability strings to Google's accepted values.
	 */
	public static function normalize_availability( $value ) {
		$value = strtolower( trim( (string) $value ) );
		$map   = array(
			'in_stock'             => 'in stock',
			'instock'              => 'in stock',
			'in stock'             => 'in stock',
			'out_of_stock'         => 'out of stock',
			'outofstock'           => 'out of stock',
			'out of stock'         => 'out of stock',
			'limited'              => 'limited availability',
			'limited_availability' => 'limited availability',
			'limited availability' => 'limited availability',
			'on_display'           => 'on display to order',
			'on display to order'  => 'on display to order',
		);
		return $map[ $value ] ?? 'in stock';
	}
}
