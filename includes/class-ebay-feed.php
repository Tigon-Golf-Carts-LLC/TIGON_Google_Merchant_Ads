<?php
/**
 * eBay feed generator.
 *
 * Produces a CSV feed compatible with eBay File Exchange / Seller Hub.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Ebay_Feed extends TMF_Feed_Generator {

	protected $slug  = 'ebay';
	protected $label = 'eBay';

	public function content_type() {
		return 'text/csv; charset=UTF-8';
	}

	public function generate() {
		$products = $this->get_products();
		$currency = get_woocommerce_currency();

		$columns = array(
			'Action',
			'CustomLabel',
			'Title',
			'Description',
			'Category',
			'ConditionID',
			'PicURL',
			'Quantity',
			'StartPrice',
			'BuyItNowPrice',
			'Currency',
			'Format',
			'Duration',
			'Brand',
			'MPN',
			'UPC',
			'Product:URL',
			'ShippingType',
			'Relationship',
			'RelationshipDetails',
			'C:Color',
			'C:Size',
			'ItemWeight',
		);

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, $columns );

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );
			if ( empty( $data['title'] ) || empty( $data['price'] ) ) {
				continue;
			}

			$condition_id = 'new' === $data['condition'] ? '1000' : '3000';
			$images       = array_merge( array( $data['image_link'] ), array_slice( $data['additional_image_links'], 0, 11 ) );
			$images       = array_filter( $images );
			$pic_url      = implode( '|', $images );

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
				'Add',
				$data['sku'] ?: $data['id'],
				substr( $data['title'], 0, 80 ),
				$data['description'],
				'Golf Carts',
				$condition_id,
				$pic_url,
				$data['stock_quantity'] !== null ? $data['stock_quantity'] : 1,
				$data['price'],
				$data['regular_price'] ?: $data['price'],
				$currency,
				'FixedPrice',
				'GTC',
				$data['brand'],
				$data['mpn'],
				$data['gtin'],
				$data['link'],
				'Flat',
				$data['parent_id'] ? 'Variation' : '',
				$data['parent_id'] ? 'ParentSKU=' . $data['parent_id'] : '',
				$color,
				$size,
				$data['weight'] ? $data['weight'] . ' ' . $data['weight_unit'] : '',
			);

			$row = apply_filters( 'tmf_ebay_item_row', $row, $data, $product );
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return apply_filters( 'tmf_ebay_feed_csv', $csv );
	}
}
