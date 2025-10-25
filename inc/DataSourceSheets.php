<?php
/**
 * Google Sheets Data Source Implementation (Stub)
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_DataSourceSheets implements NS_Tour_Price_DataSourceInterface {

	public function load(): void {
		// このデータソースは現在スタブであり、将来のバージョンで実装予定です。
		// インターフェースの契約を満たすために空のメソッドとして実装しています。
	}

	public function getSeasons( $tour_id ) {
		// Google Sheets実装は将来のバージョンで対応予定
		return array();
	}

	public function getAllSeasons() {
		// Google Sheets実装は将来のバージョンで対応予定
		return array();
	}

	public function getBasePrices( $tour_id ) {
		// Google Sheets実装は将来のバージョンで対応予定
		return array();
	}

	public function getDailyFlags( $tour_id ) {
		// Google Sheets実装は将来のバージョンで対応予定
		return array();
	}

	public function getSoloFees( $tour_id ) {
		// Google Sheets実装は将来のバージョンで対応予定
		return array();
	}

	public function getTourOptions( $tour_id = null ) {
		// Google Sheets実装は将来のバージョンで対応予定
		return array();
	}

	public function getTours() {
		// Google Sheets実装は将来のバージョンで対応予定
		return array();
	}

	public function isAvailable() {
		// Google Sheets実装は将来のバージョンで対応予定
		return false;
	}

	public function getName() {
		return __( 'Google Sheets', 'ns-tour-price' );
	}
}