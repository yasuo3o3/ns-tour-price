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

	public function load(): void {
		// このデータソースは遅延ロード（getSeasons()などが呼ばれた際に都度CSVを読み込む）
		// を採用しているため、このメソッドでの事前一括ロードは不要です。
		// インターフェースの契約を満たすために空のメソッドとして実装しています。
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
			'tours.csv' => array( 'tour_id', 'tour_name' ),
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
		$parse_stats = array(
			'total' => 0,
			'ok' => 0,
			'failed' => 0,
			'failed_examples' => array(),
		);

		foreach ( $data as $row ) {
			if ( ! isset( $row['tour_id'] ) || $row['tour_id'] !== $tour_id ) {
				continue;
			}

			$parse_stats['total']++;
			$raw_start = $row['date_start'] ?? '';
			$raw_end = $row['date_end'] ?? '';

			// 日付正規化を実行
			$normalized_start = NS_Tour_Price_Helpers::normalize_date( $raw_start );
			$normalized_end = NS_Tour_Price_Helpers::normalize_date( $raw_end );

			$season = array(
				'tour_id' => sanitize_text_field( $row['tour_id'] ),
				'season_code' => sanitize_text_field( $row['season_code'] ),
				'label' => sanitize_text_field( $row['label'] ),
				'date_start' => $normalized_start,
				'date_end' => $normalized_end,
			);

			// 日付正規化チェック
			if ( false === $normalized_start || false === $normalized_end ) {
				$parse_stats['failed']++;
				if ( count( $parse_stats['failed_examples'] ) < 3 ) {
					$parse_stats['failed_examples'][] = array(
						'tour_id' => $row['tour_id'],
						'season_code' => $row['season_code'] ?? '',
						'raw_date_start' => $raw_start,
						'raw_date_end' => $raw_end,
						'reason' => 'date_parse_failed',
					);
				}
				continue;
			}

			// 日付範囲の妥当性チェック
			if ( ! NS_Tour_Price_Helpers::validate_date_range( $normalized_start, $normalized_end ) ) {
				$parse_stats['failed']++;
				if ( count( $parse_stats['failed_examples'] ) < 3 ) {
					$parse_stats['failed_examples'][] = array(
						'tour_id' => $row['tour_id'],
						'season_code' => $row['season_code'] ?? '',
						'raw_date_start' => $raw_start,
						'raw_date_end' => $raw_end,
						'reason' => 'invalid_date_range',
					);
				}
				continue;
			}

			// その他の妥当性チェック
			if ( $this->validateSeasonData( $season ) ) {
				$result[] = $season;
				$parse_stats['ok']++;
			} else {
				$parse_stats['failed']++;
				if ( count( $parse_stats['failed_examples'] ) < 3 ) {
					$parse_stats['failed_examples'][] = array(
						'tour_id' => $row['tour_id'],
						'season_code' => $row['season_code'] ?? '',
						'raw_date_start' => $raw_start,
						'raw_date_end' => $raw_end,
						'reason' => 'validation_failed',
					);
				}
			}
		}

		// パース結果の統計ログ
		if ( $parse_stats['total'] > 0 ) {
			$this->log( sprintf( 
				'NS Tour Price: seasons parsed rows = %d/%d, failed=%d for tour=%s',
				$parse_stats['ok'],
				$parse_stats['total'],
				$parse_stats['failed'],
				$tour_id
			), 'info' );

			// 失敗例をサンプル出力（最大3件）
			foreach ( $parse_stats['failed_examples'] as $example ) {
				$this->log( sprintf( 
					'NS Tour Price: seasons parse failed example - tour_id=%s, season_code=%s, raw_start=%s, raw_end=%s, reason=%s',
					$example['tour_id'],
					$example['season_code'],
					$example['raw_date_start'],
					$example['raw_date_end'],
					$example['reason']
				), 'warning' );
			}
		}

		set_transient( $cache_key, $result, $this->cache_expiry );
		return $result;
	}

	public function getAllSeasons() {
		$cache_key = $this->cache_prefix . 'all_seasons';
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->readCsvFile( 'seasons.csv' );
		$result = array();

		foreach ( $data as $row ) {
			if ( ! isset( $row['tour_id'] ) ) {
				continue;
			}

			$raw_start = $row['date_start'] ?? '';
			$raw_end = $row['date_end'] ?? '';

			$season = array(
				'tour_id' => sanitize_text_field( $row['tour_id'] ),
				'season_code' => sanitize_text_field( $row['season_code'] ),
				'season_label' => isset( $row['season_label'] ) ? sanitize_text_field( $row['season_label'] ) : '',
				'date_start' => $raw_start,
				'date_end' => $raw_end,
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
		$csv_files = array( 'seasons.csv', 'base_prices.csv', 'solo_fees.csv', 'daily_flags.csv', 'tour_options.csv', 'tours.csv' );
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
			// 失敗理由の詳細分類
			$failure_reason = $this->getAvailabilityFailureReason( $found_files, $missing_files );
			$this->log( sprintf( 'NS Tour Price: DataSource not available - %s', $failure_reason ), 'error' );
		}

		return $is_available;
	}

	/**
	 * データソース利用不可の理由を詳細分類して取得
	 *
	 * @param array $found_files 見つかったファイル一覧
	 * @param array $missing_files 見つからないファイル一覧
	 * @return string 失敗理由の分類メッセージ
	 */
	private function getAvailabilityFailureReason( $found_files, $missing_files ) {
		$required_files = array( 'seasons.csv', 'base_prices.csv' );
		$missing_required = array();

		foreach ( $required_files as $file ) {
			if ( ! isset( $found_files[ $file ] ) ) {
				$missing_required[] = $file;
			}
		}

		if ( count( $missing_required ) === 2 ) {
			return 'missing_csv_data (both seasons.csv and base_prices.csv not found)';
		} elseif ( in_array( 'seasons.csv', $missing_required, true ) ) {
			return 'missing_csv_data (seasons.csv not found)';
		} elseif ( in_array( 'base_prices.csv', $missing_required, true ) ) {
			return 'missing_csv_data (base_prices.csv not found)';
		}

		// ここに来ることは通常ないが、念のため
		return 'missing_csv_data (unknown required file missing)';
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
		error_log("findCsvFile called with filename: '$filename'");
		if (strpos($filename, '/') !== false) {
			error_log("findCsvFile: WARNING - filename contains path separators, this may be incorrect");
			error_log("findCsvFile: Stack trace: " . wp_debug_backtrace_summary());
		}
		foreach ( $this->data_paths as $path ) {
			$full_path = $path . $filename;
			error_log("Checking CSV path: {$full_path} - exists: " . (file_exists( $full_path ) ? 'yes' : 'no'));
			if ( file_exists( $full_path ) && is_readable( $full_path ) ) {
				error_log("findCsvFile: Found file at $full_path");
				return $full_path;
			}
		}
		error_log("NS Tour Price: CSV file not found: {$filename} in paths: " . implode(', ', $this->data_paths));
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

	public function getTourOptions( $tour_id = null ) {
		error_log("getTourOptions called with tour_id: " . ($tour_id ?? 'null'));
		error_log("Data paths: " . print_r($this->data_paths, true));
		
		$cache_key = $this->cache_prefix . 'tour_options_' . ( $tour_id ? $tour_id : 'all' );
		// キャッシュをクリア（デバッグ用）
		delete_transient( $cache_key );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			error_log("Returning cached tour_options");
			return $cached;
		}

		$data = $this->readCsvFile( 'tour_options.csv' );
		error_log("TourOptions CSV data: " . print_r($data, true));
		if ( empty( $data ) ) {
			return array();
		}

		$result = array();
		foreach ( $data as $row ) {
			error_log("Processing tour option row: " . print_r($row, true));
			// ツアーIDでフィルタ（指定されている場合）
			if ( $tour_id && ( ! isset( $row['tour_id'] ) || $row['tour_id'] !== $tour_id ) ) {
				error_log("Skipping row for tour_id: {$tour_id}, row tour_id: " . ($row['tour_id'] ?? 'null'));
				continue;
			}

			$option = array(
				'tour_id' => sanitize_text_field( $row['tour_id'] ?? '' ),
				'option_id' => sanitize_text_field( $row['option_id'] ?? '' ),
				'option_label' => sanitize_text_field( $row['option_label'] ?? '' ),
				'price_min' => intval( $row['price_min'] ?? 0 ),
				'price_max' => intval( $row['price_max'] ?? 0 ),
				'show_price' => filter_var( $row['show_price'] ?? true, FILTER_VALIDATE_BOOLEAN ),
				'description' => sanitize_text_field( $row['description'] ?? '' ),
				'image_url' => esc_url_raw( $row['image_url'] ?? '' ),
				'affects_total' => filter_var( $row['affects_total'] ?? true, FILTER_VALIDATE_BOOLEAN ),
			);

			if ( $this->validateTourOptionData( $option ) ) {
				$result[] = $option;
			}
		}

		set_transient( $cache_key, $result, $this->cache_expiry );
		return $result;
	}

	public function getTours() {
		$cache_key = $this->cache_prefix . 'tours';
		$cached = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$data = $this->readCsvFile( 'tours.csv' );
		if ( empty( $data ) ) {
			return array();
		}

		$result = array();
		foreach ( $data as $row ) {
			$tour = array(
				'tour_id'     => sanitize_text_field( $row['tour_id'] ?? '' ),
				'tour_name'   => sanitize_text_field( $row['tour_name'] ?? '' ),
				'description' => sanitize_text_field( $row['description'] ?? '' ),
				'category'    => sanitize_text_field( $row['category'] ?? '' ),
				'status'      => sanitize_text_field( $row['status'] ?? '' ),
			);

			if ( ! empty( $tour['tour_id'] ) ) {
				$result[] = $tour;
			}
		}

		set_transient( $cache_key, $result, $this->cache_expiry );
		return $result;
	}

	private function validateTourOptionData( $option ) {
		if ( empty( $option['tour_id'] ) || empty( $option['option_id'] ) ) {
			return false;
		}

		if ( $option['price_min'] < 0 || $option['price_max'] < 0 ) {
			return false;
		}

		if ( $option['price_max'] < $option['price_min'] ) {
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