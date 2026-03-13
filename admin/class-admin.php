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
		add_menu_page(
			'TIGON Merchant Feeds',
			'TIGON Feeds',
			'manage_woocommerce',
			'tigon-merchant-feeds',
			array( __CLASS__, 'page_dashboard' ),
			'dashicons-rss',
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
			$slugs  = array( 'google', 'facebook', 'amazon', 'ebay', 'walmart', 'tiktok' );
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
			<div class="tmf-header">
				<h1>TIGON Merchant Feeds</h1>
				<p class="tmf-subtitle">Product Feed Management for Golf Carts</p>
			</div>

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
			<div class="tmf-header">
				<h1>Feed Settings</h1>
			</div>

			<form method="post">
				<?php wp_nonce_field( 'tmf_settings_nonce' ); ?>

				<div class="tmf-card">
					<h2>Enable / Disable Feeds</h2>
					<table class="form-table">
					<?php
					$all_slugs = array(
						'google'   => 'Google Merchant',
						'facebook' => 'Facebook / Meta',
						'amazon'   => 'Amazon',
						'ebay'     => 'eBay',
						'walmart'  => 'Walmart',
						'tiktok'   => 'TikTok Shop',
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
			<div class="tmf-header">
				<h1>Field Mapping</h1>
				<p class="tmf-subtitle">Override default WooCommerce field mapping with custom meta keys.</p>
			</div>

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
			<div class="tmf-header">
				<h1>Custom Feeds</h1>
				<p class="tmf-subtitle">Create unlimited additional marketplace feeds.</p>
			</div>

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
	//  Helpers
	// =========================================================================

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
