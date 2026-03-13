<?php
/**
 * Main plugin orchestration class.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Tigon_Merchant_Feeds {

	/**
	 * Get all registered feed slugs (built-in + custom).
	 *
	 * @return array Associative array of slug => label.
	 */
	public static function get_all_feeds() {
		$feeds  = array();
		$stored = get_option( 'tmf_feeds', array() );
		foreach ( $stored as $slug => $cfg ) {
			if ( ! empty( $cfg['enabled'] ) ) {
				$feeds[ $slug ] = $cfg['label'];
			}
		}
		$custom = get_option( 'tmf_custom_feeds', array() );
		foreach ( $custom as $slug => $cfg ) {
			if ( ! empty( $cfg['enabled'] ) ) {
				$feeds[ $slug ] = $cfg['label'];
			}
		}
		return $feeds;
	}
}
