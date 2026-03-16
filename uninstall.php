<?php
/**
 * TIGON Merchant Feeds — Uninstall
 *
 * @package TigonMerchantFeeds
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// Feed settings.
delete_option( 'tmf_feed_secret' );
delete_option( 'tmf_feeds' );
delete_option( 'tmf_field_mappings' );
delete_option( 'tmf_custom_feeds' );
delete_option( 'tmf_google_category' );
delete_option( 'tmf_google_category_name' );
delete_option( 'tmf_default_shipping_cost' );
delete_option( 'tmf_default_shipping_service' );

// Google API settings.
delete_option( 'tmf_google_merchant_id' );
delete_option( 'tmf_google_api_credentials' );
delete_option( 'tmf_google_data_source' );
delete_option( 'tmf_google_data_source_display' );
delete_option( 'tmf_google_sync_frequency' );
delete_option( 'tmf_google_last_sync_results' );
delete_option( 'tmf_merchant_created' );

// Rewrite version tracker.
delete_option( 'tmf_rewrite_version' );

// Clear cached access token.
delete_transient( 'tmf_google_access_token' );

// Unschedule cron.
$timestamp = wp_next_scheduled( 'tmf_google_api_sync' );
if ( $timestamp ) {
	wp_unschedule_event( $timestamp, 'tmf_google_api_sync' );
}
