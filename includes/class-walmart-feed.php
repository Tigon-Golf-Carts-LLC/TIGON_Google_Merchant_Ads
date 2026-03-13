<?php
/**
 * Walmart Marketplace feed generator.
 *
 * Produces an XML feed compatible with Walmart Marketplace bulk upload specification.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Walmart_Feed extends TMF_Feed_Generator {

	protected $slug  = 'walmart';
	protected $label = 'Walmart';

	public function generate() {
		$products = $this->get_products();
		$currency = get_woocommerce_currency();

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<MPItemFeed xmlns="http://walmart.com/">' . "\n";
		$xml .= '<MPItemFeedHeader>' . "\n";
		$xml .= '  <version>4.2</version>' . "\n";
		$xml .= '  <feedDate>' . gmdate( 'Y-m-d\TH:i:s' ) . '</feedDate>' . "\n";
		$xml .= '</MPItemFeedHeader>' . "\n";

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );
			if ( empty( $data['title'] ) || empty( $data['price'] ) || empty( $data['sku'] ) ) {
				continue;
			}

			$xml .= "<MPItem>\n";
			$xml .= '  <sku>' . $this->xml_escape( $data['sku'] ) . "</sku>\n";
			$xml .= '  <productName>' . $this->xml_escape( substr( $data['title'], 0, 200 ) ) . "</productName>\n";
			$xml .= '  <longDescription>' . $this->xml_escape( substr( $data['description'], 0, 4000 ) ) . "</longDescription>\n";
			$xml .= '  <shortDescription>' . $this->xml_escape( substr( $data['short_description'], 0, 1000 ) ) . "</shortDescription>\n";
			$xml .= '  <mainImageUrl>' . esc_url( $data['image_link'] ) . "</mainImageUrl>\n";

			$i = 1;
			foreach ( array_slice( $data['additional_image_links'], 0, 8 ) as $img ) {
				$xml .= '  <productSecondaryImageUrl>' . esc_url( $img ) . "</productSecondaryImageUrl>\n";
				$i++;
			}

			$xml .= '  <price>' . "\n";
			$xml .= '    <currency>' . esc_xml( $currency ) . "</currency>\n";
			$xml .= '    <amount>' . esc_xml( number_format( (float) $data['price'], 2, '.', '' ) ) . "</amount>\n";
			$xml .= "  </price>\n";

			if ( ! empty( $data['sale_price'] ) && $data['sale_price'] < $data['regular_price'] ) {
				$xml .= "  <ComparisonPrice>\n";
				$xml .= '    <currency>' . esc_xml( $currency ) . "</currency>\n";
				$xml .= '    <amount>' . esc_xml( number_format( (float) $data['regular_price'], 2, '.', '' ) ) . "</amount>\n";
				$xml .= "  </ComparisonPrice>\n";
			}

			$xml .= '  <productIdentifiers>' . "\n";
			$xml .= '    <productIdType>' . ( ! empty( $data['gtin'] ) ? 'UPC' : 'SKU' ) . "</productIdType>\n";
			$xml .= '    <productId>' . $this->xml_escape( ! empty( $data['gtin'] ) ? $data['gtin'] : $data['sku'] ) . "</productId>\n";
			$xml .= "  </productIdentifiers>\n";

			$xml .= '  <brand>' . $this->xml_escape( $data['brand'] ) . "</brand>\n";
			$xml .= '  <condition>' . $this->xml_escape( ucfirst( $data['condition'] ) ) . "</condition>\n";
			$xml .= '  <category>Vehicles &amp; Vehicles Parts &gt; Golf Carts</category>' . "\n";
			$xml .= '  <productUrl>' . esc_url( $data['link'] ) . "</productUrl>\n";

			if ( ! empty( $data['weight'] ) ) {
				$xml .= '  <shippingWeight>' . esc_xml( $data['weight'] ) . "</shippingWeight>\n";
				$xml .= '  <shippingWeightUnit>' . esc_xml( strtoupper( $data['weight_unit'] ) ) . "</shippingWeightUnit>\n";
			}

			$xml .= apply_filters( 'tmf_walmart_item_xml', '', $data, $product );
			$xml .= "</MPItem>\n";
		}

		$xml .= "</MPItemFeed>\n";
		return apply_filters( 'tmf_walmart_feed_xml', $xml );
	}
}
