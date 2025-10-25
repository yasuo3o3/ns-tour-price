<?php
/**
 * REST API Controllers Loader
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Controller Class
 */
class NS_Tour_Price_Rest {

	private static $controllers = array();

	/**
	 * Initialize REST controllers
	 */
	public static function init() {
		// Load controller classes
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Rest/Quote_Controller.php';
		require_once NS_TOUR_PRICE_PLUGIN_DIR . 'inc/Rest/Annual_Controller.php';

		// Instantiate controllers
		self::$controllers['quote'] = new NS_Tour_Price_Quote_Controller();
		self::$controllers['annual'] = new NS_Tour_Price_Annual_Controller();
	}

	/**
	 * Register all REST routes
	 */
	public static function register_routes() {
		// Ensure controllers are loaded
		if ( empty( self::$controllers ) ) {
			self::init();
		}

		// Register routes for each controller
		foreach ( self::$controllers as $controller ) {
			if ( method_exists( $controller, 'register_routes' ) ) {
				$controller->register_routes();
			}
		}
	}
}

// Initialize on load
NS_Tour_Price_Rest::init();