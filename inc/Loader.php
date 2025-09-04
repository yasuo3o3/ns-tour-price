<?php
/**
 * Data Source Loader
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Loader {

	private $data_sources = array();
	private $active_source = null;

	public function __construct() {
		$this->registerDataSources();
		$this->setActiveSource();
	}

	private function registerDataSources() {
		$this->data_sources = array(
			'csv' => new NS_Tour_Price_DataSourceCsv(),
			'sheets' => new NS_Tour_Price_DataSourceSheets(),
		);

		$this->data_sources = apply_filters( 'ns_tour_price_data_sources', $this->data_sources );
	}

	private function setActiveSource() {
		$options = get_option( 'ns_tour_price_options', array() );
		$preferred_source = $options['data_source'] ?? 'csv';

		// 希望するデータソースが利用可能かチェック
		if ( isset( $this->data_sources[ $preferred_source ] ) && 
			 $this->data_sources[ $preferred_source ]->isAvailable() ) {
			$this->active_source = $this->data_sources[ $preferred_source ];
			return;
		}

		// フォールバック: 利用可能な最初のデータソースを使用
		foreach ( $this->data_sources as $source ) {
			if ( $source->isAvailable() ) {
				$this->active_source = $source;
				return;
			}
		}

		// 利用可能なデータソースがない場合
		error_log( 'NS Tour Price: No available data sources found' );
		$this->active_source = null;
	}

	public function getActiveSource() {
		return $this->active_source;
	}

	public function isDataAvailable() {
		return null !== $this->active_source;
	}

	public function getAvailableDataSources() {
		$available = array();
		foreach ( $this->data_sources as $key => $source ) {
			if ( $source->isAvailable() ) {
				$available[ $key ] = $source->getName();
			}
		}
		return $available;
	}

	public function getAllDataSources() {
		$all = array();
		foreach ( $this->data_sources as $key => $source ) {
			$all[ $key ] = array(
				'name' => $source->getName(),
				'available' => $source->isAvailable(),
			);
		}
		return $all;
	}

	public function switchDataSource( $source_key ) {
		if ( ! isset( $this->data_sources[ $source_key ] ) ) {
			return false;
		}

		if ( ! $this->data_sources[ $source_key ]->isAvailable() ) {
			return false;
		}

		$this->active_source = $this->data_sources[ $source_key ];
		
		$options = get_option( 'ns_tour_price_options', array() );
		$options['data_source'] = $source_key;
		update_option( 'ns_tour_price_options', $options );

		return true;
	}
}