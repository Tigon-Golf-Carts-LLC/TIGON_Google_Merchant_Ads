<?php
/**
 * Field Mapper — maps WooCommerce product data to feed fields.
 *
 * Covers every Google Merchant Center required, recommended, and optional field
 * plus fields needed by other marketplaces.
 *
 * WooCommerce sources used:
 *   get_id()                → id
 *   get_parent_id()         → parent_id / item_group_id
 *   get_name()              → title
 *   get_description()       → description
 *   get_short_description() → short_description / product_highlight
 *   get_permalink()         → link / canonical_link
 *   get_image_id()          → image_link
 *   get_gallery_image_ids() → additional_image_links
 *   get_price()             → price
 *   get_regular_price()     → regular_price (→ g:price)
 *   get_sale_price()        → sale_price (→ g:sale_price)
 *   get_date_on_sale_from() → sale_price_effective_date_from
 *   get_date_on_sale_to()   → sale_price_effective_date_to
 *   get_stock_status()      → availability
 *   get_stock_quantity()     → stock_quantity
 *   get_backorders()        → backorders
 *   get_sku()               → sku / mpn fallback
 *   get_weight()            → weight / shipping_weight
 *   get_length/width/height()→ dimensions / shipping dimensions
 *   get_category_ids()      → categories / product_type
 *   get_tag_ids()           → tags
 *   get_type()              → product_type (simple/variable/etc.)
 *   get_attributes()        → color, size, material, pattern, etc.
 *   get_global_unique_id()  → gtin (WC 8.4+ native)
 *   get_meta('_gtin')       → gtin (legacy)
 *   get_meta('_global_unique_id') → gtin (fallback)
 *   get_meta('_mpn')        → mpn
 *   get_meta('_brand')      → brand
 *   get_meta('_condition')  → condition
 *   get_tax_status()        → tax info
 *   get_tax_class()         → tax_category
 *   get_shipping_class_id() → shipping_label
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Field_Mapper {

	/**
	 * Map a WC_Product to a normalized associative array.
	 *
	 * @param WC_Product $product WooCommerce product object.
	 * @return array Mapped product data.
	 */
	public static function map( WC_Product $product ) {

		// =====================================================================
		//  Images
		// =====================================================================
		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';

		// For variations with no image, inherit parent image.
		if ( empty( $image_url ) && $product->get_parent_id() ) {
			$parent    = wc_get_product( $product->get_parent_id() );
			$image_id  = $parent ? $parent->get_image_id() : 0;
			$image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';
		}

		$gallery = array();
		$gallery_ids = $product->get_gallery_image_ids();
		// Variations: inherit parent gallery if empty.
		if ( empty( $gallery_ids ) && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$gallery_ids = $parent->get_gallery_image_ids();
			}
		}
		foreach ( $gallery_ids as $gid ) {
			$url = wp_get_attachment_url( $gid );
			if ( $url ) {
				$gallery[] = $url;
			}
		}

		// =====================================================================
		//  Categories
		// =====================================================================
		$categories   = array();
		$category_ids = $product->get_category_ids();
		// Variations: inherit parent categories.
		if ( empty( $category_ids ) && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$category_ids = $parent->get_category_ids();
			}
		}
		foreach ( $category_ids as $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$categories[] = $term->name;
			}
		}

		// Full category hierarchy path (e.g. "Golf Carts > Electric").
		$category_hierarchy = self::get_full_category_path( $category_ids );

		// =====================================================================
		//  Pricing
		// =====================================================================
		$regular_price = $product->get_regular_price();
		$sale_price    = $product->get_sale_price();
		$price         = $product->get_price();
		$currency      = get_woocommerce_currency();

		// Sale price effective dates (WooCommerce stores as WC_DateTime objects).
		$sale_from = $product->get_date_on_sale_from();
		$sale_to   = $product->get_date_on_sale_to();
		$sale_price_effective_date = '';
		if ( $sale_from || $sale_to ) {
			$from_str = $sale_from ? $sale_from->date( 'Y-m-d\TH:i:sO' ) : '';
			$to_str   = $sale_to   ? $sale_to->date( 'Y-m-d\TH:i:sO' )   : '';
			if ( $from_str && $to_str ) {
				$sale_price_effective_date = $from_str . '/' . $to_str;
			} elseif ( $from_str ) {
				$sale_price_effective_date = $from_str . '/';
			} elseif ( $to_str ) {
				$sale_price_effective_date = '/' . $to_str;
			}
		}

		// =====================================================================
		//  Weight & Dimensions
		// =====================================================================
		$weight      = $product->get_weight();
		$length      = $product->get_length();
		$width       = $product->get_width();
		$height      = $product->get_height();
		$weight_unit = get_option( 'woocommerce_weight_unit', 'lbs' );
		$dim_unit    = get_option( 'woocommerce_dimension_unit', 'in' );

		// =====================================================================
		//  Stock & Availability
		// =====================================================================
		$stock_status = $product->get_stock_status();
		$stock_qty    = $product->get_stock_quantity();
		$backorders   = $product->get_backorders(); // "no", "notify", "yes"
		$availability = self::map_availability( $stock_status );

		// Availability date for backorder/preorder items.
		$availability_date = $product->get_meta( '_availability_date', true );

		// =====================================================================
		//  Identifiers: GTIN, MPN, Brand
		// =====================================================================
		$sku = $product->get_sku();

		// GTIN — try WC 8.4+ native method first, then meta keys.
		$gtin = '';
		if ( method_exists( $product, 'get_global_unique_id' ) ) {
			$gtin = $product->get_global_unique_id();
		}
		if ( empty( $gtin ) ) {
			$gtin = $product->get_meta( '_gtin', true );
		}
		if ( empty( $gtin ) ) {
			$gtin = $product->get_meta( '_global_unique_id', true );
		}
		if ( empty( $gtin ) ) {
			$gtin = $product->get_meta( 'gtin', true );
		}
		if ( empty( $gtin ) ) {
			$gtin = $product->get_meta( '_barcode', true );
		}

		// MPN — try meta, fall back to SKU.
		$mpn = $product->get_meta( '_mpn', true );
		if ( empty( $mpn ) ) {
			$mpn = $product->get_meta( 'mpn', true );
		}
		if ( empty( $mpn ) && ! empty( $sku ) ) {
			$mpn = $sku;
		}

		// Brand — try manufacturer/brand meta, then taxonomy.
		// For vehicles the brand is the manufacturer, NOT the site name.
		$brand = $product->get_meta( '_brand', true );
		if ( empty( $brand ) ) {
			$brand = $product->get_meta( 'brand', true );
		}
		if ( empty( $brand ) ) {
			$brand = $product->get_meta( '_manufacturer', true );
		}
		if ( empty( $brand ) ) {
			$brand = $product->get_meta( 'manufacturer', true );
		}
		if ( empty( $brand ) ) {
			// Check for a "brand" taxonomy (used by some plugins like YITH, WooCommerce Brands).
			$brand_terms = get_the_terms( $product->get_id(), 'product_brand' );
			if ( ! $brand_terms || is_wp_error( $brand_terms ) ) {
				$brand_terms = get_the_terms( $product->get_id(), 'pwb-brand' ); // Perfect WooCommerce Brands
			}
			if ( $brand_terms && ! is_wp_error( $brand_terms ) ) {
				$brand = $brand_terms[0]->name;
			}
		}
		// Try "Manufacturer" or "Brand" product attributes.
		if ( empty( $brand ) ) {
			$brand = self::get_attribute_value( $product, array( 'manufacturer', 'brand', 'make' ) );
		}

		// =====================================================================
		//  Descriptions — ensure never empty (Google rejects empty)
		// =====================================================================
		$short_desc = $product->get_short_description();
		$full_desc  = $product->get_description();

		// Variations: inherit parent descriptions if empty.
		if ( $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				if ( empty( $full_desc ) ) {
					$full_desc = $parent->get_description();
				}
				if ( empty( $short_desc ) ) {
					$short_desc = $parent->get_short_description();
				}
			}
		}

		$desc       = ! empty( $full_desc ) ? $full_desc : $short_desc;
		$desc       = wp_strip_all_tags( $desc );
		$short_desc = wp_strip_all_tags( $short_desc );

		// Last resort: use title as description (Google requires non-empty).
		if ( empty( $desc ) ) {
			$desc = $product->get_name();
		}

		// Product highlights — split short description into bullet points.
		$highlights = array();
		if ( ! empty( $short_desc ) ) {
			// If short desc has line breaks or list items, split into highlights.
			$lines = preg_split( '/[\r\n]+/', $short_desc );
			foreach ( $lines as $line ) {
				$line = trim( wp_strip_all_tags( $line ) );
				$line = ltrim( $line, '•-*– ' );
				if ( ! empty( $line ) ) {
					$highlights[] = $line;
				}
			}
		}

		// =====================================================================
		//  Condition
		// =====================================================================
		$condition = $product->get_meta( '_condition', true );
		if ( empty( $condition ) ) {
			$condition = 'new';
		}
		// Normalize to Google's accepted values.
		$condition = strtolower( $condition );
		if ( ! in_array( $condition, array( 'new', 'refurbished', 'used' ), true ) ) {
			$condition = 'new';
		}

		// =====================================================================
		//  Attributes — both global and variation-specific
		// =====================================================================
		$attributes      = array();
		$color           = '';
		$size            = '';
		$material        = '';
		$pattern         = '';
		$product_details = array(); // For g:product_detail

		if ( $product->is_type( 'variation' ) ) {
			// Variation-specific attributes.
			foreach ( $product->get_variation_attributes() as $attr_key => $attr_val ) {
				$clean_key = str_replace( 'attribute_', '', $attr_key );
				$attr_label = wc_attribute_label( $clean_key, $product );
				$attributes[ $attr_label ] = $attr_val;
			}
			// Also pull parent's global attributes for product_detail.
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				self::extract_global_attributes( $parent, $product_details );
			}
		} else {
			// Simple/other — read global attributes.
			self::extract_global_attributes( $product, $product_details );
			// Also add to $attributes for variant-style reading.
			foreach ( $product->get_attributes() as $attr ) {
				if ( is_a( $attr, 'WC_Product_Attribute' ) ) {
					$label = wc_attribute_label( $attr->get_name() );
					$values = $attr->get_options();
					if ( $attr->is_taxonomy() ) {
						$term_names = array();
						foreach ( $values as $term_id ) {
							$term = get_term( $term_id );
							if ( $term && ! is_wp_error( $term ) ) {
								$term_names[] = $term->name;
							}
						}
						$attributes[ $label ] = implode( ', ', $term_names );
					} else {
						$attributes[ $label ] = implode( ', ', $values );
					}
				}
			}
		}

		// Extract known Google attributes from the attributes array.
		$manufacturer = '';
		$model        = '';

		foreach ( $attributes as $label => $val ) {
			$lower = strtolower( $label );
			if ( in_array( $lower, array( 'color', 'colour' ), true ) && empty( $color ) ) {
				$color = $val;
			} elseif ( $lower === 'size' && empty( $size ) ) {
				$size = $val;
			} elseif ( $lower === 'material' && empty( $material ) ) {
				$material = $val;
			} elseif ( $lower === 'pattern' && empty( $pattern ) ) {
				$pattern = $val;
			} elseif ( in_array( $lower, array( 'manufacturer', 'make', 'brand' ), true ) && empty( $manufacturer ) ) {
				$manufacturer = $val;
			} elseif ( $lower === 'model' && empty( $model ) ) {
				$model = $val;
			}
		}

		// Also check meta for color/size/material if not found in attributes.
		if ( empty( $color ) ) {
			$color = $product->get_meta( '_color', true );
		}
		if ( empty( $color ) ) {
			$color = $product->get_meta( 'color', true );
		}
		if ( empty( $size ) ) {
			$size = $product->get_meta( '_size', true );
		}
		if ( empty( $material ) ) {
			$material = $product->get_meta( '_material', true );
		}
		if ( empty( $manufacturer ) ) {
			$manufacturer = $product->get_meta( '_manufacturer', true );
		}
		if ( empty( $manufacturer ) ) {
			$manufacturer = $product->get_meta( 'manufacturer', true );
		}
		if ( empty( $model ) ) {
			$model = $product->get_meta( '_model', true );
		}
		if ( empty( $model ) ) {
			$model = $product->get_meta( 'model', true );
		}

		// =====================================================================
		//  Tax
		// =====================================================================
		$tax_status   = $product->get_tax_status();   // "taxable", "shipping", "none"
		$tax_class    = $product->get_tax_class();     // "", "reduced-rate", "zero-rate", etc.

		// =====================================================================
		//  Shipping class
		// =====================================================================
		$shipping_class_id = $product->get_shipping_class_id();
		$shipping_label    = '';
		if ( $shipping_class_id ) {
			$term = get_term( $shipping_class_id, 'product_shipping_class' );
			if ( $term && ! is_wp_error( $term ) ) {
				$shipping_label = $term->name;
			}
		}

		// =====================================================================
		//  Store location
		// =====================================================================
		$store_location = $product->get_meta( '_tmf_store', true );
		if ( empty( $store_location ) ) {
			$store_location = $product->get_meta( '_store_location', true );
		}
		if ( empty( $store_location ) ) {
			$store_location = $product->get_meta( 'store_location', true );
		}
		if ( empty( $store_location ) ) {
			$store_location = self::get_attribute_value( $product, array( 'store location', 'store', 'location', 'dealer location' ) );
		}

		// =====================================================================
		//  Custom labels (for Google Ads campaign segmentation)
		//  0 = Manufacturer, 1 = Model, 2 = Store Location,
		//  3 = Color, 4 = New or Used
		// =====================================================================
		$custom_labels = array();
		for ( $i = 0; $i <= 4; $i++ ) {
			$val = $product->get_meta( '_custom_label_' . $i, true );
			$custom_labels[ $i ] = ! empty( $val ) ? $val : '';
		}

		// Auto-fill custom labels from product data when not manually set.
		if ( empty( $custom_labels[0] ) && ! empty( $manufacturer ) ) {
			$custom_labels[0] = $manufacturer;
		}
		if ( empty( $custom_labels[0] ) && ! empty( $brand ) ) {
			$custom_labels[0] = $brand;
		}
		if ( empty( $custom_labels[1] ) && ! empty( $model ) ) {
			$custom_labels[1] = $model;
		}
		if ( empty( $custom_labels[2] ) && ! empty( $store_location ) ) {
			$custom_labels[2] = $store_location;
		}
		if ( empty( $custom_labels[3] ) && ! empty( $color ) ) {
			$custom_labels[3] = $color;
		}
		if ( empty( $custom_labels[4] ) ) {
			$custom_labels[4] = ucfirst( $condition );
		}

		// =====================================================================
		//  Canonical link
		// =====================================================================
		$canonical_link = $product->get_permalink();
		if ( $product->get_parent_id() ) {
			// For variations, canonical should point to the parent.
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$canonical_link = $parent->get_permalink();
			}
		}

		// =====================================================================
		//  Ads redirect
		// =====================================================================
		$ads_redirect = $product->get_meta( '_ads_redirect', true );

		// =====================================================================
		//  Energy efficiency (relevant for electric golf carts)
		// =====================================================================
		$energy_efficiency = $product->get_meta( '_energy_efficiency_class', true );

		// =====================================================================
		//  Build final data array
		// =====================================================================
		$data = array(
			// --- Required by Google ---
			'id'                        => $product->get_id(),
			'title'                     => $product->get_name(),
			'description'               => $desc,
			'link'                      => $product->get_permalink(),
			'image_link'                => $image_url,
			'availability'              => $availability,
			'price'                     => $price,
			'regular_price'             => $regular_price,
			'sale_price'                => $sale_price,
			'currency'                  => $currency,
			'brand'                     => $brand,
			'condition'                 => $condition,

			// --- Required for product identification ---
			'gtin'                      => $gtin,
			'mpn'                       => $mpn,
			'sku'                       => $sku,
			'identifier_exists'         => ( ! empty( $gtin ) || ! empty( $mpn ) ),

			// --- Recommended by Google ---
			'sale_price_effective_date' => $sale_price_effective_date,
			'additional_image_links'    => $gallery,
			'google_product_category'   => get_option( 'tmf_google_category', '3931' ),
			'google_product_category_name' => get_option( 'tmf_google_category_name', 'Vehicles & Parts > Vehicles > Motor Vehicles > Golf Carts' ),
			'product_type'              => $product->get_type(),
			'category_path'             => ! empty( $category_hierarchy ) ? $category_hierarchy : implode( ' > ', $categories ),
			'canonical_link'            => $canonical_link,
			'product_highlight'         => $highlights,
			'product_detail'            => $product_details,

			// --- Variant support ---
			'parent_id'                 => $product->get_parent_id(),
			'item_group_id'             => $product->get_parent_id() ?: '',
			'attributes'                => $attributes,
			'color'                     => $color,
			'size'                      => $size,
			'material'                  => $material,
			'pattern'                   => $pattern,
			'manufacturer'              => $manufacturer,
			'model'                     => $model,
			'store_location'            => $store_location,

			// --- Shipping & dimensions ---
			'weight'                    => $weight,
			'weight_unit'               => $weight_unit,
			'length'                    => $length,
			'width'                     => $width,
			'height'                    => $height,
			'dimension_unit'            => $dim_unit,
			'shipping_label'            => $shipping_label,

			// --- Tax ---
			'tax_status'                => $tax_status,
			'tax_class'                 => $tax_class,

			// --- Stock ---
			'stock_quantity'            => $stock_qty,
			'availability_date'         => $availability_date,

			// --- Google Ads ---
			'custom_label_0'            => $custom_labels[0],
			'custom_label_1'            => $custom_labels[1],
			'custom_label_2'            => $custom_labels[2],
			'custom_label_3'            => $custom_labels[3],
			'custom_label_4'            => $custom_labels[4],
			'ads_redirect'              => $ads_redirect,

			// --- Energy efficiency (electric golf carts) ---
			'energy_efficiency_class'   => $energy_efficiency,

			// --- Other ---
			'short_description'         => $short_desc,
			'categories'                => $categories,
			'tags'                      => self::get_tag_names( $product ),
		);

		// Allow per-product overrides via custom field mappings saved in options.
		$overrides = get_option( 'tmf_field_mappings', array() );
		if ( is_array( $overrides ) ) {
			foreach ( $overrides as $feed_field => $meta_key ) {
				if ( ! empty( $meta_key ) ) {
					$meta_val = $product->get_meta( $meta_key, true );
					if ( '' !== $meta_val ) {
						$data[ $feed_field ] = $meta_val;
					}
				}
			}
		}

		return apply_filters( 'tmf_mapped_product', $data, $product );
	}

	/**
	 * Convert WC stock status to Google-style availability.
	 */
	public static function map_availability( $status ) {
		switch ( $status ) {
			case 'instock':
				return 'in stock';
			case 'outofstock':
				return 'out of stock';
			case 'onbackorder':
				return 'backorder';
			default:
				return 'in stock';
		}
	}

	/**
	 * Map availability to Google Merchant API enum format (uppercase).
	 */
	public static function map_availability_api( $status ) {
		switch ( $status ) {
			case 'instock':
				return 'IN_STOCK';
			case 'outofstock':
				return 'OUT_OF_STOCK';
			case 'onbackorder':
				return 'BACKORDER';
			default:
				return 'IN_STOCK';
		}
	}

	/**
	 * Extract global (non-variation) attributes into product_detail format.
	 *
	 * Google product_detail format: array of [section_name, attribute_name, attribute_value].
	 */
	private static function extract_global_attributes( WC_Product $product, &$details ) {
		foreach ( $product->get_attributes() as $attr ) {
			if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
				continue;
			}
			$label   = wc_attribute_label( $attr->get_name() );
			$options = $attr->get_options();
			$values  = array();

			if ( $attr->is_taxonomy() ) {
				foreach ( $options as $term_id ) {
					$term = get_term( $term_id );
					if ( $term && ! is_wp_error( $term ) ) {
						$values[] = $term->name;
					}
				}
			} else {
				$values = $options;
			}

			if ( ! empty( $values ) ) {
				$details[] = array(
					'section_name'    => 'Specifications',
					'attribute_name'  => $label,
					'attribute_value' => implode( ', ', $values ),
				);
			}
		}
	}

	/**
	 * Get the full hierarchical category path for the deepest category.
	 *
	 * E.g. "Golf Carts > Electric > 4-Seater"
	 */
	private static function get_full_category_path( $category_ids ) {
		if ( empty( $category_ids ) ) {
			return '';
		}

		$deepest      = null;
		$deepest_depth = -1;

		foreach ( $category_ids as $cat_id ) {
			$ancestors = get_ancestors( $cat_id, 'product_cat', 'taxonomy' );
			$depth     = count( $ancestors );
			if ( $depth > $deepest_depth ) {
				$deepest_depth = $depth;
				$deepest       = $cat_id;
			}
		}

		if ( ! $deepest ) {
			$deepest = $category_ids[0];
		}

		$ancestors = get_ancestors( $deepest, 'product_cat', 'taxonomy' );
		$ancestors = array_reverse( $ancestors );
		$ancestors[] = $deepest;

		$path_parts = array();
		foreach ( $ancestors as $anc_id ) {
			$term = get_term( $anc_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$path_parts[] = $term->name;
			}
		}

		return implode( ' > ', $path_parts );
	}

	/**
	 * Search product attributes for a value by label (case-insensitive).
	 *
	 * @param WC_Product $product  Product to search.
	 * @param array      $needles  Lowercase attribute label names to match.
	 * @return string Attribute value or empty string.
	 */
	private static function get_attribute_value( WC_Product $product, array $needles ) {
		$source = $product;
		// For variations, also check parent attributes.
		if ( $product->is_type( 'variation' ) && $product->get_parent_id() ) {
			$parent = wc_get_product( $product->get_parent_id() );
			if ( $parent ) {
				$source = $parent;
			}
		}

		foreach ( $source->get_attributes() as $attr ) {
			if ( ! is_a( $attr, 'WC_Product_Attribute' ) ) {
				continue;
			}
			$label = strtolower( wc_attribute_label( $attr->get_name() ) );
			if ( ! in_array( $label, $needles, true ) ) {
				continue;
			}
			$options = $attr->get_options();
			if ( $attr->is_taxonomy() ) {
				$names = array();
				foreach ( $options as $term_id ) {
					$term = get_term( $term_id );
					if ( $term && ! is_wp_error( $term ) ) {
						$names[] = $term->name;
					}
				}
				return implode( ', ', $names );
			}
			return implode( ', ', $options );
		}

		return '';
	}

	/**
	 * Get product tag names.
	 */
	private static function get_tag_names( WC_Product $product ) {
		$tags  = array();
		$terms = get_the_terms( $product->get_id(), 'product_tag' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$tags[] = $term->name;
			}
		}
		return $tags;
	}
}
