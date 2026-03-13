<?php
/**
 * Abstract base feed generator.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

abstract class TMF_Feed_Generator {

	/**
	 * Feed slug (e.g. "google", "facebook").
	 *
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Human-readable label.
	 *
	 * @var string
	 */
	protected $label = '';

	/**
	 * Retrieve all publishable WooCommerce products.
	 *
	 * @return WC_Product[]
	 */
	protected function get_products() {
		$args = array(
			'status' => 'publish',
			'limit'  => -1,
			'type'   => array( 'simple', 'variable', 'variation', 'external', 'grouped' ),
		);
		$args = apply_filters( 'tmf_feed_product_query_args', $args, $this->slug );

		$products = wc_get_products( $args );

		// For variable products, also include each variation.
		$expanded = array();
		foreach ( $products as $product ) {
			if ( $product->is_type( 'variable' ) ) {
				$children = $product->get_children();
				foreach ( $children as $child_id ) {
					$variation = wc_get_product( $child_id );
					if ( $variation && $variation->is_purchasable() && 'publish' === $variation->get_status() ) {
						$expanded[] = $variation;
					}
				}
			} else {
				$expanded[] = $product;
			}
		}

		return apply_filters( 'tmf_feed_products', $expanded, $this->slug );
	}

	/**
	 * Generate the feed output.
	 *
	 * @return string Feed content (XML, CSV, etc.).
	 */
	abstract public function generate();

	/**
	 * Get the content type for HTTP headers.
	 *
	 * @return string MIME type.
	 */
	public function content_type() {
		return 'application/xml; charset=UTF-8';
	}

	/**
	 * Get slug.
	 */
	public function get_slug() {
		return $this->slug;
	}

	/**
	 * Get label.
	 */
	public function get_label() {
		return $this->label;
	}

	/**
	 * Escape value for XML.
	 */
	protected function xml_escape( $value ) {
		return '<![CDATA[' . str_replace( ']]>', ']]]]><![CDATA[>', (string) $value ) . ']]>';
	}

	/**
	 * Format price with currency.
	 */
	protected function format_price( $price, $currency = '' ) {
		if ( '' === $price || null === $price ) {
			return '';
		}
		if ( empty( $currency ) ) {
			$currency = get_woocommerce_currency();
		}
		return number_format( (float) $price, 2, '.', '' ) . ' ' . $currency;
	}
}
