<?php
/**
 * Feed endpoint — serves feed URLs.
 *
 * Feed URLs follow the pattern:
 *   /tigon-feed/{feed_slug}/?key={secret}
 *
 * Two routing methods are used for maximum compatibility:
 *   1. WordPress rewrite rules (clean URLs via mod_rewrite)
 *   2. Early `parse_request` fallback (catches requests even when
 *      rewrite rules haven't been flushed or are overridden by cache)
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

		// Early fallback: intercept the request before WordPress 404s it.
		// This fires even if rewrite rules are stale or missing.
		add_action( 'parse_request', array( __CLASS__, 'early_intercept' ) );
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

		// Auto-flush rewrite rules once after plugin update.
		if ( get_option( 'tmf_rewrite_version' ) !== TMF_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'tmf_rewrite_version', TMF_VERSION );
		}
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
	 * Early intercept: parse the URL ourselves and serve the feed
	 * before WordPress can 404 the request. This is the fallback
	 * that works even when rewrite rules are not flushed.
	 *
	 * @param WP $wp WordPress request object.
	 */
	public static function early_intercept( $wp ) {
		// Only act if WordPress didn't already match our rewrite rule.
		if ( ! empty( $wp->query_vars['tmf_feed'] ) ) {
			return;
		}

		// Parse the request URI ourselves.
		$path = trim( wp_parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

		// Remove site subdirectory prefix if WordPress is in a subdirectory.
		$home_path = trim( wp_parse_url( home_url(), PHP_URL_PATH ) ?: '', '/' );
		if ( $home_path && 0 === strpos( $path, $home_path ) ) {
			$path = trim( substr( $path, strlen( $home_path ) ), '/' );
		}

		if ( preg_match( '#^tigon-feed/([a-zA-Z0-9_-]+)$#', $path, $matches ) ) {
			$wp->query_vars['tmf_feed'] = $matches[1];
		}
	}

	/**
	 * Handle the feed request.
	 */
	public static function handle_request() {
		$feed_slug = get_query_var( 'tmf_feed' );
		if ( empty( $feed_slug ) ) {
			return;
		}

		// Validate secret key — check both query_var and $_GET.
		$key = get_query_var( 'key', '' );
		if ( empty( $key ) && isset( $_GET['key'] ) ) {
			$key = sanitize_text_field( wp_unslash( $_GET['key'] ) );
		}

		$secret = get_option( 'tmf_feed_secret', '' );
		if ( empty( $secret ) || ! hash_equals( $secret, $key ) ) {
			status_header( 403 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo 'Forbidden: invalid feed key.';
			exit;
		}

		$generator = self::get_generator( $feed_slug );
		if ( ! $generator ) {
			status_header( 404 );
			header( 'Content-Type: text/plain; charset=UTF-8' );
			echo 'Feed not found or not enabled.';
			exit;
		}

		// Prevent any output buffering from other plugins.
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		// Set proper headers for the feed.
		$content_type = $generator->content_type();
		status_header( 200 );
		header( 'Content-Type: ' . $content_type );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'X-Robots-Tag: noindex, nofollow' );
		header( 'X-Content-Type-Options: nosniff' );

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
