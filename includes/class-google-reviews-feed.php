<?php
/**
 * Google Merchant Reviews feed generator.
 *
 * Produces an XML feed compliant with Google's Merchant Reviews schema v5.0.
 * Pulls reviews from WooCommerce product reviews (comments).
 *
 * @see https://developers.google.com/merchant-review-feeds
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Google_Reviews_Feed extends TMF_Feed_Generator {

	protected $slug  = 'google-reviews';
	protected $label = 'Google Merchant Reviews';

	/**
	 * Generate the Merchant Reviews XML feed.
	 *
	 * @return string XML content.
	 */
	public function generate() {
		$shop_name = get_bloginfo( 'name' );
		$shop_url  = home_url( '/' );
		$merchant_id = get_option( 'tmf_google_merchant_id', '0' );

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<feed xmlns="http://schemas.google.com/merchant_reviews/5.0"' . "\n";
		$xml .= '      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
		$xml .= '      xsi:schemaLocation="http://schemas.google.com/merchant_reviews/5.0 http://www.gstatic.com/productsearch/static/reviews/5.0/merchant_reviews.xsd">' . "\n\n";

		// Merchant block.
		$xml .= "<merchants>\n";
		$xml .= '  <merchant id="' . esc_attr( $merchant_id ) . '">' . "\n";
		$xml .= '    <name>' . $this->xml_escape( $shop_name ) . "</name>\n";
		$xml .= '    <merchant_url>' . esc_url( $shop_url ) . "</merchant_url>\n";
		$xml .= '    <create_timestamp>' . gmdate( 'Y-m-d\TH:i:s\Z', strtotime( get_option( 'tmf_merchant_created', current_time( 'mysql' ) ) ) ) . "</create_timestamp>\n";
		$xml .= '    <last_update_timestamp>' . gmdate( 'Y-m-d\TH:i:s\Z' ) . "</last_update_timestamp>\n";
		$xml .= "  </merchant>\n";
		$xml .= "</merchants>\n\n";

		// Reviews block.
		$reviews = get_comments( array(
			'post_type' => 'product',
			'status'    => 'approve',
			'number'    => 10000,
			'meta_key'  => 'rating',
		) );

		$xml .= "<reviews>\n";

		foreach ( $reviews as $review ) {
			$rating = (int) get_comment_meta( $review->comment_ID, 'rating', true );
			if ( $rating < 1 || $rating > 5 ) {
				continue;
			}

			$reviewer_name = ! empty( $review->comment_author ) ? $review->comment_author : 'Anonymous';
			$content       = wp_strip_all_tags( $review->comment_content );
			$created       = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $review->comment_date_gmt ) );

			// Derive title from first sentence or first 80 chars.
			$title = '';
			$first_sentence = strtok( $content, '.!?' );
			if ( $first_sentence && mb_strlen( $first_sentence ) <= 80 ) {
				$title = trim( $first_sentence );
			}

			$xml .= '  <review id="' . esc_attr( $review->comment_ID ) . '" mid="' . esc_attr( $merchant_id ) . '">' . "\n";
			$xml .= '    <reviewer_name>' . $this->xml_escape( $reviewer_name ) . "</reviewer_name>\n";
			$xml .= '    <create_timestamp>' . $created . "</create_timestamp>\n";
			$xml .= '    <last_update_timestamp>' . $created . "</last_update_timestamp>\n";
			$xml .= "    <country_code>US</country_code>\n";

			if ( ! empty( $title ) ) {
				$xml .= '    <title>' . $this->xml_escape( $title ) . "</title>\n";
			}

			$xml .= '    <content>' . $this->xml_escape( $content ) . "</content>\n";
			$xml .= "    <ratings>\n";
			// Google reviews use 1-5 scale; WooCommerce uses the same.
			$xml .= '      <overall min="1" max="5">' . $rating . "</overall>\n";
			$xml .= "    </ratings>\n";
			$xml .= "    <collection_method>after_fulfillment</collection_method>\n";
			$xml .= "  </review>\n";
		}

		$xml .= "</reviews>\n\n";
		$xml .= "</feed>\n";

		return apply_filters( 'tmf_google_reviews_feed_xml', $xml );
	}
}
