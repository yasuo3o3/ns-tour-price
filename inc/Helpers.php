<?php
/**
 * Helper Functions
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Helpers {

	public static function sanitizeCalendarArgs( $args ) {
		$defaults = array(
			'tour' => 'A1',
			'month' => gmdate( 'Y-m' ),
			'duration' => 4,
			'heatmap' => true,
			'confirmed_only' => false,
			'show_legend' => true,
		);

		$sanitized = array();

		foreach ( $defaults as $key => $default_value ) {
			if ( isset( $args[ $key ] ) ) {
				$sanitized[ $key ] = self::sanitizeCalendarArg( $key, $args[ $key ] );
			} else {
				$sanitized[ $key ] = $default_value;
			}
		}

		return $sanitized;
	}

	private static function sanitizeCalendarArg( $key, $value ) {
		switch ( $key ) {
			case 'tour':
				return sanitize_text_field( $value );

			case 'month':
				$month = sanitize_text_field( $value );
				if ( empty( $month ) ) {
					return gmdate( 'Y-m' );
				}
				// YYYY-MM形式のチェック
				if ( preg_match( '/^\d{4}-\d{2}$/', $month ) ) {
					return $month;
				}
				return gmdate( 'Y-m' );

			case 'duration':
				$duration = intval( $value );
				return max( 1, min( 30, $duration ) ); // 1-30日の範囲

			case 'heatmap':
			case 'confirmed_only':
			case 'show_legend':
				return filter_var( $value, FILTER_VALIDATE_BOOLEAN );

			default:
				return sanitize_text_field( $value );
		}
	}

	public static function validateMonth( $month_str ) {
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $month_str ) ) {
			return false;
		}

		list( $year, $month ) = explode( '-', $month_str );
		$year = intval( $year );
		$month = intval( $month );

		return checkdate( $month, 1, $year );
	}

	public static function generateMonthOptions( $start_months_back = 12, $end_months_forward = 12 ) {
		$options = array();
		$current = new DateTime();

		// 過去の月を追加
		for ( $i = $start_months_back; $i > 0; $i-- ) {
			$date = clone $current;
			$date->modify( "-{$i} month" );
			$options[] = array(
				'value' => $date->format( 'Y-m' ),
				'label' => $date->format( 'Y年n月' ),
				'is_past' => true,
			);
		}

		// 現在月を追加
		$options[] = array(
			'value' => $current->format( 'Y-m' ),
			'label' => $current->format( 'Y年n月' ) . __( '（今月）', 'ns-tour_price' ),
			'is_current' => true,
		);

		// 未来の月を追加
		for ( $i = 1; $i <= $end_months_forward; $i++ ) {
			$date = clone $current;
			$date->modify( "+{$i} month" );
			$options[] = array(
				'value' => $date->format( 'Y-m' ),
				'label' => $date->format( 'Y年n月' ),
				'is_future' => true,
			);
		}

		return $options;
	}

	public static function generateTourOptions() {
		$repo = NS_Tour_Price_Repo::getInstance();
		
		if ( ! $repo->isDataAvailable() ) {
			return array();
		}

		// とりあえず基本的なツアーIDリストを提供
		// 実際の実装では、CSVから動的に取得するか設定で管理
		$tour_ids = apply_filters( 'ns_tour_price_available_tours', array(
			'A1' => __( 'ツアーA1', 'ns-tour_price' ),
			'B2' => __( 'ツアーB2', 'ns-tour_price' ),
			'C3' => __( 'ツアーC3', 'ns-tour_price' ),
		) );

		$options = array();
		foreach ( $tour_ids as $id => $label ) {
			$options[] = array(
				'value' => $id,
				'label' => $label,
			);
		}

		return $options;
	}

	public static function generateDurationOptions( $min = 1, $max = 14 ) {
		$options = array();
		for ( $i = $min; $i <= $max; $i++ ) {
			$options[] = array(
				'value' => $i,
				'label' => sprintf( __( '%d日間', 'ns-tour_price' ), $i ),
			);
		}
		return $options;
	}

	public static function formatPrice( $price, $include_tax = true ) {
		if ( null === $price || $price < 0 ) {
			return __( '設定なし', 'ns-tour_price' );
		}

		$formatted = number_format( $price );
		$result = '¥' . $formatted;

		if ( $include_tax && $price > 0 ) {
			$result .= __( '（税込）', 'ns-tour_price' );
		}

		return apply_filters( 'ns_tour_price_price_format', $result, $price );
	}

	public static function getPriceColor( $price, $min_price, $max_price ) {
		if ( null === $price || $min_price >= $max_price ) {
			return '#cccccc';
		}

		$range = $max_price - $min_price;
		$normalized = ( $price - $min_price ) / $range;

		// RGB補間で色を計算
		if ( $normalized <= 0.5 ) {
			// 青から緑へ
			$ratio = $normalized * 2;
			$r = intval( 26 + ( 142 - 26 ) * $ratio );
			$g = intval( 115 + ( 205 - 115 ) * $ratio );
			$b = intval( 232 + ( 0 - 232 ) * $ratio );
		} else {
			// 緑から赤へ
			$ratio = ( $normalized - 0.5 ) * 2;
			$r = intval( 142 + ( 215 - 142 ) * $ratio );
			$g = intval( 205 + ( 48 - 205 ) * $ratio );
			$b = intval( 0 + ( 39 - 0 ) * $ratio );
		}

		return sprintf( '#%02x%02x%02x', $r, $g, $b );
	}

	public static function debugCalendarData( $calendar_data, $verbose = false ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$debug_info = array(
			'has_error' => isset( $calendar_data['error'] ),
			'month' => $calendar_data['month_data']['year'] ?? null . '-' . 
					  ( $calendar_data['month_data']['month'] ?? null ),
			'total_days' => count( $calendar_data['days'] ?? array() ),
			'days_with_prices' => 0,
			'price_range' => array(),
			'heatmap_levels' => array(),
		);

		if ( ! $debug_info['has_error'] ) {
			$prices = array();
			foreach ( $calendar_data['days'] as $day ) {
				if ( $day['has_price'] ) {
					$debug_info['days_with_prices']++;
					$prices[] = $day['price'];
				}
			}

			if ( ! empty( $prices ) ) {
				$debug_info['price_range'] = array(
					'min' => min( $prices ),
					'max' => max( $prices ),
					'avg' => array_sum( $prices ) / count( $prices ),
				);
			}

			if ( ! empty( $calendar_data['heatmap_classes'] ) ) {
				$debug_info['heatmap_levels'] = array_count_values( 
					array_values( $calendar_data['heatmap_classes'] )
				);
			}
		}

		error_log( 'NS Tour Price Calendar Debug: ' . wp_json_encode( $debug_info ) );

		if ( $verbose && ! $debug_info['has_error'] ) {
			error_log( 'NS Tour Price Calendar Full Data: ' . wp_json_encode( $calendar_data ) );
		}
	}

	public static function getCacheKey( $prefix, $args ) {
		$key_data = array(
			'tour' => $args['tour'],
			'month' => $args['month'],
			'duration' => $args['duration'],
			'confirmed_only' => $args['confirmed_only'],
		);

		return $prefix . '_' . md5( wp_json_encode( $key_data ) );
	}

	public static function isValidTourId( $tour_id ) {
		return preg_match( '/^[A-Z0-9_-]+$/i', $tour_id );
	}

	public static function getCurrentWeekStart() {
		$options = get_option( 'ns_tour_price_options', array() );
		return ( 'monday' === ( $options['week_start'] ?? 'sunday' ) ) ? 1 : 0;
	}

	public static function isConfirmedBadgeEnabled() {
		$options = get_option( 'ns_tour_price_options', array() );
		return ! empty( $options['confirmed_badge_enabled'] );
	}

	/**
	 * 月を解決する（QueryString > 属性 > 現在月の優先順位）
	 *
	 * @param string $attr_month ブロック/ショートコード属性の月
	 * @return string YYYY-MM形式の月文字列
	 */
	public static function resolve_month( $attr_month, $tour_id = 'A1' ) {
		$available_months = self::getAvailableMonths( $tour_id );

		// ① $_GET['tpc_month'] が最優先（データ存在チェック付き）
		if ( ! empty( $_GET['tpc_month'] ) ) {
			$get_month = sanitize_text_field( wp_unslash( $_GET['tpc_month'] ) );
			if ( self::validateMonth( $get_month ) && in_array( $get_month, $available_months, true ) ) {
				return $get_month;
			}
		}

		// ② 属性の month（データ存在チェック付き）
		if ( ! empty( $attr_month ) ) {
			$attr_month = sanitize_text_field( $attr_month );
			if ( self::validateMonth( $attr_month ) && in_array( $attr_month, $available_months, true ) ) {
				return $attr_month;
			}
		}

		// ③ スマートデフォルト月（旅行予約の特性を考慮）
		return self::getSmartDefaultMonth( $tour_id );
	}

	/**
	 * 日数を解決する（QueryString > 属性 > 最小値の優先順位）
	 *
	 * @param int|null $attr_duration ブロック/ショートコード属性の日数
	 * @param int[] $available 利用可能な日数配列（昇順）
	 * @return int 解決された日数
	 */
	public static function resolve_duration( $attr_duration, $available ) {
		if ( empty( $available ) ) {
			return 4; // デフォルト値
		}

		// ① $_GET['tpc_duration'] が最優先
		if ( ! empty( $_GET['tpc_duration'] ) ) {
			$get_duration = intval( $_GET['tpc_duration'] );
			if ( in_array( $get_duration, $available, true ) ) {
				return $get_duration;
			}
		}

		// ② 属性の duration
		if ( ! empty( $attr_duration ) ) {
			$attr_duration = intval( $attr_duration );
			if ( in_array( $attr_duration, $available, true ) ) {
				return $attr_duration;
			}
		}

		// ③ 利用可能な最小値（フォールバック）
		return $available[0];
	}

	/**
	 * 指定月の前月・翌月を取得
	 *
	 * @param string $yyyymm YYYY-MM形式の月
	 * @return array ['prev' => 'YYYY-MM', 'next' => 'YYYY-MM']
	 */
	public static function month_prev_next( $yyyymm ) {
		if ( ! self::validateMonth( $yyyymm ) ) {
			$yyyymm = gmdate( 'Y-m' );
		}

		$date = DateTime::createFromFormat( 'Y-m', $yyyymm );
		if ( false === $date ) {
			$date = new DateTime();
		}

		$prev_date = clone $date;
		$prev_date->modify( '-1 month' );

		$next_date = clone $date;
		$next_date->modify( '+1 month' );

		return array(
			'prev' => $prev_date->format( 'Y-m' ),
			'next' => $next_date->format( 'Y-m' ),
		);
	}

	/**
	 * season_code を正規化する
	 * 1. 前後空白除去
	 * 2. 全角英数→半角（NFKC正規化）
	 * 3. 大文字化
	 *
	 * @param string $code season_code
	 * @return string 正規化後の season_code
	 */
	public static function normalize_season_code( $code ) {
		if ( ! is_string( $code ) ) {
			return '';
		}

		// 1. 前後空白除去
		$normalized = trim( $code );

		// 2. 全角英数→半角（NFKC正規化）
		if ( class_exists( 'Normalizer' ) ) {
			$normalized = Normalizer::normalize( $normalized, Normalizer::FORM_KC );
		}

		// 3. 大文字化
		$normalized = mb_strtoupper( $normalized, 'UTF-8' );

		return $normalized;
	}

	/**
	 * 日付文字列を YYYY-MM-DD 形式に正規化する
	 * 対応フォーマット: YYYY/M/D, YYYY/MM/DD, YYYY-MM-D, YYYY-MM-DD など
	 * 全角数字も半角化してから解析
	 *
	 * @param string $date_str 日付文字列
	 * @return string|false 正規化された日付文字列（YYYY-MM-DD）、失敗時はfalse
	 */
	public static function normalize_date( $date_str ) {
		if ( ! is_string( $date_str ) ) {
			return false;
		}

		// 前後空白除去
		$date_str = trim( $date_str );
		if ( empty( $date_str ) ) {
			return false;
		}

		// 全角数字→半角（NFKC正規化 or 手動変換）
		if ( class_exists( 'Normalizer' ) ) {
			$date_str = Normalizer::normalize( $date_str, Normalizer::FORM_KC );
		} else {
			// 手動で全角数字を半角数字に変換
			$fullwidth_nums = array( '０', '１', '２', '３', '４', '５', '６', '７', '８', '９' );
			$halfwidth_nums = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
			$date_str = str_replace( $fullwidth_nums, $halfwidth_nums, $date_str );
			
			// 全角記号も半角に変換
			$fullwidth_symbols = array( '－', '／', '．' );
			$halfwidth_symbols = array( '-', '/', '.' );
			$date_str = str_replace( $fullwidth_symbols, $halfwidth_symbols, $date_str );
		}

		// 複数のフォーマットパターンを試行
		$patterns = array(
			'Y-m-d',      // 2025-4-15, 2025-04-15
			'Y/m/d',      // 2025/4/15, 2025/04/15
			'Y.m.d',      // 2025.4.15, 2025.04.15
			'Y-n-j',      // 2025-4-5
			'Y/n/j',      // 2025/4/5
			'Y.n.j',      // 2025.4.5
		);

		foreach ( $patterns as $pattern ) {
			$date = DateTime::createFromFormat( $pattern, $date_str );
			if ( false !== $date && $date->format( $pattern ) === $date_str ) {
				// 年が4桁でない場合は無効として扱う
				$year = (int) $date->format( 'Y' );
				if ( $year >= 1900 && $year <= 2100 ) {
					return $date->format( 'Y-m-d' );
				}
			}
		}

		// DateTime::createFromFormatで失敗した場合、strtotimeで最後のトライ
		// ただし、明らかに2桁年のパターンは除外する
		if ( ! preg_match( '/^\d{2}[-\/\.]\d{1,2}[-\/\.]\d{1,2}$/', $date_str ) ) {
			$timestamp = strtotime( $date_str );
			if ( false !== $timestamp ) {
				$date = new DateTime();
				$date->setTimestamp( $timestamp );
				
				// 妥当な日付範囲内かチェック（1900-2100年）
				$year = (int) $date->format( 'Y' );
				if ( $year >= 1900 && $year <= 2100 ) {
					return $date->format( 'Y-m-d' );
				}
			}
		}

		return false;
	}

	/**
	 * 日付範囲の妥当性をチェック
	 *
	 * @param string $start_date YYYY-MM-DD形式の開始日
	 * @param string $end_date YYYY-MM-DD形式の終了日
	 * @return bool 開始日 <= 終了日 の場合true
	 */
	public static function validate_date_range( $start_date, $end_date ) {
		if ( false === $start_date || false === $end_date ) {
			return false;
		}

		$start = DateTime::createFromFormat( 'Y-m-d', $start_date );
		$end = DateTime::createFromFormat( 'Y-m-d', $end_date );

		if ( false === $start || false === $end ) {
			return false;
		}

		return $start <= $end;
	}

	/**
	 * 常に「?key=value...」形式の相対クエリを返す。
	 * RESTコンテキストでも /wp-json を含む絶対URLにならない。
	 *
	 * @param array $args キー=>値の配列
	 * @return string 例: "?tpc_month=2025-08&tpc_duration=7"
	 */
	public static function build_relative_query( array $args ): string {
		// boolean は "1"/"0" に、他は文字列化
		$normalized = array();
		foreach ( $args as $key => $value ) {
			if ( is_bool( $value ) ) {
				$normalized[ $key ] = $value ? '1' : '0';
			} else {
				$normalized[ $key ] = (string) $value;
			}
		}

		$query_string = http_build_query( $normalized, '', '&', PHP_QUERY_RFC3986 );
		return $query_string === '' ? '?' : '?' . $query_string; // 最低でも "?" を返す（リンク構文上安全）
	}

	/**
	 * スマートなデフォルト月を取得（旅行予約の特性を考慮）
	 *
	 * @param string $tour_id ツアーID
	 * @return string YYYY-MM形式の月
	 */
	public static function getSmartDefaultMonth( $tour_id = 'A1' ) {
		$available_months = self::getAvailableMonths( $tour_id );

		if ( empty( $available_months ) ) {
			// データがない場合は現在月をフォールバック
			return gmdate( 'Y-m' );
		}

		$current_month = gmdate( 'Y-m' );
		$next_month = gmdate( 'Y-m', strtotime( '+1 month' ) );

		// 1. 現在月にデータがあるか確認（最優先）
		if ( in_array( $current_month, $available_months, true ) ) {
			return $current_month;
		}

		// 2. 翌月にデータがあるか確認
		if ( in_array( $next_month, $available_months, true ) ) {
			return $next_month;
		}

		// 3. 翌月以降で最初にデータがある月を探す
		$future_month = self::findNextAvailableMonth( $tour_id, $next_month );
		if ( $future_month ) {
			return $future_month;
		}

		// 4. 翌月以降にデータがない場合、過去で最新の月を探す
		$past_month = self::findLatestPastMonth( $tour_id, $current_month );
		if ( $past_month ) {
			return $past_month;
		}

		// 5. 全てのデータが現在月より後の場合、最初の利用可能月
		return $available_months[0];
	}

	/**
	 * ツアーの利用可能月一覧を取得
	 *
	 * @param string $tour_id ツアーID
	 * @return array YYYY-MM形式の月配列（昇順）
	 */
	public static function getAvailableMonths( $tour_id ) {
		static $cache = array();

		if ( isset( $cache[ $tour_id ] ) ) {
			return $cache[ $tour_id ];
		}

		$repo = NS_Tour_Price_Repo::getInstance();
		$seasons = $repo->getSeasons( $tour_id );

		$months = array();

		foreach ( $seasons as $season ) {
			$start_date = self::normalize_date( $season['date_start'] );
			$end_date = self::normalize_date( $season['date_end'] );

			if ( $start_date && $end_date ) {
				$current = DateTime::createFromFormat( 'Y-m-d', $start_date );
				$end = DateTime::createFromFormat( 'Y-m-d', $end_date );

				if ( $current && $end ) {
					while ( $current <= $end ) {
						$month_key = $current->format( 'Y-m' );
						if ( ! in_array( $month_key, $months, true ) ) {
							$months[] = $month_key;
						}
						$current->modify( '+1 month' );
					}
				}
			}
		}

		// 昇順にソート
		sort( $months );

		$cache[ $tour_id ] = $months;
		return $months;
	}

	/**
	 * 指定月以降で最初の利用可能月を探す
	 *
	 * @param string $tour_id ツアーID
	 * @param string $from_month YYYY-MM形式の開始月
	 * @return string|null 見つかった月、またはnull
	 */
	public static function findNextAvailableMonth( $tour_id, $from_month ) {
		$available_months = self::getAvailableMonths( $tour_id );

		foreach ( $available_months as $month ) {
			if ( $month >= $from_month ) {
				return $month;
			}
		}

		return null;
	}

	/**
	 * 指定月以前で最新の利用可能月を探す
	 *
	 * @param string $tour_id ツアーID
	 * @param string $before_month YYYY-MM形式の基準月
	 * @return string|null 見つかった月、またはnull
	 */
	public static function findLatestPastMonth( $tour_id, $before_month ) {
		$available_months = self::getAvailableMonths( $tour_id );

		// 降順で探索
		$past_months = array_reverse( $available_months );

		foreach ( $past_months as $month ) {
			if ( $month < $before_month ) {
				return $month;
			}
		}

		return null;
	}

	/**
	 * 利用可能月のキャッシュをクリア
	 */
	public static function clearAvailableMonthsCache() {
		// 静的キャッシュはリクエスト終了時に自動クリアされるため
		// 必要に応じて将来的にTransientキャッシュに移行可能
	}
}