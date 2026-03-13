<?php
/**
 * Google Merchant Center feed generator.
 *
 * Produces an XML feed compliant with the Google Product Data Specification.
 * All products default to Google category 3101 (Vehicles & Parts > Vehicles > Golf Carts).
 *
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
		$products     = $this->get_products();
		$shop_name    = get_bloginfo( 'name' );
		$shop_url     = home_url( '/' );
		$shop_desc    = get_bloginfo( 'description' );
		$google_cat   = get_option( 'tmf_google_category_name', 'Vehicles & Parts > Vehicles > Golf Carts' );
		$google_cat_id = get_option( 'tmf_google_category', '3101' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0">' . "\n";
		$xml .= "<channel>\n";
		$xml .= '<title>' . $this->xml_escape( $shop_name ) . "</title>\n";
		$xml .= '<link>' . esc_url( $shop_url ) . "</link>\n";
		$xml .= '<description>' . $this->xml_escape( $shop_desc ) . "</description>\n";

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );

			// Skip products with missing required fields.
			if ( empty( $data['title'] ) || empty( $data['link'] ) || empty( $data['price'] ) ) {
				continue;
			}

			$xml .= "<item>\n";
			$xml .= '  <g:id>' . $this->xml_escape( $data['id'] ) . "</g:id>\n";
			$xml .= '  <g:title>' . $this->xml_escape( $this->truncate( $data['title'], 150 ) ) . "</g:title>\n";
			$xml .= '  <g:description>' . $this->xml_escape( $this->truncate( $data['description'], 5000 ) ) . "</g:description>\n";
			$xml .= '  <g:link>' . esc_url( $data['link'] ) . "</g:link>\n";

			if ( ! empty( $data['image_link'] ) ) {
				$xml .= '  <g:image_link>' . esc_url( $data['image_link'] ) . "</g:image_link>\n";
			}
			foreach ( array_slice( $data['additional_image_links'], 0, 10 ) as $img ) {
				$xml .= '  <g:additional_image_link>' . esc_url( $img ) . "</g:additional_image_link>\n";
			}

			$xml .= '  <g:availability>' . esc_xml( $data['availability'] ) . "</g:availability>\n";
			$xml .= '  <g:price>' . esc_xml( $this->format_price( $data['regular_price'] ?: $data['price'], $data['currency'] ) ) . "</g:price>\n";

			if ( ! empty( $data['sale_price'] ) && $data['sale_price'] < $data['regular_price'] ) {
				$xml .= '  <g:sale_price>' . esc_xml( $this->format_price( $data['sale_price'], $data['currency'] ) ) . "</g:sale_price>\n";
			}

			$xml .= '  <g:brand>' . $this->xml_escape( $data['brand'] ) . "</g:brand>\n";

			if ( ! empty( $data['gtin'] ) ) {
				$xml .= '  <g:gtin>' . $this->xml_escape( $data['gtin'] ) . "</g:gtin>\n";
			}
			if ( ! empty( $data['mpn'] ) ) {
				$xml .= '  <g:mpn>' . $this->xml_escape( $data['mpn'] ) . "</g:mpn>\n";
			}
			if ( empty( $data['gtin'] ) && empty( $data['mpn'] ) ) {
				$xml .= "  <g:identifier_exists>false</g:identifier_exists>\n";
			}

			$xml .= '  <g:condition>' . esc_xml( $data['condition'] ) . "</g:condition>\n";

			// Google Product Category — all products are Golf Carts.
			$xml .= '  <g:google_product_category>' . esc_xml( $google_cat_id ) . "</g:google_product_category>\n";

			if ( ! empty( $data['category_path'] ) ) {
				$xml .= '  <g:product_type>' . $this->xml_escape( $data['category_path'] ) . "</g:product_type>\n";
			}

			// Item group ID for variations.
			if ( ! empty( $data['parent_id'] ) ) {
				$xml .= '  <g:item_group_id>' . esc_xml( $data['parent_id'] ) . "</g:item_group_id>\n";
			}

			// Shipping weight.
			if ( ! empty( $data['weight'] ) ) {
				$xml .= '  <g:shipping_weight>' . esc_xml( $data['weight'] . ' ' . $data['weight_unit'] ) . "</g:shipping_weight>\n";
			}

			// Product dimensions.
			if ( ! empty( $data['length'] ) ) {
				$xml .= '  <g:shipping_length>' . esc_xml( $data['length'] . ' ' . $data['dimension_unit'] ) . "</g:shipping_length>\n";
			}
			if ( ! empty( $data['width'] ) ) {
				$xml .= '  <g:shipping_width>' . esc_xml( $data['width'] . ' ' . $data['dimension_unit'] ) . "</g:shipping_width>\n";
			}
			if ( ! empty( $data['height'] ) ) {
				$xml .= '  <g:shipping_height>' . esc_xml( $data['height'] . ' ' . $data['dimension_unit'] ) . "</g:shipping_height>\n";
			}

			// Variant attributes.
			foreach ( $data['attributes'] as $attr_label => $attr_value ) {
				$tag = sanitize_key( $attr_label );
				if ( in_array( strtolower( $attr_label ), array( 'color', 'colour' ), true ) ) {
					$xml .= '  <g:color>' . $this->xml_escape( $attr_value ) . "</g:color>\n";
				} elseif ( in_array( strtolower( $attr_label ), array( 'size' ), true ) ) {
					$xml .= '  <g:size>' . $this->xml_escape( $attr_value ) . "</g:size>\n";
				} elseif ( in_array( strtolower( $attr_label ), array( 'material' ), true ) ) {
					$xml .= '  <g:material>' . $this->xml_escape( $attr_value ) . "</g:material>\n";
				}
			}

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
