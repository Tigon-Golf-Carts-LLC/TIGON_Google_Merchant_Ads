<?php
/**
 * Plugin Name: TIGON Merchant Feeds
 * Plugin URI:  https://jaslowdigital.com
 * Description: Generate product feed URLs for Google Merchant, eBay, Amazon, Walmart, TikTok, Facebook, and unlimited custom marketplace feeds — powered by WooCommerce product data.
 * Version:     1.0.0
 * Author:      Noah Jaslow — Jaslow Digital
 * Author URI:  https://jaslowdigital.com
 * Text Domain: tigon-merchant-feeds
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * License:     Proprietary. All rights reserved. © TIGON Golf Carts.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

define( 'TMF_VERSION', '1.0.0' );
define( 'TMF_PLUGIN_FILE', __FILE__ );
define( 'TMF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TMF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin classes.
 */
function tmf_load() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'tmf_woocommerce_missing_notice' );
		return;
	}

	require_once TMF_PLUGIN_DIR . 'includes/class-field-mapper.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-feed-generator.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-google-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-google-reviews-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-local-inventory-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-facebook-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-amazon-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-ebay-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-walmart-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-tiktok-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-custom-feed.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-feed-endpoint.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-google-merchant-api.php';
	require_once TMF_PLUGIN_DIR . 'includes/class-tigon-merchant-feeds.php';

	if ( is_admin() ) {
		require_once TMF_PLUGIN_DIR . 'admin/class-admin.php';
		TMF_Admin::init();
	}

	TMF_Feed_Endpoint::init();
	TMF_Google_Merchant_API::init();
}
add_action( 'plugins_loaded', 'tmf_load', 20 );

/**
 * WooCommerce missing notice.
 */
function tmf_woocommerce_missing_notice() {
	echo '<div class="notice notice-error"><p><strong>TIGON Merchant Feeds</strong> requires WooCommerce to be installed and active.</p></div>';
}

/**
 * Activation: flush rewrite rules and set defaults.
 */
function tmf_activate() {
	if ( ! get_option( 'tmf_feed_secret' ) ) {
		update_option( 'tmf_feed_secret', wp_generate_password( 24, false ) );
	}
	$defaults = array(
		'google'                  => array( 'enabled' => true, 'label' => 'Google Merchant' ),
		'google-reviews'          => array( 'enabled' => true, 'label' => 'Google Merchant Reviews' ),
		'google-local-inventory'  => array( 'enabled' => false, 'label' => 'Google Local Inventory' ),
		'facebook'                => array( 'enabled' => true, 'label' => 'Facebook / Meta' ),
		'amazon'                  => array( 'enabled' => true, 'label' => 'Amazon' ),
		'ebay'                    => array( 'enabled' => true, 'label' => 'eBay' ),
		'walmart'                 => array( 'enabled' => true, 'label' => 'Walmart' ),
		'tiktok'                  => array( 'enabled' => true, 'label' => 'TikTok Shop' ),
	);
	if ( ! get_option( 'tmf_feeds' ) ) {
		update_option( 'tmf_feeds', $defaults );
	}
	if ( ! get_option( 'tmf_field_mappings' ) ) {
		update_option( 'tmf_field_mappings', array() );
	}
	if ( ! get_option( 'tmf_custom_feeds' ) ) {
		update_option( 'tmf_custom_feeds', array() );
	}

	// Default Google category for Golf Carts.
	if ( ! get_option( 'tmf_google_category' ) ) {
		update_option( 'tmf_google_category', '3101' ); // Vehicles & Parts > Vehicles > Golf Carts
	}
	if ( ! get_option( 'tmf_google_category_name' ) ) {
		update_option( 'tmf_google_category_name', 'Vehicles & Parts > Vehicles > Golf Carts' );
	}

	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tmf_activate' );

/**
 * Deactivation: clean rewrite rules.
 */
function tmf_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tmf_deactivate' );

/**
 * Uninstall handled by uninstall.php.
 */
