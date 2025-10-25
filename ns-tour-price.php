<?php
/**
 * Plugin Name: NS Tour Price Calendar
 * Description: ツアー価格カレンダーを表示するWordPressプラグイン。CSV二段階探索とヒートマップ表示に対応。
 * Version: 1.0.1
 * Author: Netservice
 * Author URI: https://netservice.jp/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ns-tour-price
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.4
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NS_TOUR_PRICE_VERSION', '1.0.0' );
define( 'NS_TOUR_PRICE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'NS_TOUR_PRICE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'NS_TOUR_PRICE_PLUGIN_FILE', __FILE__ );

class NS_Tour_Price {

	public function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	public function init() {
		load_plugin_textdomain( 'ns-tour-price', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		$this->includes();
		$this->hooks();
	}

	private function includes() {
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Helpers.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Repo.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Renderer.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/AnnualBuilder.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Admin.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Rest.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Migration.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'blocks/price-calendar/index.php';
	}

	private function hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( 'NS_Tour_Price_Rest', 'register_routes' ) );
		add_action( 'wp_ajax_ns_tour_price_clear_cache', array( 'NS_Tour_Price_Admin', 'ajax_clear_cache' ) );
		add_action( 'wp_ajax_ns_tour_price_test_data', array( 'NS_Tour_Price_Admin', 'ajax_test_data' ) );
		add_action( 'wp_ajax_ns_tour_price_upload_csv', array( 'NS_Tour_Price_Admin', 'ajax_upload_csv' ) );
		add_action( 'wp_ajax_ns_tour_price_delete_csv', array( 'NS_Tour_Price_Admin', 'ajax_delete_csv' ) );

		// ショートコード登録
		add_shortcode( 'ns_tour_price_calendar', array( $this, 'shortcode_calendar' ) );

		// WordPress管理画面初期化
		new NS_Tour_Price_Admin();
	}

	public function enqueue_frontend_assets() {
		wp_enqueue_style(
			'ns-tour-price-frontend',
			NS_TOUR_PRICE_PLUGIN_URL . 'assets/style.css',
			array(),
			NS_TOUR_PRICE_VERSION
		);

		wp_enqueue_script(
			'ns-tour-price-navigation',
			NS_TOUR_PRICE_PLUGIN_URL . 'assets/navigation.js',
			array(),
			NS_TOUR_PRICE_VERSION,
			true
		);

		wp_enqueue_script(
			'ns-tour-price-booking-preview',
			NS_TOUR_PRICE_PLUGIN_URL . 'assets/booking-preview.js',
			array(),
			NS_TOUR_PRICE_VERSION,
			true
		);

		// REST API用のlocalize
		wp_localize_script( 'ns-tour-price-navigation', 'nsTourPriceAjax', array(
			'restUrl' => rest_url( 'ns-tour-price/v1/' ),
			'nonce'   => wp_create_nonce( 'wp_rest' )
		));
	}

	public function enqueue_editor_assets() {
		wp_enqueue_style(
			'ns-tour-price-editor',
			NS_TOUR_PRICE_PLUGIN_URL . 'assets/editor.css',
			array(),
			NS_TOUR_PRICE_VERSION
		);
	}

	public function enqueue_admin_assets( $hook ) {
		// 管理画面の特定ページでのみ読み込み
		if ( strpos( $hook, 'ns-tour-price' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'ns-tour-price-admin',
			NS_TOUR_PRICE_PLUGIN_URL . 'assets/admin.css',
			array(),
			NS_TOUR_PRICE_VERSION
		);
	}

	public function shortcode_calendar( $atts ) {
		$args = shortcode_atts( array(
			'tour' => 'A1',
			'month' => '',
			'duration' => 4,
			'heatmap' => true,
			'show_legend' => true,
			'confirmed_only' => false,
			'show_booking_panel' => false,
		), $atts );

		// boolean 値の正規化
		$args['heatmap'] = wp_validate_boolean( $args['heatmap'] );
		$args['show_legend'] = wp_validate_boolean( $args['show_legend'] );
		$args['confirmed_only'] = wp_validate_boolean( $args['confirmed_only'] );
		$args['show_booking_panel'] = wp_validate_boolean( $args['show_booking_panel'] );
		$args['duration'] = absint( $args['duration'] );

		$renderer = new NS_Tour_Price_Renderer();
		return $renderer->render( $args );
	}

	// REST API エンドポイント
	public function rest_calendar_callback( $request ) {
		$tour = $request->get_param( 'tour' ) ?: 'A1';
		$month = $request->get_param( 'month' );

		if ( empty( $month ) ) {
			$month = NS_Tour_Price_Helpers::getSmartDefaultMonth( $tour );
		}

		$duration = absint( $request->get_param( 'duration' ) ?: 4 );
		$heatmap = wp_validate_boolean( $request->get_param( 'heatmap' ) );
		$show_legend = wp_validate_boolean( $request->get_param( 'show_legend' ) );
		$confirmed_only = wp_validate_boolean( $request->get_param( 'confirmed_only' ) );

		$renderer = new NS_Tour_Price_Renderer();
		$output = $renderer->render( array(
			'tour' => $tour,
			'month' => $month,
			'duration' => $duration,
			'heatmap' => $heatmap,
			'show_legend' => $show_legend,
			'confirmed_only' => $confirmed_only,
			'show_booking_panel' => false,
		));

		return array(
			'success' => true,
			'html' => $output,
			'tour' => $tour,
			'month' => $month,
			'duration' => $duration
		);
	}

	public function activate() {
		// アクティベーション時の処理
		$migration = new NS_Tour_Price_Migration();
		$migration->check_and_migrate();

		// キャッシュクリア
		NS_Tour_Price_Repo::clear_cache();

		flush_rewrite_rules();
	}

	public function deactivate() {
		// ディアクティベーション時の処理
		NS_Tour_Price_Repo::clear_cache();
		flush_rewrite_rules();
	}
}

// プラグイン初期化
new NS_Tour_Price();

// WordPress REST API の追加登録（クラス外）
add_action( 'rest_api_init', function() {
	register_rest_route( 'ns-tour-price/v1', '/calendar', array(
		'methods' => 'GET',
		'callback' => array( new NS_Tour_Price(), 'rest_calendar_callback' ),
		'args' => array(
			'tour' => array(
				'default' => 'A1',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'month' => array(
				'default' => null,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'duration' => array(
				'default' => 4,
				'sanitize_callback' => 'absint',
			),
			'heatmap' => array(
				'default' => true,
				'sanitize_callback' => 'wp_validate_boolean',
			),
			'show_legend' => array(
				'default' => true,
				'sanitize_callback' => 'wp_validate_boolean',
			),
			'confirmed_only' => array(
				'default' => false,
				'sanitize_callback' => 'wp_validate_boolean',
			),
		),
		'permission_callback' => '__return_true',
	));

	register_rest_route( 'ns-tour-price/v1', '/annual', array(
		'methods' => 'GET',
		'callback' => function( $request ) {
			$tour = $request->get_param( 'tour' ) ?: 'A1';
			$duration = absint( $request->get_param( 'duration' ) ?: 4 );
			$year = absint( $request->get_param( 'year' ) ?: gmdate( 'Y' ) );

			$annual_builder = new NS_Tour_Price_AnnualBuilder();
			$output = $annual_builder->render( array(
				'tour' => $tour,
				'duration' => $duration,
				'year' => $year,
				'show_mini_calendars' => true,
				'show_season_table' => true,
			));

			return array(
				'success' => true,
				'html' => $output,
				'tour' => $tour,
				'duration' => $duration,
				'year' => $year
			);
		},
		'args' => array(
			'tour' => array(
				'default' => 'A1',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'duration' => array(
				'default' => 4,
				'sanitize_callback' => 'absint',
			),
			'year' => array(
				'default' => gmdate( 'Y' ),
				'sanitize_callback' => 'absint',
			),
		),
		'permission_callback' => '__return_true',
	));
});