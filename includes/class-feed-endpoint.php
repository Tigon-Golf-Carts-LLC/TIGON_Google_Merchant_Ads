<?php
/**
 * Feed endpoint — serves feed URLs.
 *
 * Feed URLs follow the pattern:
 *   /tigon-feed/{feed_slug}/?key={secret}
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Feed_Endpoint {

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'handle_request' ) );
	}

	/**
	 * Register rewrite rules.
	 */
	public static function add_rewrite_rules() {
		add_rewrite_rule(
			'^tigon-feed/([a-zA-Z0-9_-]+)/?$',
			'index.php?tmf_feed=$matches[1]',
			'top'
		);
	}

	/**
	 * Register query vars.
	 */
	public static function add_query_vars( $vars ) {
		$vars[] = 'tmf_feed';
		$vars[] = 'key';
		return $vars;
	}

	/**
	 * Handle the feed request.
	 */
	public static function handle_request() {
		$feed_slug = get_query_var( 'tmf_feed' );
		if ( empty( $feed_slug ) ) {
			return;
		}

		// Validate secret key.
		$key    = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '';
		$secret = get_option( 'tmf_feed_secret', '' );
		if ( empty( $secret ) || ! hash_equals( $secret, $key ) ) {
			status_header( 403 );
			echo 'Forbidden: invalid feed key.';
			exit;
		}

		$generator = self::get_generator( $feed_slug );
		if ( ! $generator ) {
			status_header( 404 );
			echo 'Feed not found.';
			exit;
		}

		// Set cache headers.
		header( 'Content-Type: ' . $generator->content_type() );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'X-Robots-Tag: noindex, nofollow' );

		echo $generator->generate(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Return the correct generator instance for a slug.
	 *
	 * @param string $slug Feed slug.
	 * @return TMF_Feed_Generator|null
	 */
	public static function get_generator( $slug ) {
		$built_in = array(
			'google'         => 'TMF_Google_Feed',
			'google-reviews' => 'TMF_Google_Reviews_Feed',
			'facebook'       => 'TMF_Facebook_Feed',
			'amazon'         => 'TMF_Amazon_Feed',
			'ebay'           => 'TMF_Ebay_Feed',
			'walmart'        => 'TMF_Walmart_Feed',
			'tiktok'         => 'TMF_Tiktok_Feed',
		);

		if ( isset( $built_in[ $slug ] ) ) {
			$feeds = get_option( 'tmf_feeds', array() );
			if ( ! empty( $feeds[ $slug ]['enabled'] ) ) {
				return new $built_in[ $slug ]();
			}
			return null;
		}

		// Check custom feeds.
		$custom = get_option( 'tmf_custom_feeds', array() );
		if ( isset( $custom[ $slug ] ) && ! empty( $custom[ $slug ]['enabled'] ) ) {
			return new TMF_Custom_Feed( $slug, $custom[ $slug ] );
		}

		return apply_filters( 'tmf_feed_generator', null, $slug );
	}

	/**
	 * Build the public URL for a feed.
	 *
	 * @param string $slug Feed slug.
	 * @return string
	 */
	public static function get_feed_url( $slug ) {
		$secret = get_option( 'tmf_feed_secret', '' );
		return home_url( '/tigon-feed/' . $slug . '/?key=' . $secret );
	}
}
