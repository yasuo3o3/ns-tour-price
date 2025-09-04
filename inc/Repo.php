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

	public function __construct() {
		$this->loader = new NS_Tour_Price_Loader();
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

	public function getPriceForDate( $tour_id, $date, $duration ) {
		$seasons = $this->getSeasons( $tour_id );
		$prices = $this->getBasePrices( $tour_id );

		if ( empty( $seasons ) || empty( $prices ) ) {
			return null;
		}

		// 指定日付に適用されるシーズンを見つける
		$season_code = null;
		$date_obj = DateTime::createFromFormat( 'Y-m-d', $date );
		
		if ( false === $date_obj ) {
			return null;
		}

		foreach ( $seasons as $season ) {
			$start_date = DateTime::createFromFormat( 'Y-m-d', $season['date_start'] );
			$end_date = DateTime::createFromFormat( 'Y-m-d', $season['date_end'] );

			if ( false !== $start_date && false !== $end_date &&
				 $date_obj >= $start_date && $date_obj <= $end_date ) {
				$season_code = $season['season_code'];
				break;
			}
		}

		if ( null === $season_code ) {
			return null;
		}

		// シーズンコードと日数に一致する価格を見つける
		foreach ( $prices as $price ) {
			if ( $price['season_code'] === $season_code && 
				 $price['duration_days'] === intval( $duration ) ) {
				return $price['price'];
			}
		}

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

	public function clearCache() {
		// CSV データソースのキャッシュをクリア
		$csv_source = new NS_Tour_Price_DataSourceCsv();
		$csv_source->clearCache();

		// 他のキャッシュもクリア
		delete_transient( 'ns_tour_price_calendar_cache' );
		
		do_action( 'ns_tour_price_cache_cleared' );
	}
}