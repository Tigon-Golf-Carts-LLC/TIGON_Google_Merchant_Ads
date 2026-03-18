<?php
/**
 * Admin interface for TIGON Merchant Feeds.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Admin {

	/**
	 * Initialize admin hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_actions' ) );
	}

	/**
	 * Register admin menu pages.
	 */
	public static function add_menu() {
		$icon = TMF_PLUGIN_URL . 'admin/img/NEW GOLF CART (4).ico';

		add_menu_page(
			'TIGON Merchant Feeds',
			'TIGON Feeds',
			'manage_woocommerce',
			'tigon-merchant-feeds',
			array( __CLASS__, 'page_dashboard' ),
			$icon,
			56
		);

		add_submenu_page(
			'tigon-merchant-feeds',
			'Dashboard',
			'Dashboard',
			'manage_woocommerce',
			'tigon-merchant-feeds',
			array( __CLASS__, 'page_dashboard' )
		);

		add_submenu_page(
			'tigon-merchant-feeds',
			'Feed Settings',
			'Feed Settings',
			'manage_woocommerce',
			'tigon-feed-settings',
			array( __CLASS__, 'page_settings' )
		);

		add_submenu_page(
			'tigon-merchant-feeds',
			'Field Mapping',
			'Field Mapping',
			'manage_woocommerce',
			'tigon-field-mapping',
			array( __CLASS__, 'page_field_mapping' )
		);

		add_submenu_page(
			'tigon-merchant-feeds',
			'Custom Feeds',
			'Custom Feeds',
			'manage_woocommerce',
			'tigon-custom-feeds',
			array( __CLASS__, 'page_custom_feeds' )
		);

		add_submenu_page(
			'tigon-merchant-feeds',
			'Google API',
			'Google API',
			'manage_woocommerce',
			'tigon-google-api',
			array( __CLASS__, 'page_google_api' )
		);

		add_submenu_page(
			'tigon-merchant-feeds',
			'Stores',
			'Stores',
			'manage_woocommerce',
			'tigon-stores',
			array( __CLASS__, 'page_stores' )
		);
	}

	/**
	 * Enqueue admin CSS and JS.
	 */
	public static function enqueue_assets( $hook ) {
		if ( false === strpos( $hook, 'tigon' ) ) {
			return;
		}
		wp_enqueue_style( 'tmf-admin', TMF_PLUGIN_URL . 'admin/css/admin.css', array(), TMF_VERSION );
		wp_enqueue_script( 'tmf-admin', TMF_PLUGIN_URL . 'admin/js/admin.js', array( 'jquery' ), TMF_VERSION, true );
	}

	/**
	 * Handle form submissions (settings save, custom feed add/delete, regenerate key).
	 */
	public static function handle_actions() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// --- Save feed settings ------------------------------------------------
		if ( isset( $_POST['tmf_save_settings'] ) && check_admin_referer( 'tmf_settings_nonce' ) ) {
			$feeds  = get_option( 'tmf_feeds', array() );
			$slugs  = array( 'google', 'google-reviews', 'facebook', 'amazon', 'ebay', 'walmart', 'tiktok' );
			$posted = isset( $_POST['tmf_feeds_enabled'] ) ? (array) $_POST['tmf_feeds_enabled'] : array();
			foreach ( $slugs as $s ) {
				$feeds[ $s ]['enabled'] = in_array( $s, $posted, true );
			}
			update_option( 'tmf_feeds', $feeds );

			if ( isset( $_POST['tmf_google_category'] ) ) {
				update_option( 'tmf_google_category', sanitize_text_field( wp_unslash( $_POST['tmf_google_category'] ) ) );
			}
			if ( isset( $_POST['tmf_google_category_name'] ) ) {
				update_option( 'tmf_google_category_name', sanitize_text_field( wp_unslash( $_POST['tmf_google_category_name'] ) ) );
			}

			// Shipping defaults.
			if ( isset( $_POST['tmf_default_shipping_cost'] ) ) {
				update_option( 'tmf_default_shipping_cost', sanitize_text_field( wp_unslash( $_POST['tmf_default_shipping_cost'] ) ) );
			}
			if ( isset( $_POST['tmf_default_shipping_service'] ) ) {
				update_option( 'tmf_default_shipping_service', sanitize_text_field( wp_unslash( $_POST['tmf_default_shipping_service'] ) ) );
			}

			// Google Reviews feed toggle.
			$feeds  = get_option( 'tmf_feeds', array() );
			$posted = isset( $_POST['tmf_feeds_enabled'] ) ? (array) $_POST['tmf_feeds_enabled'] : array();
			if ( in_array( 'google-reviews', $posted, true ) ) {
				$feeds['google-reviews'] = array( 'enabled' => true, 'label' => 'Google Merchant Reviews' );
			} else {
				if ( isset( $feeds['google-reviews'] ) ) {
					$feeds['google-reviews']['enabled'] = false;
				}
			}
			update_option( 'tmf_feeds', $feeds );

			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>Feed settings saved.</p></div>';
			} );
		}

		// --- Save field mappings -----------------------------------------------
		if ( isset( $_POST['tmf_save_mappings'] ) && check_admin_referer( 'tmf_mapping_nonce' ) ) {
			$mappings = array();
			if ( isset( $_POST['tmf_map_field'] ) && is_array( $_POST['tmf_map_field'] ) ) {
				foreach ( $_POST['tmf_map_field'] as $feed_field => $meta_key ) {
					$feed_field = sanitize_key( $feed_field );
					$meta_key   = sanitize_text_field( wp_unslash( $meta_key ) );
					if ( ! empty( $meta_key ) ) {
						$mappings[ $feed_field ] = $meta_key;
					}
				}
			}
			update_option( 'tmf_field_mappings', $mappings );
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>Field mappings saved.</p></div>';
			} );
		}

		// --- Add custom feed ---------------------------------------------------
		if ( isset( $_POST['tmf_add_custom_feed'] ) && check_admin_referer( 'tmf_custom_feed_nonce' ) ) {
			$slug   = sanitize_key( wp_unslash( $_POST['tmf_custom_slug'] ?? '' ) );
			$label  = sanitize_text_field( wp_unslash( $_POST['tmf_custom_label'] ?? '' ) );
			$format = sanitize_key( wp_unslash( $_POST['tmf_custom_format'] ?? 'csv' ) );
			$cols_raw = sanitize_text_field( wp_unslash( $_POST['tmf_custom_columns'] ?? '' ) );

			if ( $slug && $label ) {
				$cols = array_filter( array_map( 'trim', explode( ',', $cols_raw ) ) );
				if ( empty( $cols ) ) {
					$cols = array( 'id', 'sku', 'title', 'description', 'link', 'image_link', 'price', 'sale_price', 'currency', 'availability', 'condition', 'brand', 'gtin', 'mpn', 'category_path', 'weight' );
				}
				$custom          = get_option( 'tmf_custom_feeds', array() );
				$custom[ $slug ] = array(
					'label'   => $label,
					'format'  => $format,
					'columns' => $cols,
					'enabled' => true,
				);
				update_option( 'tmf_custom_feeds', $custom );
				flush_rewrite_rules();
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-success is-dismissible"><p>Custom feed added.</p></div>';
				} );
			}
		}

		// --- Delete custom feed ------------------------------------------------
		if ( isset( $_GET['tmf_delete_feed'] ) && check_admin_referer( 'tmf_delete_feed' ) ) {
			$slug   = sanitize_key( wp_unslash( $_GET['tmf_delete_feed'] ) );
			$custom = get_option( 'tmf_custom_feeds', array() );
			unset( $custom[ $slug ] );
			update_option( 'tmf_custom_feeds', $custom );
			wp_safe_redirect( admin_url( 'admin.php?page=tigon-custom-feeds&deleted=1' ) );
			exit;
		}

		// --- Regenerate secret key ---------------------------------------------
		if ( isset( $_POST['tmf_regenerate_key'] ) && check_admin_referer( 'tmf_settings_nonce' ) ) {
			update_option( 'tmf_feed_secret', wp_generate_password( 24, false ) );
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-warning is-dismissible"><p>Feed secret key regenerated. Update your feed URLs in all marketplaces.</p></div>';
			} );
		}

		// --- Save Google API credentials ---------------------------------------
		if ( isset( $_POST['tmf_save_google_api'] ) && check_admin_referer( 'tmf_google_api_nonce' ) ) {
			if ( isset( $_POST['tmf_google_merchant_id'] ) ) {
				update_option( 'tmf_google_merchant_id', sanitize_text_field( wp_unslash( $_POST['tmf_google_merchant_id'] ) ) );
			}
			if ( isset( $_POST['tmf_google_api_credentials'] ) ) {
				// Store raw JSON (already validated below).
				$json = wp_unslash( $_POST['tmf_google_api_credentials'] ); // phpcs:ignore
				$decoded = json_decode( $json, true );
				if ( $decoded && isset( $decoded['client_email'] ) ) {
					update_option( 'tmf_google_api_credentials', $json );
				} elseif ( ! empty( $json ) ) {
					add_action( 'admin_notices', function () {
						echo '<div class="notice notice-error is-dismissible"><p>Invalid JSON. Must be a Google service account key file.</p></div>';
					} );
				}
			}
			if ( isset( $_POST['tmf_google_sync_frequency'] ) ) {
				$freq = sanitize_key( wp_unslash( $_POST['tmf_google_sync_frequency'] ) );
				TMF_Google_Merchant_API::schedule_sync( $freq );
			}
			// Clear cached token on credential change.
			delete_transient( 'tmf_google_access_token' );

			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>Google API settings saved.</p></div>';
			} );
		}

		// --- Create Google data source -----------------------------------------
		if ( isset( $_POST['tmf_create_data_source'] ) && check_admin_referer( 'tmf_google_api_nonce' ) ) {
			$ds_name    = sanitize_text_field( wp_unslash( $_POST['tmf_ds_name'] ?? 'TIGON Merchant Feeds' ) );
			$ds_country = sanitize_text_field( wp_unslash( $_POST['tmf_ds_country'] ?? 'US' ) );
			$result     = TMF_Google_Merchant_API::create_data_source( $ds_name, $ds_country );
			if ( is_wp_error( $result ) ) {
				$error_msg = $result->get_error_message();
				add_action( 'admin_notices', function () use ( $error_msg ) {
					echo '<div class="notice notice-error is-dismissible"><p>Failed to create data source: ' . esc_html( $error_msg ) . '</p></div>';
				} );
			} else {
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-success is-dismissible"><p>Google API data source created successfully!</p></div>';
				} );
			}
		}

		// --- Trigger manual sync -----------------------------------------------
		if ( isset( $_POST['tmf_trigger_sync'] ) && check_admin_referer( 'tmf_google_api_nonce' ) ) {
			$sync_store = sanitize_key( wp_unslash( $_POST['tmf_sync_store'] ?? '' ) );
			$results    = TMF_Google_Merchant_API::sync_all_products( $sync_store );
			if ( isset( $results['error'] ) ) {
				$err = $results['error'];
				add_action( 'admin_notices', function () use ( $err ) {
					echo '<div class="notice notice-error is-dismissible"><p>Sync failed: ' . esc_html( $err ) . '</p></div>';
				} );
			} else {
				$synced = $results['synced'];
				$total  = $results['total'];
				$errors = $results['errors'];
				add_action( 'admin_notices', function () use ( $synced, $total, $errors ) {
					echo '<div class="notice notice-success is-dismissible"><p>Sync complete: ' . esc_html( $synced ) . '/' . esc_html( $total ) . ' products synced. ' . esc_html( $errors ) . ' errors.</p></div>';
				} );
			}
		}

		// --- Save stores -------------------------------------------------------
		if ( isset( $_POST['tmf_save_stores'] ) && check_admin_referer( 'tmf_stores_nonce' ) ) {
			$store_ids   = isset( $_POST['tmf_store_id'] ) ? (array) $_POST['tmf_store_id'] : array();
			$store_names = isset( $_POST['tmf_store_name'] ) ? (array) $_POST['tmf_store_name'] : array();
			$stores      = array();
			foreach ( $store_ids as $idx => $sid ) {
				$sid   = sanitize_key( $sid );
				$sname = sanitize_text_field( wp_unslash( $store_names[ $idx ] ?? '' ) );
				if ( ! empty( $sid ) && ! empty( $sname ) ) {
					$stores[ $sid ] = $sname;
				}
			}
			update_option( 'tmf_stores', $stores );
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>Stores saved.</p></div>';
			} );
		}

		// --- Add store ---------------------------------------------------------
		if ( isset( $_POST['tmf_add_store'] ) && check_admin_referer( 'tmf_stores_nonce' ) ) {
			$sid   = sanitize_key( wp_unslash( $_POST['tmf_new_store_id'] ?? '' ) );
			$sname = sanitize_text_field( wp_unslash( $_POST['tmf_new_store_name'] ?? '' ) );
			if ( ! empty( $sid ) && ! empty( $sname ) ) {
				$stores = get_option( 'tmf_stores', array() );
				$stores[ $sid ] = $sname;
				update_option( 'tmf_stores', $stores );
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-success is-dismissible"><p>Store added.</p></div>';
				} );
			}
		}
	}

	// =========================================================================
	//  Dashboard page
	// =========================================================================
	public static function page_dashboard() {
		$feeds   = TMF_Tigon_Merchant_Feeds::get_all_feeds();
		$secret  = get_option( 'tmf_feed_secret', '' );
		$product_count = self::get_product_count();
		?>
		<div class="wrap tmf-wrap">
			<?php self::render_header( 'TIGON Merchant Feeds', 'Product Feed Management for Golf Carts' ); ?>

			<div class="tmf-stats-row">
				<div class="tmf-stat-card">
					<span class="tmf-stat-number"><?php echo esc_html( $product_count ); ?></span>
					<span class="tmf-stat-label">Products</span>
				</div>
				<div class="tmf-stat-card">
					<span class="tmf-stat-number"><?php echo count( $feeds ); ?></span>
					<span class="tmf-stat-label">Active Feeds</span>
				</div>
				<div class="tmf-stat-card">
					<span class="tmf-stat-number"><?php echo count( get_option( 'tmf_custom_feeds', array() ) ); ?></span>
					<span class="tmf-stat-label">Custom Feeds</span>
				</div>
			</div>

			<div class="tmf-card">
				<h2>Feed URLs</h2>
				<p>Copy these URLs and submit them to each marketplace. Each URL is secured with your private key.</p>
				<table class="widefat tmf-feed-table">
					<thead>
						<tr>
							<th>Marketplace</th>
							<th>Feed URL</th>
							<th>Format</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $feeds ) ) : ?>
						<tr><td colspan="4">No feeds enabled. <a href="<?php echo esc_url( admin_url( 'admin.php?page=tigon-feed-settings' ) ); ?>">Enable feeds</a>.</td></tr>
					<?php else : ?>
						<?php foreach ( $feeds as $slug => $label ) :
							$url       = TMF_Feed_Endpoint::get_feed_url( $slug );
							$generator = TMF_Feed_Endpoint::get_generator( $slug );
							$format    = $generator ? self::format_label( $generator->content_type() ) : 'N/A';
						?>
						<tr>
							<td><strong><?php echo esc_html( $label ); ?></strong></td>
							<td>
								<input type="text" class="tmf-feed-url-input" value="<?php echo esc_attr( $url ); ?>" readonly>
							</td>
							<td><span class="tmf-badge"><?php echo esc_html( $format ); ?></span></td>
							<td>
								<button type="button" class="button tmf-copy-btn" data-url="<?php echo esc_attr( $url ); ?>">Copy URL</button>
								<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button">Preview</a>
							</td>
						</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<div class="tmf-card tmf-card-muted">
				<h3>Quick Links</h3>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tigon-feed-settings' ) ); ?>" class="button tmf-btn-primary">Feed Settings</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tigon-field-mapping' ) ); ?>" class="button tmf-btn-secondary">Field Mapping</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tigon-custom-feeds' ) ); ?>" class="button tmf-btn-secondary">Custom Feeds</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tigon-google-api' ) ); ?>" class="button tmf-btn-secondary">Google API</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=tigon-stores' ) ); ?>" class="button tmf-btn-secondary">Stores</a>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	//  Settings page
	// =========================================================================
	public static function page_settings() {
		$feeds      = get_option( 'tmf_feeds', array() );
		$google_cat = get_option( 'tmf_google_category', '3101' );
		$google_cat_name = get_option( 'tmf_google_category_name', 'Vehicles & Parts > Vehicles > Golf Carts' );
		$secret     = get_option( 'tmf_feed_secret', '' );
		?>
		<div class="wrap tmf-wrap">
			<?php self::render_header( 'Feed Settings' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'tmf_settings_nonce' ); ?>

				<div class="tmf-card">
					<h2>Enable / Disable Feeds</h2>
					<table class="form-table">
					<?php
					$all_slugs = array(
						'google'         => 'Google Merchant',
						'google-reviews' => 'Google Merchant Reviews',
						'facebook'       => 'Facebook / Meta',
						'amazon'         => 'Amazon',
						'ebay'           => 'eBay',
						'walmart'        => 'Walmart',
						'tiktok'         => 'TikTok Shop',
					);
					foreach ( $all_slugs as $s => $lbl ) :
						$checked = ! empty( $feeds[ $s ]['enabled'] );
					?>
					<tr>
						<th scope="row"><?php echo esc_html( $lbl ); ?></th>
						<td>
							<label class="tmf-toggle">
								<input type="checkbox" name="tmf_feeds_enabled[]" value="<?php echo esc_attr( $s ); ?>" <?php checked( $checked ); ?>>
								<span class="tmf-toggle-slider"></span>
							</label>
						</td>
					</tr>
					<?php endforeach; ?>
					</table>
				</div>

				<div class="tmf-card">
					<h2>Google Product Category</h2>
					<p>All products will be submitted under this Google Product Category. Default is <code>3101</code> — Vehicles &amp; Parts &gt; Vehicles &gt; Golf Carts.</p>
					<table class="form-table">
						<tr>
							<th>Category ID</th>
							<td><input type="text" name="tmf_google_category" value="<?php echo esc_attr( $google_cat ); ?>" class="regular-text"></td>
						</tr>
						<tr>
							<th>Category Name</th>
							<td><input type="text" name="tmf_google_category_name" value="<?php echo esc_attr( $google_cat_name ); ?>" class="regular-text"></td>
						</tr>
					</table>
				</div>

				<div class="tmf-card">
					<h2>Default Shipping</h2>
					<p>Set a default shipping cost to include in the <code>&lt;g:shipping&gt;</code> block of your Google feed. Leave blank to omit shipping from the feed (if configured at the Merchant Center account level).</p>
					<table class="form-table">
						<tr>
							<th>Shipping Cost</th>
							<td><input type="text" name="tmf_default_shipping_cost" value="<?php echo esc_attr( get_option( 'tmf_default_shipping_cost', '' ) ); ?>" class="regular-text" placeholder="e.g. 0.00 for free shipping">
							<p class="description">Enter the amount only (e.g. <code>0.00</code> or <code>199.99</code>). Currency is pulled from WooCommerce.</p></td>
						</tr>
						<tr>
							<th>Service Name</th>
							<td><input type="text" name="tmf_default_shipping_service" value="<?php echo esc_attr( get_option( 'tmf_default_shipping_service', 'Standard' ) ); ?>" class="regular-text" placeholder="Standard"></td>
						</tr>
					</table>
				</div>

				<div class="tmf-card">
					<h2>Feed Security Key</h2>
					<p>Your feeds are protected by a secret key appended to each URL. Only share feed URLs with trusted marketplace platforms.</p>
					<p><code><?php echo esc_html( $secret ); ?></code></p>
					<button type="submit" name="tmf_regenerate_key" class="button tmf-btn-danger">Regenerate Key</button>
				</div>

				<?php submit_button( 'Save Settings', 'primary tmf-btn-primary', 'tmf_save_settings' ); ?>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	//  Field Mapping page
	// =========================================================================
	public static function page_field_mapping() {
		$mappings = get_option( 'tmf_field_mappings', array() );
		$fields   = array(
			// --- Google Required ---
			'title'                     => 'Product Title (g:title)',
			'description'               => 'Description (g:description)',
			'image_link'                => 'Main Image URL (g:image_link)',
			'brand'                     => 'Brand (g:brand)',
			'gtin'                      => 'GTIN / UPC / EAN / ISBN (g:gtin)',
			'mpn'                       => 'MPN (g:mpn)',
			'condition'                 => 'Condition — new, refurbished, used (g:condition)',
			// --- Google Pricing ---
			'price'                     => 'Price (g:price)',
			'regular_price'             => 'Regular Price',
			'sale_price'                => 'Sale Price (g:sale_price)',
			// --- Google Recommended ---
			'short_description'         => 'Short Description / Highlights',
			'color'                     => 'Color (g:color)',
			'size'                      => 'Size (g:size)',
			'material'                  => 'Material (g:material)',
			'pattern'                   => 'Pattern (g:pattern)',
			'weight'                    => 'Shipping Weight (g:shipping_weight)',
			'ads_redirect'              => 'Ads Redirect URL (g:ads_redirect)',
			'energy_efficiency_class'   => 'Energy Efficiency Class (g:energy_efficiency_class)',
			// --- Google Ads Labels ---
			'custom_label_0'            => 'Custom Label 0 (g:custom_label_0)',
			'custom_label_1'            => 'Custom Label 1 (g:custom_label_1)',
			'custom_label_2'            => 'Custom Label 2 (g:custom_label_2)',
			'custom_label_3'            => 'Custom Label 3 (g:custom_label_3)',
			'custom_label_4'            => 'Custom Label 4 (g:custom_label_4)',
		);
		?>
		<div class="wrap tmf-wrap">
			<?php self::render_header( 'Field Mapping', 'Override default WooCommerce field mapping with custom meta keys.' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'tmf_mapping_nonce' ); ?>

				<div class="tmf-card">
					<h2>Map Feed Fields to Custom Meta Keys</h2>
					<p>Leave blank to use the default WooCommerce value. Enter a custom meta key (e.g. <code>_my_brand</code>) to override the default source.</p>
					<table class="widefat tmf-mapping-table">
						<thead>
							<tr>
								<th>Google / Feed Field</th>
								<th>Default WooCommerce Source</th>
								<th>Custom Meta Key Override</th>
							</tr>
						</thead>
						<tbody>
						<?php
						$wc_sources = array(
							'title'                   => 'get_name()',
							'description'             => 'get_description() → get_short_description()',
							'image_link'              => 'get_image_id() → wp_get_attachment_url()',
							'brand'                   => '_brand meta → product_brand taxonomy → Site Name',
							'gtin'                    => 'get_global_unique_id() → _gtin → _global_unique_id → _barcode meta',
							'mpn'                     => '_mpn meta → get_sku() fallback',
							'condition'               => '_condition meta → defaults to "new"',
							'price'                   => 'get_price()',
							'regular_price'           => 'get_regular_price()',
							'sale_price'              => 'get_sale_price()',
							'short_description'       => 'get_short_description()',
							'color'                   => 'Product attributes (Color/Colour) → _color meta',
							'size'                    => 'Product attributes (Size) → _size meta',
							'material'                => 'Product attributes (Material) → _material meta',
							'pattern'                 => 'Product attributes (Pattern)',
							'weight'                  => 'get_weight()',
							'ads_redirect'            => '_ads_redirect meta',
							'energy_efficiency_class' => '_energy_efficiency_class meta',
							'custom_label_0'          => '_custom_label_0 meta',
							'custom_label_1'          => '_custom_label_1 meta',
							'custom_label_2'          => '_custom_label_2 meta',
							'custom_label_3'          => '_custom_label_3 meta',
							'custom_label_4'          => '_custom_label_4 meta',
						);
						foreach ( $fields as $key => $label ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $label ); ?></strong></td>
								<td><code><?php echo esc_html( $wc_sources[ $key ] ?? 'WooCommerce default' ); ?></code></td>
								<td>
									<input type="text" name="tmf_map_field[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $mappings[ $key ] ?? '' ); ?>" placeholder="_custom_meta_key" class="regular-text">
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<div class="tmf-card tmf-card-muted">
					<h3>Automatically Mapped Fields (no override needed)</h3>
					<p>These fields are always pulled from WooCommerce and output automatically in the Google feed:</p>
					<table class="widefat tmf-mapping-table">
						<thead>
							<tr>
								<th>Google Field</th>
								<th>WooCommerce Source</th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>g:id</code></td><td>Product ID — <code>get_id()</code></td></tr>
							<tr><td><code>g:link</code></td><td>Permalink — <code>get_permalink()</code></td></tr>
							<tr><td><code>g:availability</code></td><td>Stock status — <code>get_stock_status()</code> → in_stock / out_of_stock / backorder</td></tr>
							<tr><td><code>g:google_product_category</code></td><td>Feed Settings → defaults to <code>3101</code> (Golf Carts)</td></tr>
							<tr><td><code>g:product_type</code></td><td>WooCommerce category hierarchy path</td></tr>
							<tr><td><code>g:item_group_id</code></td><td>Parent product ID — <code>get_parent_id()</code> (for variations)</td></tr>
							<tr><td><code>g:canonical_link</code></td><td>Parent permalink for variations, own permalink for simple</td></tr>
							<tr><td><code>g:additional_image_link</code></td><td>Gallery images — <code>get_gallery_image_ids()</code> (up to 10)</td></tr>
							<tr><td><code>g:sale_price_effective_date</code></td><td>Sale dates — <code>get_date_on_sale_from()</code> / <code>get_date_on_sale_to()</code></td></tr>
							<tr><td><code>g:shipping_weight</code></td><td>Product weight + unit — <code>get_weight()</code></td></tr>
							<tr><td><code>g:shipping_length/width/height</code></td><td>Product dimensions — <code>get_length/width/height()</code></td></tr>
							<tr><td><code>g:shipping_label</code></td><td>Shipping class — <code>get_shipping_class_id()</code></td></tr>
							<tr><td><code>g:tax / g:tax_category</code></td><td>Tax status + class — <code>get_tax_status()</code> / <code>get_tax_class()</code></td></tr>
							<tr><td><code>g:availability_date</code></td><td><code>_availability_date</code> meta (for backorder/preorder items)</td></tr>
							<tr><td><code>g:product_highlight</code></td><td>Short description split into bullet points</td></tr>
							<tr><td><code>g:product_detail</code></td><td>All product attributes as structured specs</td></tr>
							<tr><td><code>g:identifier_exists</code></td><td>Auto-set to <code>false</code> when no GTIN or MPN is provided</td></tr>
						</tbody>
					</table>
				</div>

				<?php submit_button( 'Save Mappings', 'primary tmf-btn-primary', 'tmf_save_mappings' ); ?>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	//  Custom Feeds page
	// =========================================================================
	public static function page_custom_feeds() {
		$custom = get_option( 'tmf_custom_feeds', array() );
		?>
		<div class="wrap tmf-wrap">
			<?php self::render_header( 'Custom Feeds', 'Create unlimited additional marketplace feeds.' ); ?>

			<?php if ( ! empty( $custom ) ) : ?>
			<div class="tmf-card">
				<h2>Existing Custom Feeds</h2>
				<table class="widefat tmf-feed-table">
					<thead>
						<tr>
							<th>Name</th>
							<th>Slug</th>
							<th>Format</th>
							<th>Feed URL</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $custom as $slug => $cfg ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $cfg['label'] ); ?></strong></td>
							<td><code><?php echo esc_html( $slug ); ?></code></td>
							<td><span class="tmf-badge"><?php echo esc_html( strtoupper( $cfg['format'] ?? 'CSV' ) ); ?></span></td>
							<td>
								<input type="text" class="tmf-feed-url-input" value="<?php echo esc_attr( TMF_Feed_Endpoint::get_feed_url( $slug ) ); ?>" readonly>
							</td>
							<td>
								<button type="button" class="button tmf-copy-btn" data-url="<?php echo esc_attr( TMF_Feed_Endpoint::get_feed_url( $slug ) ); ?>">Copy</button>
								<a href="<?php echo esc_url( TMF_Feed_Endpoint::get_feed_url( $slug ) ); ?>" target="_blank" class="button">Preview</a>
								<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=tigon-custom-feeds&tmf_delete_feed=' . $slug ), 'tmf_delete_feed' ) ); ?>" class="button tmf-btn-danger" onclick="return confirm('Delete this custom feed?');">Delete</a>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php endif; ?>

			<div class="tmf-card">
				<h2>Add New Custom Feed</h2>
				<form method="post">
					<?php wp_nonce_field( 'tmf_custom_feed_nonce' ); ?>
					<table class="form-table">
						<tr>
							<th>Feed Name</th>
							<td><input type="text" name="tmf_custom_label" class="regular-text" placeholder="e.g. Pinterest" required></td>
						</tr>
						<tr>
							<th>Feed Slug</th>
							<td><input type="text" name="tmf_custom_slug" class="regular-text" placeholder="e.g. pinterest" pattern="[a-z0-9_-]+" required>
							<p class="description">Lowercase letters, numbers, hyphens, underscores only.</p></td>
						</tr>
						<tr>
							<th>Format</th>
							<td>
								<select name="tmf_custom_format">
									<option value="csv">CSV</option>
									<option value="xml">XML</option>
									<option value="tsv">TSV (Tab-separated)</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>Columns</th>
							<td>
								<textarea name="tmf_custom_columns" class="large-text" rows="3" placeholder="id,sku,title,description,link,image_link,price,sale_price,currency,availability,condition,brand,gtin,mpn,category_path,weight"></textarea>
								<p class="description">Comma-separated list of field names. Leave blank for all default fields.<br>Available: id, sku, title, description, short_description, link, image_link, price, regular_price, sale_price, currency, availability, stock_quantity, condition, brand, gtin, mpn, category_path, weight, weight_unit, length, width, height, dimension_unit, product_type</p>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Add Custom Feed', 'primary tmf-btn-primary', 'tmf_add_custom_feed' ); ?>
				</form>
			</div>
		</div>
		<?php
	}

	// =========================================================================
	//  Google API page
	// =========================================================================
	public static function page_google_api() {
		$merchant_id = get_option( 'tmf_google_merchant_id', '' );
		$credentials = get_option( 'tmf_google_api_credentials', '' );
		$data_source = get_option( 'tmf_google_data_source', '' );
		$ds_display  = get_option( 'tmf_google_data_source_display', '' );
		$frequency   = get_option( 'tmf_google_sync_frequency', 'disabled' );
		$last_sync   = get_option( 'tmf_google_last_sync_results', array() );
		$stores      = TMF_Google_Merchant_API::get_stores();

		$has_creds = false;
		if ( ! empty( $credentials ) ) {
			$decoded   = json_decode( $credentials, true );
			$has_creds = ! empty( $decoded['client_email'] );
		}
		?>
		<div class="wrap tmf-wrap">
			<?php self::render_header( 'Google Merchant API', 'Push products directly to Google Merchant Center via the Merchant API.' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'tmf_google_api_nonce' ); ?>

				<div class="tmf-card">
					<h2>API Credentials</h2>
					<table class="form-table">
						<tr>
							<th>Merchant Center Account ID</th>
							<td>
								<input type="text" name="tmf_google_merchant_id" value="<?php echo esc_attr( $merchant_id ); ?>" class="regular-text" placeholder="e.g. 123456789">
								<p class="description">Your Google Merchant Center account number.</p>
							</td>
						</tr>
						<tr>
							<th>Service Account JSON Key</th>
							<td>
								<textarea name="tmf_google_api_credentials" class="large-text" rows="6" placeholder='Paste the entire contents of your service account .json key file here...'><?php echo esc_textarea( $credentials ); ?></textarea>
								<p class="description">
									Create a service account in <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">Google Cloud Console</a>,
									enable the <strong>Merchant API</strong>, and download the JSON key file.
									Grant the service account access to your Merchant Center account.
								</p>
								<?php if ( $has_creds ) : ?>
									<p><span class="tmf-badge" style="background:#28a745;">Connected</span> <code><?php echo esc_html( $decoded['client_email'] ); ?></code></p>
								<?php endif; ?>
							</td>
						</tr>
					</table>
					<?php submit_button( 'Save API Settings', 'primary tmf-btn-primary', 'tmf_save_google_api' ); ?>
				</div>

				<div class="tmf-card">
					<h2>API Data Source</h2>
					<p>Google requires an API data source before products can be pushed. Create one or use an existing data source ID.</p>
					<?php if ( ! empty( $data_source ) ) : ?>
						<p><strong>Active Data Source:</strong> <code><?php echo esc_html( $data_source ); ?></code>
						<?php if ( ! empty( $ds_display ) ) : ?> — <?php echo esc_html( $ds_display ); ?><?php endif; ?></p>
					<?php else : ?>
						<p><em>No data source configured.</em></p>
					<?php endif; ?>
					<table class="form-table">
						<tr>
							<th>Data Source Name</th>
							<td><input type="text" name="tmf_ds_name" value="TIGON Merchant Feeds" class="regular-text"></td>
						</tr>
						<tr>
							<th>Target Country</th>
							<td><input type="text" name="tmf_ds_country" value="US" class="small-text"></td>
						</tr>
					</table>
					<button type="submit" name="tmf_create_data_source" class="button tmf-btn-secondary">Create API Data Source</button>
				</div>

				<div class="tmf-card">
					<h2>Automatic Sync Schedule</h2>
					<p>Automatically push all WooCommerce products to Google Merchant Center on a recurring schedule.</p>
					<table class="form-table">
						<tr>
							<th>Sync Frequency</th>
							<td>
								<select name="tmf_google_sync_frequency">
									<option value="disabled" <?php selected( $frequency, 'disabled' ); ?>>Disabled</option>
									<option value="tmf_every_6_hours" <?php selected( $frequency, 'tmf_every_6_hours' ); ?>>Every 6 Hours</option>
									<option value="tmf_every_12_hours" <?php selected( $frequency, 'tmf_every_12_hours' ); ?>>Every 12 Hours</option>
									<option value="daily" <?php selected( $frequency, 'daily' ); ?>>Daily</option>
									<option value="twicedaily" <?php selected( $frequency, 'twicedaily' ); ?>>Twice Daily</option>
								</select>
							</td>
						</tr>
					</table>
					<?php
					$next = wp_next_scheduled( 'tmf_google_api_sync' );
					if ( $next ) : ?>
						<p>Next scheduled sync: <strong><?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', $next ), 'M j, Y g:i A' ) ); ?></strong></p>
					<?php endif; ?>
				</div>

				<div class="tmf-card">
					<h2>Manual Sync</h2>
					<p>Push all products to Google Merchant Center now.</p>
					<table class="form-table">
						<tr>
							<th>Filter by Store</th>
							<td>
								<select name="tmf_sync_store">
									<option value="">All Stores</option>
									<?php foreach ( $stores as $sid => $sname ) : ?>
										<option value="<?php echo esc_attr( $sid ); ?>"><?php echo esc_html( $sname ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					</table>
					<button type="submit" name="tmf_trigger_sync" class="button tmf-btn-primary">Sync Now</button>

					<?php if ( ! empty( $last_sync ) && isset( $last_sync['total'] ) ) : ?>
						<div style="margin-top:16px; padding:12px; background:#f7f9fa; border:1px solid #BCC6CC; border-radius:4px;">
							<strong>Last Sync Results:</strong><br>
							Store: <?php echo esc_html( $last_sync['store'] ?? 'all' ); ?><br>
							Products: <?php echo esc_html( $last_sync['synced'] ); ?>/<?php echo esc_html( $last_sync['total'] ); ?> synced<br>
							Errors: <?php echo esc_html( $last_sync['errors'] ); ?><br>
							Started: <?php echo esc_html( $last_sync['started'] ?? '' ); ?><br>
							Finished: <?php echo esc_html( $last_sync['finished'] ?? '' ); ?>
							<?php if ( ! empty( $last_sync['error_log'] ) ) : ?>
								<details style="margin-top:8px;">
									<summary>Error Details (<?php echo count( $last_sync['error_log'] ); ?>)</summary>
									<ul style="margin-top:4px;">
										<?php foreach ( array_slice( $last_sync['error_log'], 0, 20 ) as $err ) : ?>
											<li>Product #<?php echo esc_html( $err['product_id'] ); ?> (<?php echo esc_html( $err['sku'] ); ?>): <?php echo esc_html( $err['error'] ); ?></li>
										<?php endforeach; ?>
									</ul>
								</details>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	//  Stores page
	// =========================================================================
	public static function page_stores() {
		$stores = get_option( 'tmf_stores', array() );
		?>
		<div class="wrap tmf-wrap">
			<?php self::render_header( 'Store Management', 'Manage stores/locations for product sorting and per-store Google syncing.' ); ?>

			<form method="post">
				<?php wp_nonce_field( 'tmf_stores_nonce' ); ?>

				<?php if ( ! empty( $stores ) ) : ?>
				<div class="tmf-card">
					<h2>Your Stores</h2>
					<p>Products can be assigned to stores via the <code>_tmf_store</code> custom field on each product.</p>
					<table class="widefat tmf-feed-table">
						<thead>
							<tr>
								<th>Store ID</th>
								<th>Store Name</th>
								<th>Products</th>
							</tr>
						</thead>
						<tbody>
						<?php
						$by_store = TMF_Google_Merchant_API::get_products_by_store();
						foreach ( $stores as $sid => $sname ) :
							$count = isset( $by_store[ $sid ] ) ? $by_store[ $sid ]['count'] : 0;
						?>
							<tr>
								<td>
									<input type="text" name="tmf_store_id[]" value="<?php echo esc_attr( $sid ); ?>" class="regular-text">
								</td>
								<td>
									<input type="text" name="tmf_store_name[]" value="<?php echo esc_attr( $sname ); ?>" class="regular-text">
								</td>
								<td><?php echo esc_html( $count ); ?></td>
							</tr>
						<?php endforeach; ?>
						<?php if ( isset( $by_store['_unassigned'] ) ) : ?>
							<tr>
								<td><em>—</em></td>
								<td><em>Unassigned</em></td>
								<td><?php echo esc_html( $by_store['_unassigned']['count'] ); ?></td>
							</tr>
						<?php endif; ?>
						</tbody>
					</table>
					<?php submit_button( 'Save Stores', 'primary tmf-btn-primary', 'tmf_save_stores' ); ?>
				</div>
				<?php endif; ?>

				<div class="tmf-card">
					<h2>Add New Store</h2>
					<table class="form-table">
						<tr>
							<th>Store ID</th>
							<td><input type="text" name="tmf_new_store_id" class="regular-text" placeholder="e.g. store-tampa" pattern="[a-z0-9_-]+">
							<p class="description">Lowercase, no spaces. Used as the store code for Google Merchant.</p></td>
						</tr>
						<tr>
							<th>Store Name</th>
							<td><input type="text" name="tmf_new_store_name" class="regular-text" placeholder="e.g. TIGON Tampa"></td>
						</tr>
					</table>
					<?php submit_button( 'Add Store', 'secondary tmf-btn-secondary', 'tmf_add_store' ); ?>
				</div>

				<div class="tmf-card tmf-card-muted">
					<h3>How to Assign Products to Stores</h3>
					<p>Add a custom field named <code>_tmf_store</code> to any WooCommerce product with the store ID as the value. Products without a store assignment will sync under "All Stores".</p>
					<p>You can bulk-assign stores using any WooCommerce bulk edit tool or via the product edit screen.</p>
				</div>
			</form>
		</div>
		<?php
	}

	// =========================================================================
	//  Helpers
	// =========================================================================

	/**
	 * Render the branded page header with TIGON logo.
	 */
	private static function render_header( $title, $subtitle = '' ) {
		$logo_url = TMF_PLUGIN_URL . 'admin/img/NEW GOLF CART (4).ico';
		?>
		<div class="tmf-header">
			<div class="tmf-header-logo">
				<img src="<?php echo esc_url( $logo_url ); ?>" alt="TIGON">
			</div>
			<div class="tmf-header-text">
				<h1><?php echo esc_html( $title ); ?></h1>
				<?php if ( $subtitle ) : ?>
					<p class="tmf-subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<?php
	}

	private static function get_product_count() {
		$counts = wp_count_posts( 'product' );
		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}

	private static function format_label( $content_type ) {
		if ( false !== strpos( $content_type, 'xml' ) ) {
			return 'XML';
		}
		if ( false !== strpos( $content_type, 'tab-separated' ) ) {
			return 'TSV';
		}
		return 'CSV';
	}
}
