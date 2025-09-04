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

	/**
	 * season_code の整合性をチェック
	 * base_prices にあって seasons に無いコードの配列を返す
	 *
	 * @param string $tour_id ツアーID
	 * @return array 不整合な season_code の配列
	 */
	public function validateSeasonCodes( $tour_id ) {
		// キャッシュキーを生成
		$cache_key = 'ns_tour_price_season_validation_' . $tour_id;
		$cached = get_transient( $cache_key );
		
		if ( false !== $cached ) {
			return $cached;
		}

		if ( ! $this->loader->isDataAvailable() ) {
			return array();
		}

		$seasons = $this->getSeasons( $tour_id );
		$prices = $this->getBasePrices( $tour_id );

		if ( empty( $seasons ) || empty( $prices ) ) {
			return array();
		}

		// seasons.csv から有効な season_code を取得
		$valid_season_codes = array();
		foreach ( $seasons as $season ) {
			$valid_season_codes[] = $season['season_code'];
		}
		$valid_season_codes = array_unique( $valid_season_codes );

		// base_prices.csv で使用されている season_code を取得
		$used_season_codes = array();
		foreach ( $prices as $price ) {
			$used_season_codes[] = $price['season_code'];
		}
		$used_season_codes = array_unique( $used_season_codes );

		// base_prices にあって seasons に無いコードを検出
		$invalid_codes = array_diff( $used_season_codes, $valid_season_codes );

		// 結果をキャッシュ（1時間）
		set_transient( $cache_key, $invalid_codes, 3600 );

		// 不整合があればログに記録
		if ( ! empty( $invalid_codes ) ) {
			error_log( sprintf(
				'NS Tour Price: season_code mismatch for tour %s: %s',
				$tour_id,
				implode( ', ', $invalid_codes )
			) );
		}

		return $invalid_codes;
	}

	public function clearCache() {
		// CSV データソースのキャッシュをクリア
		$csv_source = new NS_Tour_Price_DataSourceCsv();
		$csv_source->clearCache();

		// season_code 整合性チェックのキャッシュもクリア
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_ns_tour_price_season_validation_' ) . '%'
			)
		);

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_timeout_ns_tour_price_season_validation_' ) . '%'
			)
		);

		// 他のキャッシュもクリア
		delete_transient( 'ns_tour_price_calendar_cache' );
		
		do_action( 'ns_tour_price_cache_cleared' );
	}
}