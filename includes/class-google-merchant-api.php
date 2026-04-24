<?php
/**
 * Google Merchant API integration.
 *
 * Pushes WooCommerce products directly to Google Merchant Center
 * via the Merchant API (REST). Supports:
 *   - OAuth2 authentication via Service Account (JSON key file)
 *   - Creating API data sources
 *   - Inserting / updating / deleting products
 *   - Multi-store support (products sorted by store/location)
 *   - Scheduled sync via WP-Cron
 *
 * Merchant API endpoints used:
 *   POST /datasources/v1/accounts/{id}/dataSources
 *   POST /products/v1/accounts/{id}/productInputs:insert
 *   DELETE /products/v1/accounts/{id}/productInputs/{name}
 *   GET  /products/v1/accounts/{id}/products
 *
 * @see https://developers.google.com/merchant/api/overview
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Google_Merchant_API {

	/**
	 * Merchant API base URL.
	 */
	const API_BASE = 'https://merchantapi.googleapis.com';

	/**
	 * OAuth2 token URL.
	 */
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';

	/**
	 * Required OAuth2 scope.
	 */
	const SCOPE = 'https://www.googleapis.com/auth/content';

	// =========================================================================
	//  Init
	// =========================================================================

	/**
	 * Initialize hooks.
	 */
	public static function init() {
		// Register cron for scheduled sync.
		add_action( 'tmf_google_api_sync', array( __CLASS__, 'sync_all_products' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_intervals' ) );
	}

	/**
	 * Add custom cron intervals.
	 */
	public static function add_cron_intervals( $schedules ) {
		$schedules['tmf_every_6_hours'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => 'Every 6 Hours',
		);
		$schedules['tmf_every_12_hours'] = array(
			'interval' => 12 * HOUR_IN_SECONDS,
			'display'  => 'Every 12 Hours',
		);
		return $schedules;
	}

	// =========================================================================
	//  Authentication
	// =========================================================================

	/**
	 * Get an OAuth2 access token from a service account JSON key.
	 *
	 * @return string|WP_Error Access token or error.
	 */
	public static function get_access_token() {
		// Check for cached token.
		$cached = get_transient( 'tmf_google_access_token' );
		if ( $cached ) {
			return $cached;
		}

		$credentials = self::get_credentials();
		if ( is_wp_error( $credentials ) ) {
			return $credentials;
		}

		// Build JWT for service account auth.
		$now = time();
		$jwt_header  = self::base64url_encode( wp_json_encode( array(
			'alg' => 'RS256',
			'typ' => 'JWT',
		) ) );
		$jwt_payload = self::base64url_encode( wp_json_encode( array(
			'iss'   => $credentials['client_email'],
			'scope' => self::SCOPE,
			'aud'   => self::TOKEN_URL,
			'iat'   => $now,
			'exp'   => $now + 3600,
		) ) );

		$signing_input = $jwt_header . '.' . $jwt_payload;

		// Sign with RSA-SHA256.
		$private_key = openssl_pkey_get_private( $credentials['private_key'] );
		if ( ! $private_key ) {
			return new WP_Error( 'tmf_auth', 'Invalid private key in service account credentials.' );
		}
		$signature = '';
		if ( ! openssl_sign( $signing_input, $signature, $private_key, OPENSSL_ALGO_SHA256 ) ) {
			return new WP_Error( 'tmf_auth', 'Failed to sign JWT.' );
		}

		$jwt = $signing_input . '.' . self::base64url_encode( $signature );

		// Exchange JWT for access token.
		$response = wp_remote_post( self::TOKEN_URL, array(
			'body' => array(
				'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
				'assertion'  => $jwt,
			),
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['access_token'] ) ) {
			$error_msg = isset( $body['error_description'] ) ? $body['error_description'] : 'Unknown auth error.';
			return new WP_Error( 'tmf_auth', 'Google OAuth failed: ' . $error_msg );
		}

		// Cache token (expires in ~1 hour, cache for 50 min).
		set_transient( 'tmf_google_access_token', $body['access_token'], 3000 );

		return $body['access_token'];
	}

	/**
	 * Get service account credentials from saved option.
	 *
	 * @return array|WP_Error Parsed credentials or error.
	 */
	private static function get_credentials() {
		$json = get_option( 'tmf_google_api_credentials', '' );
		if ( empty( $json ) ) {
			return new WP_Error( 'tmf_config', 'Google API credentials not configured. Go to TIGON Feeds > Google API.' );
		}
		$creds = json_decode( $json, true );
		if ( empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
			return new WP_Error( 'tmf_config', 'Invalid service account JSON. Must contain client_email and private_key.' );
		}
		return $creds;
	}

	/**
	 * Get the Merchant Center account ID.
	 *
	 * @return string|WP_Error
	 */
	public static function get_account_id() {
		$id = get_option( 'tmf_google_merchant_id', '' );
		if ( empty( $id ) ) {
			return new WP_Error( 'tmf_config', 'Google Merchant Center account ID not configured.' );
		}
		return $id;
	}

	// =========================================================================
	//  Data Source Management
	// =========================================================================

	/**
	 * Create an API data source in Merchant Center.
	 *
	 * @param string $name     Display name for the data source.
	 * @param string $country  Target country (e.g. "US").
	 * @param string $language Content language (e.g. "en").
	 * @return array|WP_Error  Created data source or error.
	 */
	public static function create_data_source( $name = 'TIGON Merchant Feeds', $country = 'US', $language = 'en' ) {
		$account_id = self::get_account_id();
		if ( is_wp_error( $account_id ) ) {
			return $account_id;
		}

		$feed_label = strtoupper( $country );

		$body = array(
			'displayName'              => $name,
			'primaryProductDataSource' => array(
				'channel'         => 'ONLINE_PRODUCTS',
				'countries'       => array( $country ),
				'contentLanguage' => $language,
				'feedLabel'       => $feed_label,
			),
		);

		$response = self::api_request(
			'POST',
			'/datasources/v1/accounts/' . $account_id . '/dataSources',
			$body
		);

		if ( ! is_wp_error( $response ) && ! empty( $response['name'] ) ) {
			// Save the data source name for product inserts.
			update_option( 'tmf_google_data_source', $response['name'] );
			update_option( 'tmf_google_data_source_display', $name );
		}

		return $response;
	}

	/**
	 * List existing data sources.
	 *
	 * @return array|WP_Error
	 */
	public static function list_data_sources() {
		$account_id = self::get_account_id();
		if ( is_wp_error( $account_id ) ) {
			return $account_id;
		}

		return self::api_request(
			'GET',
			'/datasources/v1/accounts/' . $account_id . '/dataSources'
		);
	}

	// =========================================================================
	//  Product Operations
	// =========================================================================

	/**
	 * Insert or update a single product in Merchant Center.
	 *
	 * @param WC_Product $product  WooCommerce product.
	 * @param string     $store_id Optional store code for multi-store.
	 * @return array|WP_Error API response or error.
	 */
	public static function insert_product( WC_Product $product, $store_id = '' ) {
		$account_id  = self::get_account_id();
		$data_source = get_option( 'tmf_google_data_source', '' );

		if ( is_wp_error( $account_id ) ) {
			return $account_id;
		}
		if ( empty( $data_source ) ) {
			return new WP_Error( 'tmf_config', 'No API data source configured. Create one in TIGON Feeds > Google API.' );
		}

		$data     = TMF_Field_Mapper::map( $product );
		$country  = get_option( 'woocommerce_default_country', 'US' );
		if ( strpos( $country, ':' ) !== false ) {
			$country = explode( ':', $country )[0];
		}

		$offer_id = ! empty( $data['sku'] ) ? $data['sku'] : (string) $data['id'];

		// Build product attributes for the API.
		$attributes = array(
			'title'       => self::truncate_api( $data['title'], 150 ),
			'description' => self::truncate_api( $data['description'], 5000 ),
			'link'        => $data['link'],
			'imageLink'   => $data['image_link'],
			'availability' => TMF_Field_Mapper::map_availability_api( $product->get_stock_status() ),
			'condition'    => strtoupper( $data['condition'] ),
			'brand'        => $data['brand'],
			'price'        => array(
				'amountMicros' => self::to_micros( $data['regular_price'] ?: $data['price'] ),
				'currencyCode' => $data['currency'],
			),
			'googleProductCategory' => $data['google_product_category'],
		);

		// Additional images.
		if ( ! empty( $data['additional_image_links'] ) ) {
			$attributes['additionalImageLinks'] = array_slice( $data['additional_image_links'], 0, 10 );
		}

		// Sale price.
		if ( ! empty( $data['sale_price'] ) && (float) $data['sale_price'] < (float) ( $data['regular_price'] ?: $data['price'] ) ) {
			$attributes['salePrice'] = array(
				'amountMicros' => self::to_micros( $data['sale_price'] ),
				'currencyCode' => $data['currency'],
			);
			if ( ! empty( $data['sale_price_effective_date'] ) ) {
				$parts = explode( '/', $data['sale_price_effective_date'] );
				if ( count( $parts ) === 2 ) {
					$attributes['salePriceEffectiveDate'] = array(
						'startDate' => ! empty( $parts[0] ) ? $parts[0] : null,
						'endDate'   => ! empty( $parts[1] ) ? $parts[1] : null,
					);
				}
			}
		}

		// GTIN.
		if ( ! empty( $data['gtin'] ) ) {
			$attributes['gtins'] = array( $data['gtin'] );
		}

		// MPN.
		if ( ! empty( $data['mpn'] ) ) {
			$attributes['mpn'] = $data['mpn'];
		}

		// Identifier exists.
		if ( empty( $data['gtin'] ) && empty( $data['mpn'] ) ) {
			$attributes['identifierExists'] = false;
		}

		// Color, size, material, pattern.
		if ( ! empty( $data['color'] ) ) {
			$attributes['color'] = $data['color'];
		}
		if ( ! empty( $data['size'] ) ) {
			$attributes['size'] = $data['size'];
		}
		if ( ! empty( $data['material'] ) ) {
			$attributes['material'] = $data['material'];
		}
		if ( ! empty( $data['pattern'] ) ) {
			$attributes['pattern'] = $data['pattern'];
		}

		// Shipping weight.
		if ( ! empty( $data['weight'] ) ) {
			$attributes['shippingWeight'] = array(
				'value' => (float) $data['weight'],
				'unit'  => $data['weight_unit'],
			);
		}

		// Shipping dimensions.
		if ( ! empty( $data['length'] ) ) {
			$attributes['shippingLength'] = array(
				'value' => (float) $data['length'],
				'unit'  => $data['dimension_unit'],
			);
		}
		if ( ! empty( $data['width'] ) ) {
			$attributes['shippingWidth'] = array(
				'value' => (float) $data['width'],
				'unit'  => $data['dimension_unit'],
			);
		}
		if ( ! empty( $data['height'] ) ) {
			$attributes['shippingHeight'] = array(
				'value' => (float) $data['height'],
				'unit'  => $data['dimension_unit'],
			);
		}

		// Item group ID for variations.
		if ( ! empty( $data['item_group_id'] ) ) {
			$attributes['itemGroupId'] = (string) $data['item_group_id'];
		}

		// Product type (your category path).
		if ( ! empty( $data['category_path'] ) ) {
			$attributes['productTypes'] = array( $data['category_path'] );
		}

		// Product highlights.
		if ( ! empty( $data['product_highlight'] ) ) {
			$attributes['productHighlights'] = array_slice( $data['product_highlight'], 0, 10 );
		}

		// Product details (structured specs).
		if ( ! empty( $data['product_detail'] ) ) {
			$details = array();
			foreach ( array_slice( $data['product_detail'], 0, 100 ) as $d ) {
				$details[] = array(
					'sectionName'    => $d['section_name'],
					'attributeName'  => $d['attribute_name'],
					'attributeValue' => $d['attribute_value'],
				);
			}
			$attributes['productDetails'] = $details;
		}

		// Custom labels.
		for ( $i = 0; $i <= 4; $i++ ) {
			$key = 'custom_label_' . $i;
			if ( ! empty( $data[ $key ] ) ) {
				$attributes[ 'customLabel' . $i ] = $data[ $key ];
			}
		}

		// Canonical link.
		if ( ! empty( $data['canonical_link'] ) ) {
			$attributes['canonicalLink'] = $data['canonical_link'];
		}

		// Shipping info.
		$shipping_cost = get_option( 'tmf_default_shipping_cost', '' );
		if ( ! empty( $shipping_cost ) ) {
			$attributes['shipping'] = array(
				array(
					'country' => $country,
					'service' => get_option( 'tmf_default_shipping_service', 'Standard' ),
					'price'   => array(
						'amountMicros' => self::to_micros( $shipping_cost ),
						'currencyCode' => $data['currency'],
					),
				),
			);
		}

		// Store code for multi-store / local inventory.
		$custom_attributes = array();
		if ( ! empty( $store_id ) ) {
			$custom_attributes[] = array(
				'name'  => 'store_code',
				'value' => $store_id,
			);
		}

		// Build the product input.
		$product_input = array(
			'offerId'           => $offer_id,
			'contentLanguage'   => 'en',
			'feedLabel'         => strtoupper( $country ),
			'channel'           => 'ONLINE',
			'productAttributes' => $attributes,
		);

		if ( ! empty( $custom_attributes ) ) {
			$product_input['customAttributes'] = $custom_attributes;
		}

		$product_input = apply_filters( 'tmf_google_api_product_input', $product_input, $data, $product );

		$url = '/products/v1/accounts/' . $account_id . '/productInputs:insert?dataSource=' . $data_source;

		$result = self::api_request( 'POST', $url, $product_input );

		if ( ! is_wp_error( $result ) ) {
			// Save last sync timestamp on the product.
			$product->update_meta_data( '_tmf_google_last_sync', current_time( 'mysql' ) );
			$product->update_meta_data( '_tmf_google_sync_status', 'synced' );
			if ( ! empty( $result['name'] ) ) {
				$product->update_meta_data( '_tmf_google_product_name', $result['name'] );
			}
			$product->save_meta_data();
		}

		return $result;
	}

	/**
	 * Delete a product from Merchant Center.
	 *
	 * @param WC_Product $product WooCommerce product.
	 * @return array|WP_Error
	 */
	public static function delete_product( WC_Product $product ) {
		$account_id  = self::get_account_id();
		$data_source = get_option( 'tmf_google_data_source', '' );

		if ( is_wp_error( $account_id ) ) {
			return $account_id;
		}

		$data     = TMF_Field_Mapper::map( $product );
		$offer_id = ! empty( $data['sku'] ) ? $data['sku'] : (string) $data['id'];
		$country  = get_option( 'woocommerce_default_country', 'US' );
		if ( strpos( $country, ':' ) !== false ) {
			$country = explode( ':', $country )[0];
		}

		$name = 'accounts/' . $account_id . '/productInputs/online~en~' . strtoupper( $country ) . '~' . $offer_id;

		$result = self::api_request(
			'DELETE',
			'/products/v1/' . $name . '?dataSource=' . $data_source
		);

		if ( ! is_wp_error( $result ) ) {
			$product->delete_meta_data( '_tmf_google_last_sync' );
			$product->update_meta_data( '_tmf_google_sync_status', 'deleted' );
			$product->delete_meta_data( '_tmf_google_product_name' );
			$product->save_meta_data();
		}

		return $result;
	}

	/**
	 * List products currently in Merchant Center.
	 *
	 * @param int $page_size Number of products per page.
	 * @return array|WP_Error
	 */
	public static function list_products( $page_size = 100 ) {
		$account_id = self::get_account_id();
		if ( is_wp_error( $account_id ) ) {
			return $account_id;
		}

		return self::api_request(
			'GET',
			'/products/v1/accounts/' . $account_id . '/products?pageSize=' . $page_size
		);
	}

	// =========================================================================
	//  Bulk Sync
	// =========================================================================

	/**
	 * Sync all published WooCommerce products to Google Merchant Center.
	 *
	 * @param string $store_id Optional store code filter.
	 * @return array Summary of sync results.
	 */
	public static function sync_all_products( $store_id = '' ) {
		$account_id  = self::get_account_id();
		$data_source = get_option( 'tmf_google_data_source', '' );

		if ( is_wp_error( $account_id ) ) {
			$msg = $account_id->get_error_message();
			self::log( 'Sync aborted: ' . $msg );
			return array( 'error' => $msg );
		}
		if ( empty( $data_source ) ) {
			$msg = 'No API data source configured. Create one in TIGON Feeds > Google API.';
			self::log( 'Sync aborted: ' . $msg );
			return array( 'error' => $msg );
		}

		$args = array(
			'status' => 'publish',
			'limit'  => -1,
			'type'   => array( 'simple', 'variable', 'variation', 'external' ),
		);

		// Filter by store if provided.
		if ( ! empty( $store_id ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_tmf_store',
					'value' => $store_id,
				),
			);
		}

		$products = wc_get_products( $args );

		// Expand variable products into variations.
		$all = array();
		foreach ( $products as $product ) {
			if ( $product->is_type( 'variable' ) ) {
				foreach ( $product->get_children() as $child_id ) {
					$variation = wc_get_product( $child_id );
					if ( $variation && $variation->is_purchasable() ) {
						$all[] = $variation;
					}
				}
			} else {
				$all[] = $product;
			}
		}

		$results = array(
			'total'     => count( $all ),
			'synced'    => 0,
			'errors'    => 0,
			'error_log' => array(),
			'store'     => $store_id ?: 'all',
			'started'   => current_time( 'mysql' ),
		);

		foreach ( $all as $product ) {
			$result = self::insert_product( $product, $store_id );
			if ( is_wp_error( $result ) ) {
				$results['errors']++;
				$results['error_log'][] = array(
					'product_id' => $product->get_id(),
					'sku'        => $product->get_sku(),
					'error'      => $result->get_error_message(),
				);
				self::log( 'Sync error for product #' . $product->get_id() . ': ' . $result->get_error_message() );
			} else {
				$results['synced']++;
			}

			// Rate limiting: Google recommends max 2 requests per second.
			usleep( 500000 ); // 0.5 seconds between requests.
		}

		$results['finished'] = current_time( 'mysql' );
		update_option( 'tmf_google_last_sync_results', $results );
		self::log( 'Sync complete: ' . $results['synced'] . '/' . $results['total'] . ' products synced.' );

		return $results;
	}

	// =========================================================================
	//  Cron Management
	// =========================================================================

	/**
	 * Schedule the cron sync.
	 *
	 * @param string $frequency WP cron recurrence key.
	 */
	public static function schedule_sync( $frequency = 'tmf_every_6_hours' ) {
		$timestamp = wp_next_scheduled( 'tmf_google_api_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tmf_google_api_sync' );
		}
		if ( 'disabled' !== $frequency ) {
			wp_schedule_event( time(), $frequency, 'tmf_google_api_sync' );
		}
		update_option( 'tmf_google_sync_frequency', $frequency );
	}

	/**
	 * Unschedule sync.
	 */
	public static function unschedule_sync() {
		$timestamp = wp_next_scheduled( 'tmf_google_api_sync' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'tmf_google_api_sync' );
		}
	}

	// =========================================================================
	//  Store Management
	// =========================================================================

	/**
	 * Get configured stores.
	 *
	 * @return array Array of store_id => store_name.
	 */
	public static function get_stores() {
		return get_option( 'tmf_stores', array() );
	}

	/**
	 * Get products grouped by store.
	 *
	 * @return array Associative array of store_id => product count.
	 */
	public static function get_products_by_store() {
		$stores  = self::get_stores();
		$grouped = array();

		foreach ( $stores as $store_id => $store_name ) {
			$products = wc_get_products( array(
				'status'     => 'publish',
				'limit'      => -1,
				'return'     => 'ids',
				'meta_query' => array(
					array(
						'key'   => '_tmf_store',
						'value' => $store_id,
					),
				),
			) );
			$grouped[ $store_id ] = array(
				'name'  => $store_name,
				'count' => count( $products ),
			);
		}

		// Count unassigned products.
		$all_product_ids = wc_get_products( array(
			'status' => 'publish',
			'limit'  => -1,
			'return' => 'ids',
		) );
		$assigned = wc_get_products( array(
			'status'     => 'publish',
			'limit'      => -1,
			'return'     => 'ids',
			'meta_query' => array(
				array(
					'key'     => '_tmf_store',
					'compare' => 'EXISTS',
				),
			),
		) );
		$unassigned = count( $all_product_ids ) - count( $assigned );
		if ( $unassigned > 0 ) {
			$grouped['_unassigned'] = array(
				'name'  => 'Unassigned',
				'count' => $unassigned,
			);
		}

		return $grouped;
	}

	// =========================================================================
	//  HTTP Helpers
	// =========================================================================

	/**
	 * Make an authenticated request to the Merchant API.
	 *
	 * @param string     $method HTTP method (GET, POST, DELETE, PATCH).
	 * @param string     $path   API path (e.g. /products/v1/accounts/123/...).
	 * @param array|null $body   Request body for POST/PATCH.
	 * @return array|WP_Error Decoded response or error.
	 */
	private static function api_request( $method, $path, $body = null ) {
		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$url  = self::API_BASE . $path;
		$args = array(
			'method'  => $method,
			'timeout' => 60,
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		if ( $body && in_array( $method, array( 'POST', 'PATCH', 'PUT' ), true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$error_msg = 'API error (HTTP ' . $code . ')';
			if ( isset( $data['error']['message'] ) ) {
				$error_msg .= ': ' . $data['error']['message'];
			}
			return new WP_Error( 'tmf_api_error', $error_msg, array( 'status' => $code, 'response' => $data ) );
		}

		// DELETE returns empty body on success.
		if ( 204 === $code || ( 'DELETE' === $method && $code >= 200 && $code < 300 ) ) {
			return array( 'success' => true );
		}

		return $data ?: array( 'success' => true );
	}

	// =========================================================================
	//  Utility
	// =========================================================================

	/**
	 * Convert a price to micros (Google API format: $15.99 → 15990000).
	 */
	private static function to_micros( $price ) {
		return (string) round( (float) $price * 1000000 );
	}

	/**
	 * Base64url encode (for JWT).
	 */
	private static function base64url_encode( $data ) {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}

	/**
	 * Log a message to the WooCommerce logger.
	 */
	private static function log( $message ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( $message, array( 'source' => 'tigon-merchant-feeds' ) );
		}
	}

	/**
	 * Truncate for API (same as feed but static context).
	 */
	private static function truncate_api( $str, $max ) {
		if ( mb_strlen( $str ) <= $max ) {
			return $str;
		}
		return mb_substr( $str, 0, $max - 3 ) . '...';
	}
}
