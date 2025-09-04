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
	private $statistics_logged = array(); // 統計ログ出力済みマーク

	public function __construct() {
		$this->loader = new NS_Tour_Price_Loader();
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
	 * 正規化・エイリアス統計ログを出力
	 */
	public function logNormalizationStatistics( $tour_id ) {
		// 一度のリクエストで一回だけ出力
		$log_key = 'normalization_' . $tour_id;
		if ( isset( $this->statistics_logged[ $log_key ] ) ) {
			return;
		}
		$this->statistics_logged[ $log_key ] = true;

		$seasons = $this->getSeasons( $tour_id );
		$prices = $this->getBasePrices( $tour_id );
		
		if ( empty( $seasons ) || empty( $prices ) ) {
			return;
		}

		// seasons の正規化後 season_code セット
		$season_codes = array();
		foreach ( $seasons as $season ) {
			$normalized = NS_Tour_Price_Helpers::normalize_season_code( $season['season_code'] );
			$season_codes[ $normalized ] = true;
		}

		// base_prices の正規化後 season_code セット
		$price_codes = array();
		foreach ( $prices as $price ) {
			$normalized = NS_Tour_Price_Helpers::normalize_season_code( $price['season_code'] );
			$price_codes[ $normalized ] = true;
		}

		$this->log( sprintf( 
			'NS Tour Price: seasons(normalized)={%s}', 
			implode( ',', array_keys( $season_codes ) ) 
		), 'info' );

		$this->log( sprintf( 
			'NS Tour Price: base_prices(normalized)={%s}', 
			implode( ',', array_keys( $price_codes ) ) 
		), 'info' );

		// DataSourceからロードサマリーも出力
		if ( $this->loader->isDataAvailable() ) {
			$source = $this->loader->getActiveSource();
			if ( method_exists( $source, 'logLoadSummary' ) ) {
				$source->logLoadSummary();
			}
		}
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

	public function getSoloFees( $tour_id ) {
		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$source = $this->loader->getActiveSource();
		return $source->getSoloFees( $tour_id );
	}

	public function getSoloFee( $tour_id, $duration_days ) {
		$solo_fees = $this->getSoloFees( $tour_id );

		foreach ( $solo_fees as $fee ) {
			if ( intval( $fee['duration_days'] ) === intval( $duration_days ) ) {
				return intval( $fee['solo_fee'] );
			}
		}

		return 0;
	}

	public function getTourOptions( $tour_id = null ) {
		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$source = $this->loader->getActiveSource();
		return $source->getTourOptions( $tour_id );
	}

	/**
	 * 年内の各日→価格（int）を返す。soloは含めない。
	 *
	 * @param string $tour_id
	 * @param int $duration
	 * @param int $year
	 * @return array ['Y-m-d' => price_int, ...]
	 */
	public function getYearlyPrices( $tour_id, $duration, $year ) {
		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$seasons_data = $this->getSeasons( $tour_id );
		$prices_data = $this->getBasePrices( $tour_id );
		$yearly_prices = array();

		// 年の範囲
		$year_start = sprintf( '%04d-01-01', $year );
		$year_end = sprintf( '%04d-12-31', $year );

		foreach ( $seasons_data as $season ) {
			// 日付正規化（/ → -）
			$date_start = strtr( trim( $season['date_start'] ?? '' ), array( '/' => '-' ) );
			$date_end = strtr( trim( $season['date_end'] ?? '' ), array( '/' => '-' ) );

			// DateTime作成（フォールバック付き）
			$start_date = date_create( $date_start );
			if ( ! $start_date ) {
				$start_date = date_create_from_format( 'Y-m-d', $date_start );
			}

			$end_date = date_create( $date_end );
			if ( ! $end_date ) {
				$end_date = date_create_from_format( 'Y-m-d', $date_end );
			}

			if ( ! $start_date || ! $end_date ) {
				continue;
			}

			// 年範囲での交差チェック
			$season_start = max( $start_date->format( 'Y-m-d' ), $year_start );
			$season_end = min( $end_date->format( 'Y-m-d' ), $year_end );

			if ( $season_start > $season_end ) {
				continue; // 年範囲外
			}

			// 該当期間の価格を取得
			$season_code = $season['season_code'];
			$price = $this->findPriceForSeason( $tour_id, $season_code, $duration, $prices_data );

			if ( $price > 0 ) {
				// 期間内の全日に価格を設定
				$current = date_create( $season_start );
				$end = date_create( $season_end );

				while ( $current <= $end ) {
					$date_key = $current->format( 'Y-m-d' );
					$yearly_prices[ $date_key ] = $price;
					$current->add( new DateInterval( 'P1D' ) );
				}
			}
		}

		return $yearly_prices;
	}

	/**
	 * season_code と duration に対応する価格を検索
	 */
	private function findPriceForSeason( $tour_id, $season_code, $duration, $prices_data ) {
		foreach ( $prices_data as $price ) {
			if ( $price['tour_id'] === $tour_id &&
				 $price['season_code'] === $season_code &&
				 intval( $price['duration_days'] ) === intval( $duration ) ) {
				return intval( $price['price'] );
			}
		}
		return 0;
	}

	public function getPriceForDate( $tour_id, $date, $duration ) {
		// 統計ログを出力（初回のみ）
		$this->logNormalizationStatistics( $tour_id );

		$seasons = $this->getSeasons( $tour_id );
		$prices = $this->getBasePrices( $tour_id );

		// データ不足チェック
		if ( empty( $seasons ) && empty( $prices ) ) {
			$this->log( sprintf( 
				'NS Tour Price: No price - reason=missing_csv_data for tour=%s date=%s duration=%d (both seasons.csv and base_prices.csv not loaded)', 
				$tour_id, $date, $duration 
			), 'warning' );
			return null;
		} else if ( empty( $seasons ) ) {
			$this->log( sprintf( 
				'NS Tour Price: No price - reason=missing_csv_seasons for tour=%s date=%s duration=%d (seasons.csv not loaded)', 
				$tour_id, $date, $duration 
			), 'warning' );
			return null;
		} else if ( empty( $prices ) ) {
			$this->log( sprintf( 
				'NS Tour Price: No price - reason=missing_csv_base_prices for tour=%s date=%s duration=%d (base_prices.csv not loaded)', 
				$tour_id, $date, $duration 
			), 'warning' );
			return null;
		}

		// エイリアスを読み込み
		$aliases = $this->loadSeasonAliases( $tour_id );

		// 日付形式チェック
		$date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( false === $date_obj ) {
			$this->log( sprintf( 
				'NS Tour Price: No price - reason=invalid_date_format for tour=%s date=%s duration=%d', 
				$tour_id, $date, $duration 
			), 'warning' );
			return null;
		}

		// 指定日付に適用されるシーズンを見つける
		$season_code = null;
		foreach ( $seasons as $season ) {
			$start_date = DateTime::createFromFormat( 'Y-m-d', $season['date_start'] );
			$end_date = DateTime::createFromFormat( 'Y-m-d', $season['date_end'] );

			if ( false !== $start_date && false !== $end_date &&
				 $date_obj >= $start_date && $date_obj <= $end_date ) {
				$season_code = $season['season_code'];
				break;
			}
		}

		// シーズン未一致チェック
		if ( null === $season_code ) {
			$season_ranges = array();
			foreach ( $seasons as $season ) {
				$season_ranges[] = sprintf( '%s(%s-%s)', $season['season_code'], $season['date_start'], $season['date_end'] );
			}
			$this->log( sprintf( 
				'NS Tour Price: No price - reason=no_season_match for tour=%s date=%s duration=%d (available seasons: %s)', 
				$tour_id, $date, $duration, implode( ', ', $season_ranges )
			), 'warning' );
			return null;
		}

		// seasons.csvのseason_codeを正規化
		$normalized_season_code = NS_Tour_Price_Helpers::normalize_season_code( $season_code );

		// シーズンコードと日数に一致する価格を見つける（正規化＆エイリアス対応）
		$matching_durations = array();
		$matching_seasons = array();
		foreach ( $prices as $price ) {
			$matching_durations[ $price['duration_days'] ] = true;
			$matching_seasons[ $price['season_code'] ] = true;

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

		// 価格未一致の詳細原因
		$this->log( sprintf( 
			'NS Tour Price: No price - reason=no_price_match for tour=%s date=%s duration=%d season=%s(normalized:%s) (available durations: %s, available seasons: %s)', 
			$tour_id, $date, $duration, $season_code, $normalized_season_code, 
			implode( ',', array_keys( $matching_durations ) ), 
			implode( ',', array_keys( $matching_seasons ) )
		), 'warning' );

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

		// エイリアス解決状況を詳細ログ
		if ( $resolved_count > 0 || ! empty( $invalid_codes ) ) {
			$this->log( sprintf(
				'NS Tour Price: season_code validation for tour %s: aliases_resolved=%d, final_mismatches=%d',
				$tour_id,
				$resolved_count,
				count( $invalid_codes )
			), 'info' );
		}

		// 不整合があればエラーログに詳細記録
		if ( ! empty( $invalid_codes ) ) {
			$this->log( sprintf(
				'NS Tour Price: season_code mismatch for tour %s: {%s} not found in seasons after normalization and alias resolution',
				$tour_id,
				implode( ', ', $invalid_codes )
			), 'error' );

			// エイリアス情報も出力
			if ( ! empty( $aliases ) ) {
				$alias_list = array();
				foreach ( $aliases as $alias => $target ) {
					$alias_list[] = $alias . '->' . $target;
				}
				$this->log( sprintf(
					'NS Tour Price: available aliases for tour %s: {%s}',
					$tour_id,
					implode( ', ', $alias_list )
				), 'info' );
			}
		}

		// 結果をキャッシュ（1時間）
		set_transient( $cache_key, $invalid_codes, 3600 );

		return $invalid_codes;
	}

	/**
	 * 指定ツアー・日数の全期間価格を取得（ヒートマップ・凡例用）
	 * 
	 * @param string $tour_id ツアーID
	 * @param int $duration 日数
	 * @return array 価格の数値配列（重複排除済み）
	 */
	public function getAllPricesFor( $tour_id, $duration ) {
		// transientキャッシュを確認
		$cache_key = sprintf( 'ns_tour_price_all_prices_%s_%d', $tour_id, $duration );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// データ取得
		$seasons = $this->getSeasons( $tour_id );
		$prices = $this->getBasePrices( $tour_id );
		
		if ( empty( $seasons ) || empty( $prices ) ) {
			$result = array();
			set_transient( $cache_key, $result, 3600 );
			return $result;
		}

		// エイリアス読み込み
		$aliases = $this->loadSeasonAliases( $tour_id );

		// solo fee取得
		$solo_fee = $this->getSoloFee( $tour_id, $duration );

		// 指定日数の価格を収集
		$collected_prices = array();
		foreach ( $prices as $price ) {
			if ( intval( $duration ) !== $price['duration_days'] ) {
				continue;
			}

			// base_pricesのseason_codeを正規化・エイリアス解決
			$normalized_price_code = NS_Tour_Price_Helpers::normalize_season_code( $price['season_code'] );
			$resolved_price_code = $this->resolveSeasonCodeAlias( $normalized_price_code, $aliases );

			// 対応するシーズンを検索
			$season_matched = false;
			foreach ( $seasons as $season ) {
				$normalized_season_code = NS_Tour_Price_Helpers::normalize_season_code( $season['season_code'] );
				
				if ( $resolved_price_code === $normalized_season_code ) {
					// ベース価格とソロ料金を加算
					$total_price = $price['price'] + $solo_fee;
					if ( $total_price > 0 ) {
						$collected_prices[] = $total_price;
					}
					$season_matched = true;
					break;
				}
			}
		}

		// ユニークな価格のみを昇順で返す
		$result = array_values( array_unique( $collected_prices ) );
		sort( $result, SORT_NUMERIC );

		// キャッシュに保存（1時間）
		set_transient( $cache_key, $result, 3600 );

		return $result;
	}

	/**
	 * 指定ツアーで利用可能な日数を取得
	 * 
	 * @param string $tour_id ツアーID
	 * @return int[] 昇順ユニークな日数配列
	 */
	public function getAvailableDurations( $tour_id ) {
		// transientキャッシュを確認
		$cache_key = sprintf( 'tpc_durations_%s', $tour_id );
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		// base_pricesから日数を取得
		$prices = $this->getBasePrices( $tour_id );
		
		if ( empty( $prices ) ) {
			$result = array();
			set_transient( $cache_key, $result, 3600 );
			return $result;
		}

		// duration_daysを収集してユニーク昇順ソート
		$durations = array();
		foreach ( $prices as $price ) {
			$duration = intval( $price['duration_days'] );
			if ( $duration > 0 ) {
				$durations[] = $duration;
			}
		}

		$result = array_values( array_unique( $durations ) );
		sort( $result, SORT_NUMERIC );

		// キャッシュに保存（1時間）
		set_transient( $cache_key, $result, 3600 );

		return $result;
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
		$this->statistics_logged = array(); // 統計ログも再出力可能にリセット

		// 他のキャッシュもクリア
		delete_transient( 'ns_tour_price_calendar_cache' );
		
		// HTMLキャッシュもクリア
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_tpc_html_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_tpc_html_' ) . '%'
			)
		);
		
		// getAllPricesFor のキャッシュもクリア
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_ns_tour_price_all_prices_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_ns_tour_price_all_prices_' ) . '%'
			)
		);

		// getAvailableDurations のキャッシュもクリア
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_tpc_durations_' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_tpc_durations_' ) . '%'
			)
		);
		
		// 年間ビューのキャッシュもクリア
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_tpc:annual:' ) . '%'
			)
		);
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_tpc:annual:' ) . '%'
			)
		);

		// キャッシュクリア実行をログ出力
		$this->log( 'NS Tour Price: All caches cleared, next CSV access will trigger fresh logs', 'info' );
		
		do_action( 'ns_tour_price_cache_cleared' );
	}

	/**
	 * 指定日のシーズンコードを取得
	 *
	 * @param string $tour_id ツアーID
	 * @param string $date YYYY-MM-DD形式の日付
	 * @return string シーズンコード（該当なしの場合は空文字）
	 */
	public function getSeasonForDate( $tour_id, $date ) {
		$seasons = $this->getSeasons( $tour_id );
		if ( empty( $seasons ) ) {
			return '';
		}

		foreach ( $seasons as $season ) {
			$start_date = NS_Tour_Price_Helpers::normalize_date( $season['date_start'] );
			$end_date = NS_Tour_Price_Helpers::normalize_date( $season['date_end'] );

			if ( false !== $start_date && false !== $end_date ) {
				if ( $date >= $start_date && $date <= $end_date ) {
					return NS_Tour_Price_Helpers::normalize_season_code( $season['season_code'] );
				}
			}
		}

		return '';
	}

	/**
	 * 全シーズンデータを取得
	 *
	 * @param string $tour_id ツアーID
	 * @return array シーズンデータの配列
	 */
	public function getAllSeasonsData( $tour_id ) {
		$seasons = $this->getSeasons( $tour_id );
		if ( empty( $seasons ) ) {
			return array();
		}

		$result = array();
		foreach ( $seasons as $season ) {
			$season_code = NS_Tour_Price_Helpers::normalize_season_code( $season['season_code'] );
			$result[] = array(
				'season_code' => $season_code,
				'season_label' => $season['season_label'] ?? $season_code,
				'date_start' => $season['date_start'],
				'date_end' => $season['date_end'],
			);
		}

		return $result;
	}

	/**
	 * シーズン別価格を取得
	 *
	 * @param string $tour_id ツアーID
	 * @param string $season_code シーズンコード
	 * @param int $duration 日数
	 * @return int|null 価格（見つからない場合はnull）
	 */
	public function getPriceForSeason( $tour_id, $season_code, $duration ) {
		$prices = $this->getBasePrices( $tour_id );
		if ( empty( $prices ) ) {
			return null;
		}

		$normalized_season = NS_Tour_Price_Helpers::normalize_season_code( $season_code );

		foreach ( $prices as $price_row ) {
			$row_season = NS_Tour_Price_Helpers::normalize_season_code( $price_row['season_code'] );
			$row_duration = intval( $price_row['duration_days'] );

			if ( $row_season === $normalized_season && $row_duration === $duration ) {
				return intval( $price_row['base_price'] );
			}
		}

		return null;
	}
}