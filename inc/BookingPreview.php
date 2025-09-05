<?php
/**
 * Booking Preview Builder - 旅行内容選択フォーム
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_BookingPreview {

	private $repo;

	public function __construct() {
		$this->repo = NS_Tour_Price_Repo::getInstance();
	}

	/**
	 * 予約プレビューフォームを構築
	 *
	 * @param array $args
	 * @return array
	 */
	public function build( $args ) {
		$defaults = array(
			'tour' => 'A1',
			'date' => gmdate( 'Y-m-d' ),
			'duration' => 4,
			'pax' => 1,
			'departure' => '成田',
			'options' => array(),
			'show_debug' => false,
		);
		$args = wp_parse_args( $args, $defaults );

		// データ取得
		$season_info = $this->getSeasonInfo( $args['tour'], $args['date'] );
		$available_durations = $this->getAvailableDurations( $args['tour'] );
		$departure_options = $this->getDepartureOptions();
		$tour_options = $this->getTourOptions( $args['tour'] );
		error_log("BookingPreview tour_options for {$args['tour']}: " . print_r($tour_options, true));
		$price_calculation = $this->calculatePrices( $args );

		$template = $this->loadTemplate( 'booking-preview.php', array(
			'args' => $args,
			'season_info' => $season_info,
			'available_durations' => $available_durations,
			'departure_options' => $departure_options,
			'tour_options' => $tour_options,
			'price_calculation' => $price_calculation,
		) );

		return array(
			'html' => $template,
			'meta' => array(
				'tour' => $args['tour'],
				'date' => $args['date'],
				'season_code' => $season_info['season_code'] ?? '',
				'duration' => $args['duration'],
				'total' => $price_calculation['total'],
			)
		);
	}

	/**
	 * 指定日のシーズン情報取得
	 */
	private function getSeasonInfo( $tour, $date ) {
		$seasons_data = $this->repo->getSeasons();
		
		foreach ( $seasons_data as $season ) {
			if ( $season['tour_id'] === $tour &&
				 $date >= $season['date_start'] && 
				 $date <= $season['date_end'] ) {
				return array(
					'season_code' => $season['season_code'],
					'season_label' => $season['label'] ?? $season['season_code'],
					'date_start' => $season['date_start'],
					'date_end' => $season['date_end'],
				);
			}
		}

		return array(
			'season_code' => '',
			'season_label' => '未設定',
			'date_start' => '',
			'date_end' => '',
		);
	}

	/**
	 * 利用可能な日数を取得
	 */
	private function getAvailableDurations( $tour ) {
		$prices_data = $this->repo->getBasePrices();
		$durations = array();

		foreach ( $prices_data as $price ) {
			if ( $price['tour_id'] === $tour ) {
				$duration = intval( $price['duration_days'] );
				if ( ! in_array( $duration, $durations ) ) {
					$durations[] = $duration;
				}
			}
		}

		sort( $durations );
		return $durations;
	}

	/**
	 * 出発地オプション取得
	 */
	private function getDepartureOptions() {
		return array(
			'成田' => '成田空港',
			'羽田' => '羽田空港', 
			'関西' => '関西国際空港',
			'中部' => '中部国際空港',
			'新千歳' => '新千歳空港',
			'福岡' => '福岡空港',
		);
	}

	/**
	 * ツアーオプション取得
	 */
	public function getTourOptions( $tour ) {
		error_log("BookingPreview::getTourOptions called for tour: $tour");
		$options_data = $this->repo->getTourOptions( $tour );
		error_log("BookingPreview got options_data: " . print_r($options_data, true));
		$tour_options = array();

		foreach ( $options_data as $option ) {
			$tour_options[] = array(
				'option_id' => $option['option_id'],
				'option_label' => $option['option_label'],
				'price_min' => intval( $option['price_min'] ),
				'price_max' => intval( $option['price_max'] ),
				'description' => $option['description'] ?? '',
				'affects_total' => filter_var( $option['affects_total'] ?? true, FILTER_VALIDATE_BOOLEAN ),
			);
		}

		return $tour_options;
	}

	/**
	 * 価格計算
	 */
	public function calculatePrices( $args ) {
		$tour = $args['tour'];
		$date = $args['date'];
		$duration = intval( $args['duration'] );
		$pax = intval( $args['pax'] );
		$selected_options = (array) ( $args['options'] ?? array() );

		// シーズンコード取得
		$season_info = $this->getSeasonInfo( $tour, $date );
		$season_code = $season_info['season_code'];

		// 基本料金取得
		$base_price = $this->getBasePrice( $tour, $season_code, $duration );
		$base_total = $base_price * $pax;

		// ソロフィー計算
		$solo_fee = 0;
		if ( $pax === 1 ) {
			$solo_fee = $this->getSoloFee( $tour, $duration );
		}

		// オプション料金計算
		$option_total = 0;
		$tour_options = $this->getTourOptions( $tour );
		
		foreach ( $selected_options as $option_id ) {
			foreach ( $tour_options as $option ) {
				if ( $option['option_id'] === $option_id && $option['affects_total'] ) {
					// 概算として最小料金を使用
					$option_total += $option['price_min'];
					break;
				}
			}
		}

		$total = $base_total + $solo_fee + $option_total;

		return array(
			'base_price' => $base_price,
			'base_total' => $base_total,
			'solo_fee' => $solo_fee,
			'option_total' => $option_total,
			'total' => $total,
			'formatted' => array(
				'base_price' => '¥' . number_format( $base_price ),
				'base_total' => '¥' . number_format( $base_total ),
				'solo_fee' => '¥' . number_format( $solo_fee ),
				'option_total' => '¥' . number_format( $option_total ),
				'total' => '¥' . number_format( $total ),
			),
		);
	}

	/**
	 * 基本料金取得
	 */
	private function getBasePrice( $tour, $season_code, $duration ) {
		$prices_data = $this->repo->getBasePrices();

		foreach ( $prices_data as $price ) {
			if ( $price['tour_id'] === $tour &&
				 $price['season_code'] === $season_code &&
				 intval( $price['duration_days'] ) === $duration ) {
				return intval( $price['price'] );
			}
		}

		return 0;
	}

	/**
	 * ソロフィー取得
	 */
	private function getSoloFee( $tour, $duration ) {
		$solo_fees = $this->repo->getSoloFees();

		foreach ( $solo_fees as $fee ) {
			if ( $fee['tour_id'] === $tour &&
				 intval( $fee['duration_days'] ) === $duration ) {
				return intval( $fee['solo_fee'] );
			}
		}

		return 0;
	}

	/**
	 * テンプレート読み込み
	 */
	private function loadTemplate( $template_name, $vars = array() ) {
		$template_path = NS_TOUR_PRICE_PLUGIN_DIR . 'templates/' . $template_name;
		
		if ( ! file_exists( $template_path ) ) {
			return '<div class="tpc-error">テンプレートが見つかりません: ' . esc_html( $template_name ) . '</div>';
		}

		extract( $vars );
		ob_start();
		include $template_path;
		return ob_get_clean();
	}

	/**
	 * Ajax用価格計算API
	 */
	public function ajaxCalculatePrice( $request ) {
		$tour = sanitize_text_field( $request->get_param( 'tour' ) ?: 'A1' );
		$date = sanitize_text_field( $request->get_param( 'date' ) ?: gmdate( 'Y-m-d' ) );
		$duration = absint( $request->get_param( 'duration' ) ?: 4 );
		$pax = absint( $request->get_param( 'pax' ) ?: 1 );
		$options = (array) ( $request->get_param( 'options' ) ?: array() );

		$args = array(
			'tour' => $tour,
			'date' => $date,
			'duration' => $duration,
			'pax' => $pax,
			'options' => $options,
		);

		$calculation = $this->calculatePrices( $args );

		return rest_ensure_response( array(
			'success' => true,
			'data' => $calculation,
		) );
	}
}