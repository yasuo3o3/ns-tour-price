<?php
/**
 * CSV Data Source Implementation
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_DataSourceCsv implements NS_Tour_Price_DataSourceInterface {

	private $data_paths = array();
	private $cache_prefix = 'ns_tour_price_csv_';
	private $cache_expiry = 3600; // 1時間

	public function __construct() {
		$this->setupDataPaths();
	}

	private function setupDataPaths() {
		// 優先順位: プラグインディレクトリ → アップロードディレクトリ
		$this->data_paths = array(
			NS_TOUR_PRICE_PLUGIN_DIR . 'data/',
			wp_upload_dir()['basedir'] . '/ns-tour_price/',
		);
	}

	public function getSeasons( $tour_id ) {
		$cache_key = $this->cache_prefix . 'seasons_' . $tour_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->readCsvFile( 'seasons.csv' );
		$result = array();

		foreach ( $data as $row ) {
			if ( ! isset( $row['tour_id'] ) || $row['tour_id'] !== $tour_id ) {
				continue;
			}

			$season = array(
				'tour_id' => sanitize_text_field( $row['tour_id'] ),
				'season_code' => sanitize_text_field( $row['season_code'] ),
				'label' => sanitize_text_field( $row['label'] ),
				'date_start' => sanitize_text_field( $row['date_start'] ),
				'date_end' => sanitize_text_field( $row['date_end'] ),
			);

			if ( $this->validateSeasonData( $season ) ) {
				$result[] = $season;
			}
		}

		set_transient( $cache_key, $result, $this->cache_expiry );
		return $result;
	}

	public function getBasePrices( $tour_id ) {
		$cache_key = $this->cache_prefix . 'prices_' . $tour_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->readCsvFile( 'base_prices.csv' );
		$result = array();

		foreach ( $data as $row ) {
			if ( ! isset( $row['tour_id'] ) || $row['tour_id'] !== $tour_id ) {
				continue;
			}

			$price = array(
				'tour_id' => sanitize_text_field( $row['tour_id'] ),
				'season_code' => sanitize_text_field( $row['season_code'] ),
				'duration_days' => intval( $row['duration_days'] ),
				'price' => intval( $row['price'] ),
			);

			if ( $this->validatePriceData( $price ) ) {
				$result[] = $price;
			}
		}

		set_transient( $cache_key, $result, $this->cache_expiry );
		return $result;
	}

	public function getDailyFlags( $tour_id ) {
		$cache_key = $this->cache_prefix . 'flags_' . $tour_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->readCsvFile( 'daily_flags.csv' );
		$result = array();

		foreach ( $data as $row ) {
			if ( ! isset( $row['tour_id'] ) || $row['tour_id'] !== $tour_id ) {
				continue;
			}

			$flag = array(
				'tour_id' => sanitize_text_field( $row['tour_id'] ),
				'date' => sanitize_text_field( $row['date'] ),
				'is_confirmed' => intval( $row['is_confirmed'] ),
				'note' => sanitize_text_field( $row['note'] ?? '' ),
			);

			if ( $this->validateFlagData( $flag ) ) {
				$result[] = $flag;
			}
		}

		set_transient( $cache_key, $result, $this->cache_expiry );
		return $result;
	}

	public function getSoloFees( $tour_id ) {
		$cache_key = $this->cache_prefix . 'solo_fees_' . $tour_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->readCsvFile( 'solo_fees.csv' );
		$result = array();

		foreach ( $data as $row ) {
			if ( ! isset( $row['tour_id'] ) || $row['tour_id'] !== $tour_id ) {
				continue;
			}

			$solo_fee = array(
				'tour_id' => sanitize_text_field( $row['tour_id'] ),
				'duration_days' => intval( $row['duration_days'] ),
				'solo_fee' => intval( $row['solo_fee'] ),
			);

			if ( $this->validateSoloFeeData( $solo_fee ) ) {
				$result[] = $solo_fee;
			}
		}

		set_transient( $cache_key, $result, $this->cache_expiry );
		return $result;
	}

	public function isAvailable() {
		// 最低限、seasons.csvとbase_prices.csvが存在するかチェック
		$seasons_path = $this->findCsvFile( 'seasons.csv' );
		$prices_path = $this->findCsvFile( 'base_prices.csv' );
		
		return ( false !== $seasons_path && false !== $prices_path );
	}

	public function getName() {
		return __( 'CSV Files', 'ns-tour_price' );
	}

	private function readCsvFile( $filename ) {
		$file_path = $this->findCsvFile( $filename );
		
		if ( false === $file_path ) {
			error_log( sprintf( 
				'NS Tour Price: CSV file not found: %s in paths: %s', 
				$filename,
				implode( ', ', $this->data_paths )
			) );
			return array();
		}

		$handle = fopen( $file_path, 'r' );
		if ( false === $handle ) {
			error_log( sprintf( 'NS Tour Price: Cannot open CSV file: %s', $file_path ) );
			return array();
		}

		$data = array();
		$headers = array();
		$line_number = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$line_number++;
			
			if ( 1 === $line_number ) {
				$headers = array_map( 'trim', $row );
				continue;
			}

			if ( count( $row ) !== count( $headers ) ) {
				error_log( sprintf( 
					'NS Tour Price: CSV column count mismatch in %s at line %d', 
					$filename, 
					$line_number 
				) );
				continue;
			}

			$row_data = array();
			foreach ( $headers as $index => $header ) {
				$row_data[ $header ] = trim( $row[ $index ] );
			}
			
			$data[] = $row_data;
		}

		fclose( $handle );
		return $data;
	}

	private function findCsvFile( $filename ) {
		foreach ( $this->data_paths as $path ) {
			$full_path = $path . $filename;
			if ( file_exists( $full_path ) && is_readable( $full_path ) ) {
				return $full_path;
			}
		}
		return false;
	}

	private function validateSeasonData( $season ) {
		if ( empty( $season['tour_id'] ) || empty( $season['season_code'] ) ) {
			return false;
		}

		if ( empty( $season['date_start'] ) || empty( $season['date_end'] ) ) {
			return false;
		}

		// 日付形式チェック
		$start_date = DateTime::createFromFormat( 'Y-m-d', $season['date_start'] );
		$end_date = DateTime::createFromFormat( 'Y-m-d', $season['date_end'] );

		return ( false !== $start_date && false !== $end_date && $start_date <= $end_date );
	}

	private function validatePriceData( $price ) {
		if ( empty( $price['tour_id'] ) || empty( $price['season_code'] ) ) {
			return false;
		}

		if ( $price['duration_days'] <= 0 || $price['price'] < 0 ) {
			return false;
		}

		return true;
	}

	private function validateFlagData( $flag ) {
		if ( empty( $flag['tour_id'] ) || empty( $flag['date'] ) ) {
			return false;
		}

		// 日付形式チェック
		$date = DateTime::createFromFormat( 'Y-m-d', $flag['date'] );
		return ( false !== $date );
	}

	private function validateSoloFeeData( $solo_fee ) {
		if ( empty( $solo_fee['tour_id'] ) ) {
			return false;
		}

		if ( $solo_fee['duration_days'] <= 0 || $solo_fee['solo_fee'] < 0 ) {
			return false;
		}

		return true;
	}

	public function clearCache() {
		global $wpdb;
		
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_' . $this->cache_prefix ) . '%'
			)
		);
	}
}