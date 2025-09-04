<?php
/**
 * Calendar Builder
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_CalendarBuilder {

	private $repo;
	private $heatmap;

	public function __construct() {
		$this->repo = NS_Tour_Price_Repo::getInstance();
		$this->heatmap = new NS_Tour_Price_Heatmap();
	}

	public function buildCalendar( $args ) {
		$defaults = array(
			'tour' => 'A1',
			'month' => gmdate( 'Y-m' ),
			'duration' => 4,
			'heatmap' => true,
			'confirmed_only' => false,
			'show_legend' => true,
		);

		$args = wp_parse_args( $args, $defaults );
		$args = apply_filters( 'ns_tour_price_calendar_args', $args );

		if ( ! $this->repo->isDataAvailable() ) {
			return $this->buildErrorCalendar( __( 'データが見つかりません', 'ns-tour_price' ) );
		}

		$month_data = $this->generateMonthData( $args['month'] );
		if ( ! $month_data ) {
			return $this->buildErrorCalendar( __( '無効な月が指定されました', 'ns-tour_price' ) );
		}

		$calendar_days = $this->buildCalendarDays( $month_data, $args );
		$heatmap_classes = array();
		
		if ( $args['heatmap'] ) {
			$prices = $this->extractPrices( $calendar_days );
			$heatmap_classes = $this->heatmap->generateHeatmapClasses( $prices );
		}

		return array(
			'month_data' => $month_data,
			'days' => $calendar_days,
			'heatmap_classes' => $heatmap_classes,
			'args' => $args,
			'legend' => $this->buildLegend( $calendar_days, $heatmap_classes ),
		);
	}

	private function generateMonthData( $month_str ) {
		$date = DateTime::createFromFormat( 'Y-m', $month_str );
		if ( false === $date ) {
			return false;
		}

		$year = intval( $date->format( 'Y' ) );
		$month = intval( $date->format( 'm' ) );

		$first_day = new DateTime( sprintf( '%d-%02d-01', $year, $month ) );
		$last_day = clone $first_day;
		$last_day->modify( 'last day of this month' );

		$options = get_option( 'ns_tour_price_options', array() );
		$week_start = $options['week_start'] ?? 'sunday';
		$week_start_num = ( 'monday' === $week_start ) ? 1 : 0;

		return array(
			'year' => $year,
			'month' => $month,
			'month_name' => $this->getMonthName( $month ),
			'first_day' => $first_day,
			'last_day' => $last_day,
			'days_in_month' => intval( $last_day->format( 'd' ) ),
			'first_weekday' => intval( $first_day->format( 'w' ) ),
			'week_start' => $week_start_num,
		);
	}

	private function buildCalendarDays( $month_data, $args ) {
		$days = array();
		$current_date = clone $month_data['first_day'];
		$options = get_option( 'ns_tour_price_options', array() );
		$confirmed_badge_enabled = $options['confirmed_badge_enabled'] ?? false;

		for ( $day = 1; $day <= $month_data['days_in_month']; $day++ ) {
			$date_str = $current_date->format( 'Y-m-d' );
			$price = $this->repo->getPriceForDate( $args['tour'], $date_str, $args['duration'] );
			$is_confirmed = $confirmed_badge_enabled ? $this->repo->isConfirmedDate( $args['tour'], $date_str ) : false;
			$note = $confirmed_badge_enabled ? $this->repo->getDateNote( $args['tour'], $date_str ) : '';

			// confirmed_onlyがtrueの場合、確定日のみ表示
			$should_display = ! $args['confirmed_only'] || $is_confirmed;

			$day_data = array(
				'day' => $day,
				'date' => $date_str,
				'weekday' => intval( $current_date->format( 'w' ) ),
				'price' => $price,
				'formatted_price' => $this->formatPrice( $price ),
				'is_confirmed' => $is_confirmed,
				'note' => $note,
				'is_today' => $date_str === gmdate( 'Y-m-d' ),
				'is_weekend' => in_array( intval( $current_date->format( 'w' ) ), array( 0, 6 ) ),
				'should_display' => $should_display,
				'has_price' => null !== $price && $price > 0,
			);

			$days[] = $day_data;
			$current_date->modify( '+1 day' );
		}

		return $days;
	}

	private function buildErrorCalendar( $message ) {
		return array(
			'error' => true,
			'message' => $message,
			'month_data' => null,
			'days' => array(),
			'heatmap_classes' => array(),
			'legend' => array(),
		);
	}

	private function extractPrices( $calendar_days ) {
		$prices = array();
		foreach ( $calendar_days as $day ) {
			if ( $day['has_price'] && $day['should_display'] ) {
				$prices[] = $day['price'];
			}
		}
		return $prices;
	}

	private function formatPrice( $price ) {
		if ( null === $price || $price <= 0 ) {
			return '';
		}

		$formatted = number_format( $price );
		return apply_filters( 'ns_tour_price_price_format', '¥' . $formatted, $price );
	}

	private function getMonthName( $month ) {
		$names = array(
			1 => __( '1月', 'ns-tour_price' ),
			2 => __( '2月', 'ns-tour_price' ),
			3 => __( '3月', 'ns-tour_price' ),
			4 => __( '4月', 'ns-tour_price' ),
			5 => __( '5月', 'ns-tour_price' ),
			6 => __( '6月', 'ns-tour_price' ),
			7 => __( '7月', 'ns-tour_price' ),
			8 => __( '8月', 'ns-tour_price' ),
			9 => __( '9月', 'ns-tour_price' ),
			10 => __( '10月', 'ns-tour_price' ),
			11 => __( '11月', 'ns-tour_price' ),
			12 => __( '12月', 'ns-tour_price' ),
		);

		return $names[ $month ] ?? (string) $month;
	}

	private function buildLegend( $calendar_days, $heatmap_classes ) {
		if ( empty( $heatmap_classes ) ) {
			return array();
		}

		$prices_with_classes = array();
		foreach ( $calendar_days as $day ) {
			if ( $day['has_price'] && $day['should_display'] ) {
				$class_num = $heatmap_classes[ $day['price'] ] ?? 0;
				$prices_with_classes[ $class_num ][] = $day['price'];
			}
		}

		$legend = array();
		for ( $i = 0; $i <= 9; $i++ ) {
			if ( isset( $prices_with_classes[ $i ] ) ) {
				$prices = $prices_with_classes[ $i ];
				$legend[] = array(
					'class' => 'hp-' . $i,
					'min_price' => min( $prices ),
					'max_price' => max( $prices ),
					'formatted_min' => $this->formatPrice( min( $prices ) ),
					'formatted_max' => $this->formatPrice( max( $prices ) ),
				);
			}
		}

		return $legend;
	}

	public function getWeekdayHeaders( $week_start = 0 ) {
		$days = array(
			__( '日', 'ns-tour_price' ),
			__( '月', 'ns-tour_price' ),
			__( '火', 'ns-tour_price' ),
			__( '水', 'ns-tour_price' ),
			__( '木', 'ns-tour_price' ),
			__( '金', 'ns-tour_price' ),
			__( '土', 'ns-tour_price' ),
		);

		if ( 1 === $week_start ) {
			// 月曜始まり
			$days = array_slice( $days, 1 ) + array( $days[0] );
		}

		return $days;
	}

	public function getCalendarGrid( $calendar_data ) {
		if ( isset( $calendar_data['error'] ) ) {
			return array( array( array( 'error' => true, 'message' => $calendar_data['message'] ) ) );
		}

		$month_data = $calendar_data['month_data'];
		$days = $calendar_data['days'];
		$week_start = $month_data['week_start'];

		$grid = array();
		$current_week = array();

		// 最初の週の空セルを追加
		$first_weekday = $month_data['first_weekday'];
		if ( 1 === $week_start ) {
			$first_weekday = ( 0 === $first_weekday ) ? 6 : $first_weekday - 1;
		}

		for ( $i = 0; $i < $first_weekday; $i++ ) {
			$current_week[] = array( 'empty' => true );
		}

		// 日付を週ごとに配置
		foreach ( $days as $day ) {
			$current_week[] = $day;

			if ( count( $current_week ) === 7 ) {
				$grid[] = $current_week;
				$current_week = array();
			}
		}

		// 最後の週を埋める
		if ( ! empty( $current_week ) ) {
			while ( count( $current_week ) < 7 ) {
				$current_week[] = array( 'empty' => true );
			}
			$grid[] = $current_week;
		}

		return $grid;
	}
}