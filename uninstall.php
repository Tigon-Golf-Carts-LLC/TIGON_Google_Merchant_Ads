<?php
/**
 * TIGON Merchant Feeds — Uninstall
 *
 * @package TigonMerchantFeeds
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'tmf_feed_secret' );
delete_option( 'tmf_feeds' );
delete_option( 'tmf_field_mappings' );
delete_option( 'tmf_custom_feeds' );
delete_option( 'tmf_google_category' );
delete_option( 'tmf_google_category_name' );
