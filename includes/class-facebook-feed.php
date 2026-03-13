<?php
/**
 * Facebook / Meta Commerce feed generator.
 *
 * Produces a CSV feed compliant with Meta Commerce Manager Product Data Specification.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Facebook_Feed extends TMF_Feed_Generator {

	protected $slug  = 'facebook';
	protected $label = 'Facebook / Meta';

	public function content_type() {
		return 'text/csv; charset=UTF-8';
	}

	public function generate() {
		$products = $this->get_products();
		$google_cat = get_option( 'tmf_google_category_name', 'Vehicles & Parts > Vehicles > Golf Carts' );

		$columns = array(
			'id',
			'title',
			'description',
			'availability',
			'condition',
			'price',
			'sale_price',
			'link',
			'image_link',
			'additional_image_link',
			'brand',
			'google_product_category',
			'product_type',
			'gtin',
			'mpn',
			'item_group_id',
			'color',
			'size',
		);

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, $columns );

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );
			if ( empty( $data['title'] ) || empty( $data['link'] ) || empty( $data['price'] ) ) {
				continue;
			}

			$color = '';
			$size  = '';
			foreach ( $data['attributes'] as $label => $val ) {
				if ( in_array( strtolower( $label ), array( 'color', 'colour' ), true ) ) {
					$color = $val;
				}
				if ( strtolower( $label ) === 'size' ) {
					$size = $val;
				}
			}

			$row = array(
				$data['id'],
				$data['title'],
				$data['description'],
				str_replace( '_', ' ', $data['availability'] ),
				$data['condition'],
				$this->format_price( $data['regular_price'] ?: $data['price'], $data['currency'] ),
				! empty( $data['sale_price'] ) ? $this->format_price( $data['sale_price'], $data['currency'] ) : '',
				$data['link'],
				$data['image_link'],
				implode( ',', array_slice( $data['additional_image_links'], 0, 10 ) ),
				$data['brand'],
				$google_cat,
				$data['category_path'],
				$data['gtin'],
				$data['mpn'],
				$data['parent_id'] ?: '',
				$color,
				$size,
			);

			$row = apply_filters( 'tmf_facebook_item_row', $row, $data, $product );
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return apply_filters( 'tmf_facebook_feed_csv', $csv );
	}
}
