<?php
/**
 * TikTok Shop feed generator.
 *
 * Produces a CSV feed compatible with TikTok Shop bulk product upload.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Tiktok_Feed extends TMF_Feed_Generator {

	protected $slug  = 'tiktok';
	protected $label = 'TikTok Shop';

	public function content_type() {
		return 'text/csv; charset=UTF-8';
	}

	public function generate() {
		$products = $this->get_products();

		$columns = array(
			'product_id',
			'sku_id',
			'product_name',
			'product_description',
			'category',
			'brand',
			'main_image',
			'additional_images',
			'price',
			'original_price',
			'currency',
			'stock',
			'condition',
			'product_url',
			'gtin',
			'mpn',
			'color',
			'size',
			'weight',
			'weight_unit',
			'item_group_id',
		);

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, $columns );

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );
			if ( empty( $data['title'] ) || empty( $data['price'] ) ) {
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
				$data['sku'] ?: $data['id'],
				$data['title'],
				$data['description'],
				'Golf Carts',
				$data['brand'],
				$data['image_link'],
				implode( ',', array_slice( $data['additional_image_links'], 0, 8 ) ),
				$data['price'],
				$data['regular_price'] ?: $data['price'],
				$data['currency'],
				$data['stock_quantity'] !== null ? $data['stock_quantity'] : '',
				$data['condition'],
				$data['link'],
				$data['gtin'],
				$data['mpn'],
				$color,
				$size,
				$data['weight'] ?: '',
				$data['weight_unit'],
				$data['parent_id'] ?: '',
			);

			$row = apply_filters( 'tmf_tiktok_item_row', $row, $data, $product );
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return apply_filters( 'tmf_tiktok_feed_csv', $csv );
	}
}
