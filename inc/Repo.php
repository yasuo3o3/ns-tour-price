<?php
/**
 * Data Repository
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Repo {

	private $loader;
	private static $instance = null;
	private $aliases_cache = array();

	public function __construct() {
		$this->loader = new NS_Tour_Price_Loader();
	}

	public static function getInstance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function getSeasons( $tour_id ) {
		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$source = $this->loader->getActiveSource();
		return $source->getSeasons( $tour_id );
	}

	public function getBasePrices( $tour_id ) {
		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$source = $this->loader->getActiveSource();
		return $source->getBasePrices( $tour_id );
	}

	public function getDailyFlags( $tour_id ) {
		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$source = $this->loader->getActiveSource();
		return $source->getDailyFlags( $tour_id );
	}

	public function getPriceForDate( $tour_id, $date, $duration ) {
		$seasons = $this->getSeasons( $tour_id );
		$prices = $this->getBasePrices( $tour_id );

		if ( empty( $seasons ) || empty( $prices ) ) {
			return null;
		}

		// エイリアスを読み込み
		$aliases = $this->loadSeasonAliases( $tour_id );

		// 指定日付に適用されるシーズンを見つける
		$season_code = null;
		$date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
		
		if ( false === $date_obj ) {
			return null;
		}

		foreach ( $seasons as $season ) {
			$start_date = DateTime::createFromFormat( 'Y-m-d', $season['date_start'] );
			$end_date = DateTime::createFromFormat( 'Y-m-d', $season['date_end'] );

			if ( false !== $start_date && false !== $end_date &&
				 $date_obj >= $start_date && $date_obj <= $end_date ) {
				$season_code = $season['season_code'];
				break;
			}
		}

		if ( null === $season_code ) {
			return null;
		}

		// seasons.csvのseason_codeを正規化
		$normalized_season_code = NS_Tour_Price_Helpers::normalize_season_code( $season_code );

		// シーズンコードと日数に一致する価格を見つける（正規化＆エイリアス対応）
		foreach ( $prices as $price ) {
			if ( intval( $duration ) !== $price['duration_days'] ) {
				continue;
			}

			$price_season_code = $price['season_code'];
			$normalized_price_code = NS_Tour_Price_Helpers::normalize_season_code( $price_season_code );

			// エイリアス解決を試行
			if ( ! empty( $aliases ) && isset( $aliases[ $normalized_price_code ] ) ) {
				$resolved_code = NS_Tour_Price_Helpers::normalize_season_code( $aliases[ $normalized_price_code ] );
				if ( $resolved_code === $normalized_season_code ) {
					return $price['price'];
				}
			} else {
				// 正規化後のコードで直接比較
				if ( $normalized_price_code === $normalized_season_code ) {
					return $price['price'];
				}
			}
		}

		return null;
	}

	public function isConfirmedDate( $tour_id, $date ) {
		$flags = $this->getDailyFlags( $tour_id );

		foreach ( $flags as $flag ) {
			if ( $flag['date'] === $date && $flag['is_confirmed'] ) {
				return true;
			}
		}

		return false;
	}

	public function getDateNote( $tour_id, $date ) {
		$flags = $this->getDailyFlags( $tour_id );

		foreach ( $flags as $flag ) {
			if ( $flag['date'] === $date && ! empty( $flag['note'] ) ) {
				return $flag['note'];
			}
		}

		return '';
	}

	public function isDataAvailable() {
		return $this->loader->isDataAvailable();
	}

	public function getDataSourceInfo() {
		$active = $this->loader->getActiveSource();
		if ( null === $active ) {
			return array(
				'active' => null,
				'available' => $this->loader->getAvailableDataSources(),
				'all' => $this->loader->getAllDataSources(),
			);
		}

		return array(
			'active' => $active->getName(),
			'available' => $this->loader->getAvailableDataSources(),
			'all' => $this->loader->getAllDataSources(),
		);
	}

	public function switchDataSource( $source_key ) {
		return $this->loader->switchDataSource( $source_key );
	}

	/**
	 * season_aliases.csv を読み込む
	 *
	 * @param string $tour_id ツアーID
	 * @return array エイリアスマッピング ['alias' => 'season_code', ...]
	 */
	private function loadSeasonAliases( $tour_id ) {
		// インメモリキャッシュ
		if ( isset( $this->aliases_cache[ $tour_id ] ) ) {
			return $this->aliases_cache[ $tour_id ];
		}

		$cache_key = 'ns_tour_price_season_aliases_' . $tour_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			$this->aliases_cache[ $tour_id ] = $cached;
			return $cached;
		}

		$aliases = array();

		// CSV探索順: /plugins/.../data → /uploads/ns-tour_price/
		$data_paths = array(
			NS_TOUR_PRICE_PLUGIN_DIR . 'data/',
			wp_upload_dir()['basedir'] . '/ns-tour_price/',
		);

		$aliases_file = null;
		foreach ( $data_paths as $path ) {
			$full_path = $path . 'season_aliases.csv';
			if ( file_exists( $full_path ) && is_readable( $full_path ) ) {
				$aliases_file = $full_path;
				break;
			}
		}

		if ( null === $aliases_file ) {
			// エイリアスファイルが無い場合は空の配列
			set_transient( $cache_key, $aliases, 3600 );
			$this->aliases_cache[ $tour_id ] = $aliases;
			return $aliases;
		}

		// CSVファイル読み込み
		$handle = fopen( $aliases_file, 'r' );
		if ( false === $handle ) {
			error_log( sprintf( 'NS Tour Price: Cannot open season_aliases.csv: %s', $aliases_file ) );
			$this->aliases_cache[ $tour_id ] = $aliases;
			return $aliases;
		}

		$headers = array();
		$line_number = 0;

		while ( ( $row = fgetcsv( $handle ) ) !== false ) {
			$line_number++;
			
			if ( 1 === $line_number ) {
				$headers = array_map( 'trim', $row );
				// 必須フィールドチェック
				if ( ! in_array( 'tour_id', $headers ) || ! in_array( 'alias', $headers ) || ! in_array( 'season_code', $headers ) ) {
					error_log( 'NS Tour Price: season_aliases.csv missing required columns: tour_id, alias, season_code' );
					break;
				}
				continue;
			}

			if ( count( $row ) !== count( $headers ) ) {
				continue; // 列数不一致はスキップ
			}

			$row_data = array();
			foreach ( $headers as $index => $header ) {
				$row_data[ $header ] = trim( $row[ $index ] );
			}

			// 指定ツアーID のみ処理
			if ( ! isset( $row_data['tour_id'] ) || $row_data['tour_id'] !== $tour_id ) {
				continue;
			}

			$alias = sanitize_text_field( $row_data['alias'] );
			$season_code = sanitize_text_field( $row_data['season_code'] );

			if ( ! empty( $alias ) && ! empty( $season_code ) ) {
				// エイリアスも正規化
				$normalized_alias = NS_Tour_Price_Helpers::normalize_season_code( $alias );
				$aliases[ $normalized_alias ] = $season_code;
			}
		}

		fclose( $handle );

		// 結果をキャッシュ（1時間）
		set_transient( $cache_key, $aliases, 3600 );
		$this->aliases_cache[ $tour_id ] = $aliases;

		return $aliases;
	}

	/**
	 * エイリアスを適用してseason_codeを解決する
	 *
	 * @param string $season_code 元のseason_code
	 * @param array $aliases エイリアスマッピング
	 * @return string 解決後のseason_code
	 */
	private function resolveSeasonCodeAlias( $season_code, $aliases ) {
		$normalized = NS_Tour_Price_Helpers::normalize_season_code( $season_code );
		return $aliases[ $normalized ] ?? $season_code;
	}

	/**
	 * season_code の整合性をチェック（正規化＆エイリアス対応）
	 * base_prices にあって seasons に無いコードの配列を返す
	 *
	 * @param string $tour_id ツアーID
	 * @return array 不整合な season_code の配列
	 */
	public function validateSeasonCodes( $tour_id ) {
		// キャッシュキーを生成
		$cache_key = 'ns_tour_price_season_validation_' . $tour_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$seasons = $this->getSeasons( $tour_id );
		$prices = $this->getBasePrices( $tour_id );

		if ( empty( $seasons ) || empty( $prices ) ) {
			return array();
		}

		// エイリアスを読み込み
		$aliases = $this->loadSeasonAliases( $tour_id );

		// seasons.csv から有効な season_code を取得（正規化済み）
		$valid_season_codes = array();
		foreach ( $seasons as $season ) {
			$normalized = NS_Tour_Price_Helpers::normalize_season_code( $season['season_code'] );
			$valid_season_codes[] = $normalized;
		}
		$valid_season_codes = array_unique( $valid_season_codes );

		// base_prices.csv で使用されている season_code を取得
		$used_season_codes = array();
		$resolved_count = 0;
		$unresolved_codes = array();
		
		foreach ( $prices as $price ) {
			$original_code = $price['season_code'];
			$normalized = NS_Tour_Price_Helpers::normalize_season_code( $original_code );
			
			// エイリアス解決を試行
			if ( ! empty( $aliases ) && isset( $aliases[ $normalized ] ) ) {
				// エイリアス経由で解決
				$resolved_code = NS_Tour_Price_Helpers::normalize_season_code( $aliases[ $normalized ] );
				$used_season_codes[] = $resolved_code;
				$resolved_count++;
			} else {
				// 正規化後のコードをそのまま使用
				$used_season_codes[] = $normalized;
				$unresolved_codes[] = $original_code;
			}
		}
		$used_season_codes = array_unique( $used_season_codes );

		// base_prices にあって seasons に無いコードを検出（正規化＆エイリアス適用後）
		$invalid_codes = array_diff( $used_season_codes, $valid_season_codes );

		// 不整合があればログに詳細記録
		if ( ! empty( $invalid_codes ) ) {
			error_log( sprintf(
				'NS Tour Price: season_code mismatch for tour %s: {%s} not found in seasons. Aliases resolved: %d, Remaining mismatches: %d',
				$tour_id,
				implode( ', ', $invalid_codes ),
				$resolved_count,
				count( $invalid_codes )
			) );
		} else if ( $resolved_count > 0 ) {
			// 全て解決された場合のみ成功ログ
			error_log( sprintf(
				'NS Tour Price: All season_codes resolved for tour %s. Aliases resolved: %d',
				$tour_id,
				$resolved_count
			) );
		}

		// 結果をキャッシュ（1時間）
		set_transient( $cache_key, $invalid_codes, 3600 );

		return $invalid_codes;
	}

	public function clearCache() {
		// CSV データソースのキャッシュをクリア
		$csv_source = new NS_Tour_Price_DataSourceCsv();
		$csv_source->clearCache();

		// season_code 整合性チェックのキャッシュもクリア
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_ns_tour_price_season_validation_' ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_ns_tour_price_season_validation_' ) . '%'
			)
		);

		// season_aliases のキャッシュもクリア
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_ns_tour_price_season_aliases_' ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_ns_tour_price_season_aliases_' ) . '%'
			)
		);

		// インメモリキャッシュもクリア
		$this->aliases_cache = array();

		// 他のキャッシュもクリア
		delete_transient( 'ns_tour_price_calendar_cache' );
		
		do_action( 'ns_tour_price_cache_cleared' );
	}
}