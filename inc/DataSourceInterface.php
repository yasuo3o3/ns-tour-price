<?php
/**
 * Data Source Interface
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface NS_Tour_Price_DataSourceInterface {

	/**
	 * ツアーのシーズン情報を取得
	 *
	 * @param string $tour_id ツアーID
	 * @return array シーズン情報の配列
	 */
	public function getSeasons( $tour_id );

	/**
	 * 全ツアーのシーズン情報を取得
	 *
	 * @return array 全シーズン情報の配列
	 */
	public function getAllSeasons();

	/**
	 * ベース価格情報を取得
	 *
	 * @param string $tour_id ツアーID
	 * @return array 価格情報の配列
	 */
	public function getBasePrices( $tour_id );

	/**
	 * 日別フラグ情報を取得
	 *
	 * @param string $tour_id ツアーID
	 * @return array 日別フラグの配列
	 */
	public function getDailyFlags( $tour_id );

	/**
	 * ソロフィー情報を取得
	 *
	 * @param string $tour_id ツアーID
	 * @return array ソロフィー情報の配列
	 */
	public function getSoloFees( $tour_id );

	/**
	 * データソースが利用可能かチェック
	 *
	 * @return bool 利用可能な場合はtrue
	 */
	public function isAvailable();

	/**
	 * データソースの名前を取得
	 *
	 * @return string データソース名
	 */
	public function getName();
}