<?php
/**
 * Annual Controller - 年間価格概要RESTコントローラー
 * 
 * /wp-json/ns-tour-price/v1/annual の GET/POST 対応
 * 包括的なエラーハンドリングとデバッグログを提供
 *
 * @package NS_Tour_Price
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Annual_Controller extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = 'ns-tour-price/v1';
		$this->rest_base = 'annual';
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_endpoint_args(),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true',
				'args'                => $this->get_endpoint_args(),
			),
		) );
	}

	public function get_endpoint_args() {
		return array(
			'tour' => array(
				'required' => false,
				'type' => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default' => 'A1',
				'description' => 'ツアーID',
			),
			'duration' => array(
				'required' => false,
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => 4,
				'description' => '日数',
			),
			'year' => array(
				'required' => false,
				'type' => 'integer',
				'sanitize_callback' => 'absint',
				'default' => gmdate( 'Y' ),
				'description' => '年',
			),
			'show' => array(
				'required' => false,
				'type' => 'boolean',
				'default' => true,
				'description' => '表示フラグ',
			),
		);
	}

	public function handle_request( $request ) {
		try {
			$this->log_debug( "リクエスト処理開始: " . $request->get_method() );
			
			// パラメータを複数ソースから取得
			$params = $this->extract_params( $request );
			$this->log_debug( "抽出パラメータ: " . wp_json_encode( $params ) );

			// show=falseの場合は空のHTMLを返す
			if ( ! $params['show'] ) {
				$this->log_debug( "show=false のため空レスポンスを返却" );
				return new WP_REST_Response( array(
					'success' => true,
					'html' => '',
				), 200 );
			}

			// AnnualBuilderでデータ構築
			$builder = new NS_Tour_Price_Annual_Builder();
			$annual_data = $builder->build( $params['tour'], $params['duration'], $params['year'] );

			$this->log_debug( "年間データ構築完了 - HTML長: " . strlen( $annual_data['html'] ) );

			return new WP_REST_Response( array(
				'success' => true,
				'html' => $annual_data['html'],
				'meta' => $annual_data['meta'],
			), 200 );

		} catch ( Exception $e ) {
			$this->log_error( "年間データ構築エラー: " . $e->getMessage(), $e );
			
			return new WP_REST_Response( array(
				'success' => false,
				'message' => $e->getMessage(),
				'error_code' => $e->getCode(),
			), 500 );
		}
	}

	private function extract_params( $request ) {
		$params = array();
		$defaults = $this->get_endpoint_args();

		// 基本パラメータを取得（WP_REST_Request経由）
		foreach ( $defaults as $key => $config ) {
			$params[$key] = $request->get_param( $key );
			if ( is_null( $params[$key] ) ) {
				$params[$key] = $config['default'];
			}
		}

		// JSONボディからの直接取得も試行（POST時）
		if ( $request->get_method() === 'POST' ) {
			$json_params = $request->get_json_params();
			if ( is_array( $json_params ) ) {
				foreach ( $defaults as $key => $config ) {
					if ( isset( $json_params[$key] ) ) {
						$raw_value = $json_params[$key];
						
						// 型変換とサニタイズ
						if ( $config['type'] === 'integer' ) {
							$params[$key] = absint( $raw_value );
						} elseif ( $config['type'] === 'boolean' ) {
							$params[$key] = filter_var( $raw_value, FILTER_VALIDATE_BOOLEAN );
						} elseif ( $config['type'] === 'string' ) {
							$params[$key] = sanitize_text_field( $raw_value );
						}
					}
				}
			}
		}

		// $_GET パラメータからの補完（直接URL呼び出し対応）
		foreach ( $defaults as $key => $config ) {
			if ( isset( $_GET[$key] ) && ! empty( $_GET[$key] ) ) {
				$raw_value = $_GET[$key];
				
				if ( $config['type'] === 'integer' ) {
					$params[$key] = absint( $raw_value );
				} elseif ( $config['type'] === 'boolean' ) {
					$params[$key] = filter_var( $raw_value, FILTER_VALIDATE_BOOLEAN );
				} elseif ( $config['type'] === 'string' ) {
					$params[$key] = sanitize_text_field( $raw_value );
				}
			}
		}

		return $params;
	}

	private function log_debug( $message ) {
		if ( WP_DEBUG && WP_DEBUG_LOG ) {
			error_log( "[NS Annual] DEBUG: " . $message );
		}
	}

	private function log_error( $message, $exception = null ) {
		$log_message = "[NS Annual] ERROR: " . $message;
		
		if ( $exception ) {
			$log_message .= " | ファイル: " . $exception->getFile() . ":" . $exception->getLine();
			$log_message .= " | スタック: " . $exception->getTraceAsString();
		}
		
		error_log( $log_message );
	}
}