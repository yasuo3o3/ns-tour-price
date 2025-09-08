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
	 * データソースを初期化（必要に応じて）
	 */
	public function load(): void;

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
	 * ツアーオプション情報を取得
	 *
	 * @param string|null $tour_id ツアーID（nullの場合は全件）
	 * @return array オプション情報の配列
	 */
	public function getTourOptions( $tour_id = null );

	/**
	 * ツアー情報を取得
	 *
	 * @return array ツアー情報の配列
	 */
	public function getTours();

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