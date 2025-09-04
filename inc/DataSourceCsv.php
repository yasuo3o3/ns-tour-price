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
	private $loaded_files_log = array(); // ロード済みファイル情報

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

	/**
	 * ログ出力（WP_DEBUGに連動）
	 */
	private function log( $message, $level = 'info' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			// WP_DEBUG が無効の場合はwarning以上のみログ出力
			if ( ! in_array( $level, array( 'warning', 'error' ) ) ) {
				return;
			}
		}
		error_log( $message );
	}

	/**
	 * UTF-8 BOMを除去
	 */
	private function removeBOM( $content ) {
		$bom = "\xEF\xBB\xBF";
		if ( substr( $content, 0, 3 ) === $bom ) {
			return substr( $content, 3 );
		}
		return $content;
	}

	/**
	 * ファイル別必須列定義
	 */
	private function getRequiredColumns( $filename ) {
		$required_columns = array(
			'seasons.csv' => array( 'tour_id', 'season_code', 'label', 'date_start', 'date_end' ),
			'base_prices.csv' => array( 'tour_id', 'season_code', 'duration_days', 'price' ),
			'solo_fees.csv' => array( 'tour_id', 'duration_days', 'solo_fee' ),
			'daily_flags.csv' => array( 'tour_id', 'date', 'is_confirmed' ),
		);

		return isset( $required_columns[ $filename ] ) ? $required_columns[ $filename ] : array();
	}

	/**
	 * 必須列の検証
	 */
	private function validateRequiredColumns( $headers, $filename ) {
		$required = $this->getRequiredColumns( $filename );
		if ( empty( $required ) ) {
			return true;
		}

		$missing = array_diff( $required, $headers );
		if ( ! empty( $missing ) ) {
			$this->log( sprintf(
				'NS Tour Price: CSV file %s missing required columns: %s',
				$filename,
				implode( ', ', $missing )
			), 'error' );
			return false;
		}

		return true;
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
		// CSV存在チェックとログ出力
		$csv_files = array( 'seasons.csv', 'base_prices.csv', 'solo_fees.csv', 'daily_flags.csv' );
		$found_files = array();
		$missing_files = array();
		$active_source_path = '';

		foreach ( $csv_files as $filename ) {
			$file_path = $this->findCsvFile( $filename );
			if ( false !== $file_path ) {
				$found_files[ $filename ] = $file_path;
				// データソースパスを特定（最初に見つかったファイルから）
				if ( empty( $active_source_path ) ) {
					$active_source_path = dirname( $file_path ) . '/';
				}
			} else {
				$missing_files[] = $filename;
			}
		}

		// データソース情報をログ出力
		if ( ! empty( $active_source_path ) ) {
			$source_type = ( strpos( $active_source_path, 'plugins' ) !== false ) ? 'plugins/data' : 'uploads';
			$this->log( sprintf( 'NS Tour Price: datasource=%s (active)', $source_type ), 'info' );
		}

		// ファイル存在状況をログ出力
		if ( ! empty( $found_files ) ) {
			$file_list = array();
			foreach ( $found_files as $filename => $path ) {
				$file_list[] = $filename . ' (found)';
			}
			foreach ( $missing_files as $filename ) {
				$file_list[] = $filename . ' (missing)';
			}
			$this->log( sprintf( 'NS Tour Price: CSV files: %s', implode( ', ', $file_list ) ), 'info' );
		}

		// 最低限、seasons.csvとbase_prices.csvが存在するかチェック
		$seasons_exists = isset( $found_files['seasons.csv'] );
		$prices_exists = isset( $found_files['base_prices.csv'] );
		
		$is_available = $seasons_exists && $prices_exists;
		
		if ( ! $is_available ) {
			$this->log( 'NS Tour Price: DataSource not available - missing required CSV files', 'error' );
		}

		return $is_available;
	}

	/**
	 * ロード済みファイル情報を取得
	 */
	public function getLoadedFilesLog() {
		return $this->loaded_files_log;
	}

	/**
	 * ロードサマリーをログ出力
	 */
	public function logLoadSummary() {
		if ( empty( $this->loaded_files_log ) ) {
			return;
		}

		$summary_parts = array();
		foreach ( array( 'seasons.csv', 'base_prices.csv', 'solo_fees.csv', 'daily_flags.csv' ) as $filename ) {
			if ( isset( $this->loaded_files_log[ $filename ] ) ) {
				$info = $this->loaded_files_log[ $filename ];
				$summary_parts[] = sprintf( '%s=%d rows', str_replace( '.csv', '', $filename ), $info['rows'] );
			} else {
				$summary_parts[] = sprintf( '%s=0 rows (missing or unused)', str_replace( '.csv', '', $filename ) );
			}
		}

		$this->log( sprintf( 'NS Tour Price: load %s', implode( ' / ', $summary_parts ) ), 'info' );
	}

	public function getName() {
		return __( 'CSV Files', 'ns-tour_price' );
	}

	private function readCsvFile( $filename ) {
		$file_path = $this->findCsvFile( $filename );
		
		if ( false === $file_path ) {
			$this->log( sprintf( 
				'NS Tour Price: CSV file not found: %s in paths: %s', 
				$filename,
				implode( ', ', $this->data_paths )
			), 'warning' );
			return array();
		}

		// ファイル内容を一括読み込みしてBOM除去
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			$this->log( sprintf( 'NS Tour Price: Cannot read CSV file: %s', $file_path ), 'error' );
			return array();
		}

		// BOM除去チェック
		$original_content = $content;
		$content = $this->removeBOM( $content );
		if ( $content !== $original_content ) {
			$this->log( sprintf( 'NS Tour Price: BOM detected in %s, removed', $filename ), 'info' );
		}

		// 一時ファイルでCSVパース
		$handle = fopen( 'php://memory', 'r+' );
		fwrite( $handle, $content );
		rewind( $handle );

		$data = array();
		$headers = array();
		$line_number = 0;
		$bom_removed = $content !== $original_content;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$line_number++;
			
			if ( 1 === $line_number ) {
				$headers = array_map( 'trim', $row );
				
				// 必須列チェック
				if ( ! $this->validateRequiredColumns( $headers, $filename ) ) {
					fclose( $handle );
					return array();
				}
				continue;
			}

			if ( count( $row ) !== count( $headers ) ) {
				$this->log( sprintf( 
					'NS Tour Price: CSV column count mismatch in %s at line %d', 
					$filename, 
					$line_number 
				), 'warning' );
				continue;
			}

			$row_data = array();
			foreach ( $headers as $index => $header ) {
				$row_data[ $header ] = trim( $row[ $index ] );
			}
			
			$data[] = $row_data;
		}

		fclose( $handle );

		// ロード情報を記録
		$this->loaded_files_log[ $filename ] = array(
			'path' => $file_path,
			'rows' => count( $data ),
			'bom_removed' => $bom_removed,
		);

		$this->log( sprintf( 
			'NS Tour Price: loaded %s rows=%d from %s%s',
			$filename,
			count( $data ),
			$file_path,
			$bom_removed ? ' (BOM removed)' : ''
		), 'info' );

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