<?php
/**
 * Plugin Name: NS Tour Price Calendar
 * Plugin URI: https://github.com/ns-tech/ns-tour_price
 * Description: Base44風のツアー価格カレンダーを表示するWordPressプラグイン。CSV二段階探索とヒートマップ表示に対応。
 * Version: 1.0.0
 * Author: NS Tech
 * Author URI: https://ns-tech.co.jp
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ns-tour_price
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
		load_plugin_textdomain( 'ns-tour_price', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		$this->includes();
		$this->hooks();
	}

	private function includes() {
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/DataSourceInterface.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/DataSourceCsv.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/DataSourceSheets.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Loader.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Repo.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/CalendarBuilder.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Heatmap.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Renderer.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Helpers.php';

		if ( is_admin() ) {
			require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Admin.php';
		}
	}

	private function hooks() {
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'init', array( $this, 'register_shortcodes' ) );
		add_action( 'init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		if ( is_admin() ) {
			new NS_Tour_Price_Admin();
		}
	}

	public function register_blocks() {
		register_block_type( NS_TOUR_PRICE_PLUGIN_DIR . 'blocks/price-calendar' );
	}

	public function register_shortcodes() {
		add_shortcode( 'tour_price', array( $this, 'shortcode_callback' ) );
	}

	public function register_rest_routes() {
		register_rest_route( 'ns-tour-price/v1', '/calendar', array(
			'methods' => 'GET',
			'callback' => array( $this, 'rest_calendar_callback' ),
			'permission_callback' => '__return_true',
			'args' => array(
				'tour' => array(
					'required' => false,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default' => 'A1',
				),
				'month' => array(
					'required' => false,
					'type' => 'string',
					'sanitize_callback' => 'sanitize_text_field',
					'default' => gmdate( 'Y-m' ),
				),
				'duration' => array(
					'required' => false,
					'type' => 'integer',
					'sanitize_callback' => 'absint',
					'default' => 4,
				),
				'heatmap' => array(
					'required' => false,
					'type' => 'boolean',
					'default' => true,
				),
				'confirmed_only' => array(
					'required' => false,
					'type' => 'boolean',
					'default' => false,
				),
				'show_legend' => array(
					'required' => false,
					'type' => 'boolean',
					'default' => true,
				),
			),
		) );
	}

	public function rest_calendar_callback( $request ) {
		$args = array(
			'tour' => $request->get_param( 'tour' ),
			'month' => $request->get_param( 'month' ),
			'duration' => $request->get_param( 'duration' ),
			'heatmap' => $request->get_param( 'heatmap' ),
			'confirmed_only' => $request->get_param( 'confirmed_only' ),
			'show_legend' => $request->get_param( 'show_legend' ),
		);

		$renderer = new NS_Tour_Price_Renderer();
		$html = $renderer->render( $args );

		// 前後月のナビゲーション情報も返す
		$prev_next = NS_Tour_Price_Helpers::month_prev_next( $args['month'] );

		return new WP_REST_Response( array(
			'success' => true,
			'html' => $html,
			'current_month' => $args['month'],
			'prev_month' => $prev_next['prev'],
			'next_month' => $prev_next['next'],
		), 200 );
	}

	public function shortcode_callback( $atts ) {
		$args = shortcode_atts( array(
			'tour' => 'A1',
			'month' => '',
			'duration' => 4,
			'show_legend' => true,
			'confirmed_only' => false,
			'heatmap' => true,
		), $atts );

		$args['show_legend'] = filter_var( $args['show_legend'], FILTER_VALIDATE_BOOLEAN );
		$args['confirmed_only'] = filter_var( $args['confirmed_only'], FILTER_VALIDATE_BOOLEAN );
		$args['heatmap'] = filter_var( $args['heatmap'], FILTER_VALIDATE_BOOLEAN );
		$args['duration'] = intval( $args['duration'] );

		// 月を解決（QueryString > 属性 > 現在月の優先順位）
		// 注意: CalendarBuilder でも実行されるが、統一性のためここでも適用
		$args['month'] = NS_Tour_Price_Helpers::resolve_month( $args['month'] );

		$renderer = new NS_Tour_Price_Renderer();
		return $renderer->render( $args );
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
	}

	public function enqueue_admin_assets( $hook ) {
		if ( 'tools_page_ns-tour-price' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ns-tour-price-admin',
			NS_TOUR_PRICE_PLUGIN_URL . 'assets/admin.css',
			array(),
			NS_TOUR_PRICE_VERSION
		);
	}

	public function activate() {
		$default_options = array(
			'data_source' => 'csv',
			'week_start' => 'sunday',
			'confirmed_badge_enabled' => false,
			'cache_expiry' => 3600,
			'heatmap_bins' => 7,
			'heatmap_mode' => 'quantile',
		);

		add_option( 'ns_tour_price_options', $default_options );

		$upload_dir = wp_upload_dir();
		$tour_price_dir = $upload_dir['basedir'] . '/ns-tour_price';

		if ( ! file_exists( $tour_price_dir ) ) {
			wp_mkdir_p( $tour_price_dir );
		}

		flush_rewrite_rules();
	}

	public function deactivate() {
		delete_transient( 'ns_tour_price_cache' );
		flush_rewrite_rules();
	}
}

function ns_tour_price_render_block( $attributes ) {
	$renderer = new NS_Tour_Price_Renderer();
	return $renderer->render( $attributes );
}

new NS_Tour_Price();