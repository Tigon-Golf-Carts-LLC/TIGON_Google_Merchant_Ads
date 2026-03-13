<?php
/**
 * Custom feed generator — user-defined marketplace feeds.
 *
 * Supports XML and CSV output with configurable column mapping.
 *
 * @package TigonMerchantFeeds
 */

defined( 'ABSPATH' ) || exit;

class TMF_Custom_Feed extends TMF_Feed_Generator {

	/**
	 * Feed configuration array.
	 *
	 * @var array
	 */
	private $config = array();

	/**
	 * Constructor.
	 *
	 * @param string $slug   Feed slug.
	 * @param array  $config Feed configuration.
	 */
	public function __construct( $slug, $config ) {
		$this->slug   = $slug;
		$this->config = $config;
		$this->label  = ! empty( $config['label'] ) ? $config['label'] : $slug;
	}

	public function content_type() {
		$format = isset( $this->config['format'] ) ? $this->config['format'] : 'csv';
		if ( 'xml' === $format ) {
			return 'application/xml; charset=UTF-8';
		}
		if ( 'tsv' === $format ) {
			return 'text/tab-separated-values; charset=UTF-8';
		}
		return 'text/csv; charset=UTF-8';
	}

	public function generate() {
		$format = isset( $this->config['format'] ) ? $this->config['format'] : 'csv';

		if ( 'xml' === $format ) {
			return $this->generate_xml();
		}
		if ( 'tsv' === $format ) {
			return $this->generate_tsv();
		}
		return $this->generate_csv();
	}

	/**
	 * Get the configured columns or default to all.
	 */
	private function get_columns() {
		if ( ! empty( $this->config['columns'] ) && is_array( $this->config['columns'] ) ) {
			return $this->config['columns'];
		}
		// Default comprehensive set.
		return array(
			'id', 'sku', 'title', 'description', 'link', 'image_link',
			'price', 'sale_price', 'currency', 'availability', 'condition',
			'brand', 'gtin', 'mpn', 'category_path', 'weight',
		);
	}

	/**
	 * Generate CSV feed.
	 */
	private function generate_csv() {
		$products = $this->get_products();
		$columns  = $this->get_columns();

		$output = fopen( 'php://temp', 'r+' );
		fputcsv( $output, $columns );

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );
			$row  = array();
			foreach ( $columns as $col ) {
				$val = isset( $data[ $col ] ) ? $data[ $col ] : '';
				if ( is_array( $val ) ) {
					$val = implode( ',', $val );
				}
				$row[] = $val;
			}
			$row = apply_filters( 'tmf_custom_feed_item_row', $row, $data, $product, $this->slug );
			fputcsv( $output, $row );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}

	/**
	 * Generate TSV feed.
	 */
	private function generate_tsv() {
		$products = $this->get_products();
		$columns  = $this->get_columns();

		$lines   = array();
		$lines[] = implode( "\t", $columns );

		foreach ( $products as $product ) {
			$data  = TMF_Field_Mapper::map( $product );
			$cells = array();
			foreach ( $columns as $col ) {
				$val = isset( $data[ $col ] ) ? $data[ $col ] : '';
				if ( is_array( $val ) ) {
					$val = implode( ',', $val );
				}
				$cells[] = str_replace( array( "\t", "\n", "\r" ), ' ', (string) $val );
			}
			$lines[] = implode( "\t", $cells );
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Generate XML feed.
	 */
	private function generate_xml() {
		$products   = $this->get_products();
		$columns    = $this->get_columns();
		$root_tag   = ! empty( $this->config['xml_root'] ) ? $this->config['xml_root'] : 'products';
		$item_tag   = ! empty( $this->config['xml_item'] ) ? $this->config['xml_item'] : 'product';

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		$xml .= '<' . esc_xml( $root_tag ) . ">\n";

		foreach ( $products as $product ) {
			$data = TMF_Field_Mapper::map( $product );
			$xml .= '  <' . esc_xml( $item_tag ) . ">\n";
			foreach ( $columns as $col ) {
				$val = isset( $data[ $col ] ) ? $data[ $col ] : '';
				if ( is_array( $val ) ) {
					$val = implode( ',', $val );
				}
				$xml .= '    <' . esc_xml( $col ) . '>' . $this->xml_escape( $val ) . '</' . esc_xml( $col ) . ">\n";
			}
			$xml .= '  </' . esc_xml( $item_tag ) . ">\n";
		}

		$xml .= '</' . esc_xml( $root_tag ) . ">\n";
		return $xml;
	}
}
