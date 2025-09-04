<?php
/**
 * Quote Controller - 見積API
 * 
 * /wp-json/ns-tour-price/v1/quote で日付選択時の料金計算
 *
 * @package NS_Tour_Price
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Quote_Controller extends WP_REST_Controller {

	private $repo;

	public function __construct() {
		$this->namespace = 'ns-tour-price/v1';
		$this->rest_base = 'quote';
		$this->repo = NS_Tour_Price_Repo::getInstance();
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => array( $this, 'handle_quote_request' ),
			'permission_callback' => '__return_true',
			'args'                => $this->get_endpoint_args(),
		) );
	}

	public function get_endpoint_args() {
		return array(
			'tour' => array(
				'required' => true,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description' => 'ツアーID',
			),
			'date' => array(
				'required' => true,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'description' => '出発日（YYYY-MM-DD）',
			),
			'duration' => array(
				'required' => true,
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'description' => '日数',
			),
			'pax' => array(
				'required' => false,
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 1,
				'description' => '参加人数',
			),
		);
	}

	public function handle_quote_request( $request ) {
		try {
			$tour = $request->get_param( 'tour' );
			$date = $request->get_param( 'date' );
			$duration = $request->get_param( 'duration' );
			$pax = $request->get_param( 'pax' );

			// 日付形式チェック
			if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'reason' => 'invalid_date_format',
					'message' => '日付はYYYY-MM-DD形式で指定してください',
				), 400 );
			}

			// シーズンコード取得
			$season_code = $this->repo->getSeasonForDate( $tour, $date );
			if ( empty( $season_code ) ) {
				return new WP_REST_Response( array(
					'success' => false,
					'reason' => 'no_season',
					'message' => '指定日のシーズン情報が見つかりません',
				), 404 );
			}

			// 基本料金取得
			$base_price = $this->repo->getPriceForSeason( $tour, $season_code, $duration );
			if ( $base_price === null ) {
				return new WP_REST_Response( array(
					'success' => false,
					'reason' => 'no_base_price',
					'message' => '該当するツアー料金が見つかりません',
				), 404 );
			}

			// ソロフィー取得（1名の場合のみ）
			$solo_fee = 0;
			if ( $pax === 1 ) {
				$solo_fee = $this->getSoloFee( $tour, $duration );
			}

			// 合計計算
			$total = $base_price * $pax + $solo_fee;

			return new WP_REST_Response( array(
				'success' => true,
				'base_price' => $base_price,
				'solo_fee' => $solo_fee,
				'pax' => $pax,
				'total' => $total,
				'season_code' => $season_code,
			), 200 );

		} catch ( Exception $e ) {
			return new WP_REST_Response( array(
				'success' => false,
				'reason' => 'server_error',
				'message' => $e->getMessage(),
			), 500 );
		}
	}

	/**
	 * ソロフィーを取得
	 */
	private function getSoloFee( $tour, $duration ) {
		$solo_fees = $this->repo->getSoloFees( $tour );
		if ( empty( $solo_fees ) ) {
			return 0;
		}

		foreach ( $solo_fees as $fee ) {
			if ( intval( $fee['duration_days'] ?? 0 ) === $duration ) {
				return intval( $fee['solo_fee'] ?? 0 );
			}
		}

		return 0;
	}
}