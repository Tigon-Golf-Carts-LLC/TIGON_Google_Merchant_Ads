<?php
/**
 * Amazon Seller feed generator.
 *
 * Produces a tab-delimited TXT feed compatible with Amazon Seller Central bulk upload.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Amazon_Feed extends TMF_Feed_Generator {

	protected $slug  = 'amazon';
	protected $label = 'Amazon';

	public function content_type() {
		return 'text/tab-separated-values; charset=UTF-8';
	}

	public function generate() {
		$products = $this->get_products();

		$columns = array(
			'sku',
			'product-id',
			'product-id-type',
			'item_name',
			'item_description',
			'standard-price',
			'sale-price',
			'quantity',
			'main-image-url',
			'other-image-url1',
			'other-image-url2',
			'other-image-url3',
			'manufacturer',
			'brand',
			'item-condition',
			'item_type',
			'external-product-url',
			'parent-sku',
			'relationship-type',
			'variation-theme',
			'color',
			'size',
			'item-weight',
			'item-weight-unit-of-measure',
			'item-length',
			'item-width',
			'item-height',
			'item-dimensions-unit-of-measure',
			'fulfillment-channel',
		);

		$lines   = array();
		$lines[] = implode( "\t", $columns );

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );
			if ( empty( $data['title'] ) || empty( $data['price'] ) ) {
				continue;
			}

			$id_type = ! empty( $data['gtin'] ) ? 'UPC' : '';
			$id_val  = ! empty( $data['gtin'] ) ? $data['gtin'] : '';

			$gallery = $data['additional_image_links'];
			$color   = '';
			$size    = '';
			foreach ( $data['attributes'] as $label => $val ) {
				if ( in_array( strtolower( $label ), array( 'color', 'colour' ), true ) ) {
					$color = $val;
				}
				if ( strtolower( $label ) === 'size' ) {
					$size = $val;
				}
			}

			$row = array(
				$data['sku'] ?: $data['id'],
				$id_val,
				$id_type,
				$data['title'],
				$data['description'],
				$data['regular_price'] ?: $data['price'],
				$data['sale_price'] ?: '',
				$data['stock_quantity'] !== null ? $data['stock_quantity'] : '',
				$data['image_link'],
				isset( $gallery[0] ) ? $gallery[0] : '',
				isset( $gallery[1] ) ? $gallery[1] : '',
				isset( $gallery[2] ) ? $gallery[2] : '',
				$data['brand'],
				$data['brand'],
				ucfirst( $data['condition'] ),
				'Golf Carts',
				$data['link'],
				$data['parent_id'] ?: '',
				$data['parent_id'] ? 'Variation' : '',
				( $color || $size ) ? implode( '-', array_filter( array( $color ? 'Color' : '', $size ? 'Size' : '' ) ) ) : '',
				$color,
				$size,
				$data['weight'] ?: '',
				$data['weight_unit'],
				$data['length'] ?: '',
				$data['width'] ?: '',
				$data['height'] ?: '',
				$data['dimension_unit'],
				'DEFAULT',
			);

			$row     = apply_filters( 'tmf_amazon_item_row', $row, $data, $product );
			$lines[] = implode( "\t", array_map( array( $this, 'tsv_escape' ), $row ) );
		}

		$tsv = implode( "\n", $lines ) . "\n";
		return apply_filters( 'tmf_amazon_feed_tsv', $tsv );
	}

	/**
	 * Escape value for TSV (remove tabs and newlines).
	 */
	private function tsv_escape( $value ) {
		return str_replace( array( "\t", "\n", "\r" ), ' ', (string) $value );
	}
}
