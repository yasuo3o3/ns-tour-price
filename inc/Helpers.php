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
}