<?php
/**
 * Field Mapper — maps WooCommerce product data to feed fields.
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
		$image_id  = $product->get_image_id();
		$image_url = $image_id ? wp_get_attachment_url( $image_id ) : '';
		$gallery   = array();
		foreach ( $product->get_gallery_image_ids() as $gid ) {
			$url = wp_get_attachment_url( $gid );
			if ( $url ) {
				$gallery[] = $url;
			}
		}

		$categories     = array();
		$category_ids   = $product->get_category_ids();
		foreach ( $category_ids as $cat_id ) {
			$term = get_term( $cat_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$categories[] = $term->name;
			}
		}

		$regular_price = $product->get_regular_price();
		$sale_price    = $product->get_sale_price();
		$price         = $product->get_price();
		$currency      = get_woocommerce_currency();

		$weight      = $product->get_weight();
		$length      = $product->get_length();
		$width       = $product->get_width();
		$height      = $product->get_height();
		$weight_unit = get_option( 'woocommerce_weight_unit', 'lbs' );
		$dim_unit    = get_option( 'woocommerce_dimension_unit', 'in' );

		$stock_status = $product->get_stock_status();
		$stock_qty    = $product->get_stock_quantity();

		$sku  = $product->get_sku();
		$gtin = $product->get_meta( '_gtin', true );
		if ( empty( $gtin ) ) {
			$gtin = $product->get_meta( '_global_unique_id', true );
		}
		$mpn  = $product->get_meta( '_mpn', true );
		if ( empty( $mpn ) && ! empty( $sku ) ) {
			$mpn = $sku;
		}
		$brand = $product->get_meta( '_brand', true );
		if ( empty( $brand ) ) {
			$brand = get_bloginfo( 'name' );
		}

		// Build short and long descriptions.
		$short_desc = $product->get_short_description();
		$full_desc  = $product->get_description();
		$desc       = ! empty( $full_desc ) ? $full_desc : $short_desc;
		$desc       = wp_strip_all_tags( $desc );
		$short_desc = wp_strip_all_tags( $short_desc );

		// Condition — default to "new".
		$condition = $product->get_meta( '_condition', true );
		if ( empty( $condition ) ) {
			$condition = 'new';
		}

		// Attributes for variants.
		$attributes = array();
		if ( $product->is_type( 'variation' ) ) {
			foreach ( $product->get_variation_attributes() as $attr_key => $attr_val ) {
				$attr_label = wc_attribute_label( str_replace( 'attribute_', '', $attr_key ), $product );
				$attributes[ $attr_label ] = $attr_val;
			}
		}

		$data = array(
			'id'               => $product->get_id(),
			'parent_id'        => $product->get_parent_id(),
			'title'            => $product->get_name(),
			'description'      => $desc,
			'short_description' => $short_desc,
			'link'             => $product->get_permalink(),
			'image_link'       => $image_url,
			'additional_image_links' => $gallery,
			'price'            => $price,
			'regular_price'    => $regular_price,
			'sale_price'       => $sale_price,
			'currency'         => $currency,
			'availability'     => self::map_availability( $stock_status ),
			'stock_quantity'   => $stock_qty,
			'sku'              => $sku,
			'gtin'             => $gtin,
			'mpn'              => $mpn,
			'brand'            => $brand,
			'condition'        => $condition,
			'weight'           => $weight,
			'weight_unit'      => $weight_unit,
			'length'           => $length,
			'width'            => $width,
			'height'           => $height,
			'dimension_unit'   => $dim_unit,
			'categories'       => $categories,
			'category_path'    => implode( ' > ', $categories ),
			'product_type'     => $product->get_type(),
			'attributes'       => $attributes,
			'tags'             => self::get_tag_names( $product ),
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
				return 'in_stock';
			case 'outofstock':
				return 'out_of_stock';
			case 'onbackorder':
				return 'backorder';
			default:
				return 'in_stock';
		}
	}

	/**
	 * Get product tag names.
	 */
	private static function get_tag_names( WC_Product $product ) {
		$tags = array();
		$terms = get_the_terms( $product->get_id(), 'product_tag' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$tags[] = $term->name;
			}
		}
		return $tags;
	}
}
