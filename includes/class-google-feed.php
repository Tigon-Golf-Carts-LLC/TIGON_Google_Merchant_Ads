<?php
/**
 * Google Merchant Center feed generator.
 *
 * Produces an XML feed fully compliant with the Google Product Data Specification.
 * All products default to Google category 3101 (Vehicles & Parts > Vehicles > Golf Carts).
 *
 * Google Merchant Required Fields:
 *   g:id, g:title, g:description, g:link, g:image_link,
 *   g:availability, g:price, g:brand, g:condition,
 *   g:gtin or g:mpn (or identifier_exists=false)
 *
 * Google Merchant Recommended Fields:
 *   g:additional_image_link, g:sale_price, g:sale_price_effective_date,
 *   g:google_product_category, g:product_type, g:item_group_id,
 *   g:color, g:size, g:material, g:pattern,
 *   g:shipping_weight, g:shipping_length, g:shipping_width, g:shipping_height,
 *   g:shipping, g:tax, g:canonical_link,
 *   g:product_highlight, g:product_detail,
 *   g:custom_label_0 through g:custom_label_4,
 *   g:ads_redirect, g:availability_date, g:energy_efficiency_class
 *
 * @see https://support.google.com/merchants/answer/7052112
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Google_Feed extends TMF_Feed_Generator {

	protected $slug  = 'google';
	protected $label = 'Google Merchant';

	/**
	 * Generate the Google Merchant XML feed.
	 *
	 * @return string XML content.
	 */
	public function generate() {
		$products   = $this->get_products();
		$shop_name  = get_bloginfo( 'name' );
		$shop_url   = home_url( '/' );
		$shop_desc  = get_bloginfo( 'description' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
		$xml .= "<channel>\n";
		$xml .= '  <title>' . $this->xml_escape( $shop_name ) . "</title>\n";
		$xml .= '  <link>' . esc_url( $shop_url ) . "</link>\n";
		$xml .= '  <description>' . $this->xml_escape( $shop_desc ) . "</description>\n";

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );

			// Skip products with missing REQUIRED fields.
			if ( empty( $data['title'] ) || empty( $data['link'] ) || empty( $data['price'] ) ) {
				continue;
			}

			$xml .= "<item>\n";

			// =================================================================
			//  REQUIRED FIELDS
			// =================================================================

			// g:id — unique product identifier
			$xml .= '  <g:id>' . $this->xml_escape( $data['id'] ) . "</g:id>\n";

			// g:title — max 150 characters
			$xml .= '  <g:title>' . $this->xml_escape( $this->truncate( $data['title'], 150 ) ) . "</g:title>\n";

			// g:description — max 5000 characters, must not be empty
			$xml .= '  <g:description>' . $this->xml_escape( $this->truncate( $data['description'], 5000 ) ) . "</g:description>\n";

			// g:link — product page URL
			$xml .= '  <g:link>' . esc_url( $data['link'] ) . "</g:link>\n";

			// g:image_link — main product image (required)
			if ( ! empty( $data['image_link'] ) ) {
				$xml .= '  <g:image_link>' . esc_url( $data['image_link'] ) . "</g:image_link>\n";
			}

			// g:availability — in_stock | out_of_stock | preorder | backorder
			$xml .= '  <g:availability>' . esc_xml( $data['availability'] ) . "</g:availability>\n";

			// g:price — regular price with currency code (e.g. "12999.00 USD")
			$xml .= '  <g:price>' . esc_xml( $this->format_price( $data['regular_price'] ?: $data['price'], $data['currency'] ) ) . "</g:price>\n";

			// g:brand — required for all products
			$xml .= '  <g:brand>' . $this->xml_escape( $data['brand'] ) . "</g:brand>\n";

			// g:condition — new | refurbished | used
			$xml .= '  <g:condition>' . esc_xml( $data['condition'] ) . "</g:condition>\n";

			// g:gtin / g:mpn / g:identifier_exists
			if ( ! empty( $data['gtin'] ) ) {
				$xml .= '  <g:gtin>' . $this->xml_escape( $data['gtin'] ) . "</g:gtin>\n";
			}
			if ( ! empty( $data['mpn'] ) ) {
				$xml .= '  <g:mpn>' . $this->xml_escape( $data['mpn'] ) . "</g:mpn>\n";
			}
			if ( empty( $data['gtin'] ) && empty( $data['mpn'] ) ) {
				$xml .= "  <g:identifier_exists>false</g:identifier_exists>\n";
			}

			// =================================================================
			//  RECOMMENDED FIELDS
			// =================================================================

			// g:sale_price — only if on sale and less than regular price
			if ( ! empty( $data['sale_price'] ) && (float) $data['sale_price'] < (float) ( $data['regular_price'] ?: $data['price'] ) ) {
				$xml .= '  <g:sale_price>' . esc_xml( $this->format_price( $data['sale_price'], $data['currency'] ) ) . "</g:sale_price>\n";

				// g:sale_price_effective_date — ISO 8601 date range
				if ( ! empty( $data['sale_price_effective_date'] ) ) {
					$xml .= '  <g:sale_price_effective_date>' . esc_xml( $data['sale_price_effective_date'] ) . "</g:sale_price_effective_date>\n";
				}
			}

			// g:additional_image_link — up to 10 additional images
			foreach ( array_slice( $data['additional_image_links'], 0, 10 ) as $img ) {
				$xml .= '  <g:additional_image_link>' . esc_url( $img ) . "</g:additional_image_link>\n";
			}

			// g:google_product_category — Google taxonomy ID (3101 = Golf Carts)
			$xml .= '  <g:google_product_category>' . esc_xml( $data['google_product_category'] ) . "</g:google_product_category>\n";

			// g:product_type — your own category hierarchy
			if ( ! empty( $data['category_path'] ) ) {
				$xml .= '  <g:product_type>' . $this->xml_escape( $data['category_path'] ) . "</g:product_type>\n";
			}

			// g:canonical_link — canonical URL to prevent duplicate rejection
			if ( ! empty( $data['canonical_link'] ) && $data['canonical_link'] !== $data['link'] ) {
				$xml .= '  <g:canonical_link>' . esc_url( $data['canonical_link'] ) . "</g:canonical_link>\n";
			}

			// g:item_group_id — groups product variations together
			if ( ! empty( $data['item_group_id'] ) ) {
				$xml .= '  <g:item_group_id>' . esc_xml( $data['item_group_id'] ) . "</g:item_group_id>\n";
			}

			// g:color
			if ( ! empty( $data['color'] ) ) {
				$xml .= '  <g:color>' . $this->xml_escape( $data['color'] ) . "</g:color>\n";
			}

			// g:size
			if ( ! empty( $data['size'] ) ) {
				$xml .= '  <g:size>' . $this->xml_escape( $data['size'] ) . "</g:size>\n";
			}

			// g:material
			if ( ! empty( $data['material'] ) ) {
				$xml .= '  <g:material>' . $this->xml_escape( $data['material'] ) . "</g:material>\n";
			}

			// g:pattern
			if ( ! empty( $data['pattern'] ) ) {
				$xml .= '  <g:pattern>' . $this->xml_escape( $data['pattern'] ) . "</g:pattern>\n";
			}

			// g:shipping_weight
			if ( ! empty( $data['weight'] ) ) {
				$xml .= '  <g:shipping_weight>' . esc_xml( $data['weight'] . ' ' . $data['weight_unit'] ) . "</g:shipping_weight>\n";
			}

			// g:shipping_length, g:shipping_width, g:shipping_height
			if ( ! empty( $data['length'] ) ) {
				$xml .= '  <g:shipping_length>' . esc_xml( $data['length'] . ' ' . $data['dimension_unit'] ) . "</g:shipping_length>\n";
			}
			if ( ! empty( $data['width'] ) ) {
				$xml .= '  <g:shipping_width>' . esc_xml( $data['width'] . ' ' . $data['dimension_unit'] ) . "</g:shipping_width>\n";
			}
			if ( ! empty( $data['height'] ) ) {
				$xml .= '  <g:shipping_height>' . esc_xml( $data['height'] . ' ' . $data['dimension_unit'] ) . "</g:shipping_height>\n";
			}

			// g:shipping — explicit shipping info block
			$shipping_country = get_option( 'woocommerce_default_country', 'US' );
			if ( strpos( $shipping_country, ':' ) !== false ) {
				$shipping_country = explode( ':', $shipping_country )[0];
			}
			$shipping_cost    = get_option( 'tmf_default_shipping_cost', '' );
			$shipping_service = get_option( 'tmf_default_shipping_service', 'Standard' );
			if ( ! empty( $shipping_cost ) ) {
				$xml .= "  <g:shipping>\n";
				$xml .= '    <g:country>' . esc_xml( $shipping_country ) . "</g:country>\n";
				$xml .= '    <g:service>' . $this->xml_escape( $shipping_service ) . "</g:service>\n";
				$xml .= '    <g:price>' . esc_xml( $this->format_price( $shipping_cost, $data['currency'] ) ) . "</g:price>\n";
				$xml .= "  </g:shipping>\n";
			}

			// g:shipping_label
			if ( ! empty( $data['shipping_label'] ) ) {
				$xml .= '  <g:shipping_label>' . $this->xml_escape( $data['shipping_label'] ) . "</g:shipping_label>\n";
			}

			// g:tax — tax status for US
			if ( 'none' === $data['tax_status'] ) {
				// Product is tax exempt.
				$xml .= "  <g:tax>\n";
				$xml .= "    <g:country>US</g:country>\n";
				$xml .= "    <g:rate>0</g:rate>\n";
				$xml .= "    <g:tax_ship>no</g:tax_ship>\n";
				$xml .= "  </g:tax>\n";
			}
			if ( ! empty( $data['tax_class'] ) && 'standard' !== $data['tax_class'] ) {
				$xml .= '  <g:tax_category>' . $this->xml_escape( $data['tax_class'] ) . "</g:tax_category>\n";
			}

			// g:availability_date — for backorder/preorder items
			if ( ! empty( $data['availability_date'] ) && in_array( $data['availability'], array( 'backorder', 'preorder' ), true ) ) {
				$xml .= '  <g:availability_date>' . esc_xml( $data['availability_date'] ) . "</g:availability_date>\n";
			}

			// g:product_highlight — up to 10 bullet-point highlights
			if ( ! empty( $data['product_highlight'] ) ) {
				foreach ( array_slice( $data['product_highlight'], 0, 10 ) as $highlight ) {
					$xml .= '  <g:product_highlight>' . $this->xml_escape( $this->truncate( $highlight, 150 ) ) . "</g:product_highlight>\n";
				}
			}

			// g:product_detail — additional structured specifications
			if ( ! empty( $data['product_detail'] ) ) {
				foreach ( array_slice( $data['product_detail'], 0, 100 ) as $detail ) {
					$xml .= "  <g:product_detail>\n";
					$xml .= '    <g:section_name>' . $this->xml_escape( $detail['section_name'] ) . "</g:section_name>\n";
					$xml .= '    <g:attribute_name>' . $this->xml_escape( $detail['attribute_name'] ) . "</g:attribute_name>\n";
					$xml .= '    <g:attribute_value>' . $this->xml_escape( $detail['attribute_value'] ) . "</g:attribute_value>\n";
					$xml .= "  </g:product_detail>\n";
				}
			}

			// g:custom_label_0 through g:custom_label_4 — Google Ads segmentation
			for ( $i = 0; $i <= 4; $i++ ) {
				$key = 'custom_label_' . $i;
				if ( ! empty( $data[ $key ] ) ) {
					$xml .= '  <g:' . $key . '>' . $this->xml_escape( $data[ $key ] ) . '</g:' . $key . ">\n";
				}
			}

			// g:ads_redirect — tracking redirect URL for Google Ads
			if ( ! empty( $data['ads_redirect'] ) ) {
				$xml .= '  <g:ads_redirect>' . esc_url( $data['ads_redirect'] ) . "</g:ads_redirect>\n";
			}

			// g:energy_efficiency_class — for electric golf carts
			if ( ! empty( $data['energy_efficiency_class'] ) ) {
				$xml .= '  <g:energy_efficiency_class>' . esc_xml( $data['energy_efficiency_class'] ) . "</g:energy_efficiency_class>\n";
			}

			// Extensibility — allow additional XML via filter.
			$xml .= apply_filters( 'tmf_google_item_xml', '', $data, $product );

			$xml .= "</item>\n";
		}

		$xml .= "</channel>\n</rss>\n";

		return apply_filters( 'tmf_google_feed_xml', $xml );
	}

	/**
	 * Truncate a string to a max length.
	 */
	private function truncate( $str, $max ) {
		if ( mb_strlen( $str ) <= $max ) {
			return $str;
		}
		return mb_substr( $str, 0, $max - 3 ) . '...';
	}
}
