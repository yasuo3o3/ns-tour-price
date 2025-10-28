<?php
/**
 * Season Color Map - シーズン色マップ統一管理
 *
 * @package Andw_Tour_Price
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Andw_Tour_Price_SeasonColorMap {

    private $repo;

    public function __construct() {
        $this->repo = Andw_Tour_Price_Repo::getInstance();
    }

    /**
     * シーズンカラーマップを生成
     *
     * @param string $tour_id ツアーID
     * @param int $year 年（未使用だが将来拡張のため）
     * @param int $duration 日数
     * @return array {
     *     'season_to_hp' => array,    // ['A'=>0, 'B'=>1, ..., 'K'=>10]
     *     'season_to_color' => array, // ['A'=>'#2d4f8e', 'B'=>'#336dbd', ...]
     *     'seasons' => array          // シーズンコード配列
     * }
     */
    public static function map( $tour_id, $year, $duration ) {
        $instance = new self();
        return $instance->generateMap( $tour_id, $duration );
    }

    /**
     * シーズンマップを生成（内部実装）
     *
     * @param string $tour_id ツアーID
     * @param int $duration 日数
     * @return array マップデータ
     */
    private function generateMap( $tour_id, $duration ) {
        // 直接CSVから価格データを読み込み
        $base_prices = $this->loadBasePricesDirectly( $tour_id, $duration );
        
        if ( empty( $base_prices ) ) {
            if ( WP_DEBUG ) {
                error_log( '[ns-tour] SeasonColorMap: No base prices found for ' . $tour_id . '/' . $duration );
            }
            return array(
                'season_to_hp' => array(),
                'season_to_color' => array(),
                'seasons' => array()
            );
        }

        // シーズンコードを昇順でソート
        uksort( $base_prices, 'strnatcmp' );
        
        $colors = $this->getColorPalette();
        $season_to_hp = array();
        $season_to_color = array();
        $seasons = array_keys( $base_prices );
        
        $index = 0;
        foreach ( $seasons as $season_code ) {
            $season_to_hp[ $season_code ] = $index;
            $season_to_color[ $season_code ] = $colors[ $index ] ?? '#cccccc';
            $index++;
        }

        return array(
            'season_to_hp' => $season_to_hp,
            'season_to_color' => $season_to_color,
            'seasons' => $seasons
        );
    }

    /**
     * 直接CSVからbase_pricesを読み込み
     *
     * @param string $tour_id ツアーID
     * @param int $duration 日数
     * @return array season_code => price のマップ
     */
    private function loadBasePricesDirectly( $tour_id, $duration ) {
        $prices = array();
        $csv_path = ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/base_prices.csv';
        
        if ( ! file_exists( $csv_path ) ) {
            return $prices;
        }
        
        $handle = fopen( $csv_path, 'r' );
        if ( ! $handle ) {
            return $prices;
        }
        
        fgetcsv( $handle ); // ヘッダー行をスキップ
        
        while ( ( $row = fgetcsv( $handle ) ) !== false ) {
            if ( count( $row ) >= 4 && 
                 trim( $row[0] ) === $tour_id && 
                 intval( $row[2] ) === intval( $duration ) ) {
                $season_code = trim( $row[1] );
                $price = intval( $row[3] );
                $prices[ $season_code ] = $price;
            }
        }
        
        fclose( $handle );
        return $prices;
    }

    /**
     * カラーパレットを取得
     *
     * @return array 色の配列
     */
    private function getColorPalette() {
		return self::get_palette();
	}

	/**
	 * カラーパレットを取得（静的メソッド）
	 * 管理画面設定を優先し、なければデフォルトを返す
	 *
	 * @return array 色の配列
	 */
	public static function get_palette() {
		$options = get_option( 'andw_tour_price_options', array() );

		// 管理画面で設定されたシーズンパレットを使用
		if ( ! empty( $options['season_palette'] ) && is_array( $options['season_palette'] ) ) {
			return $options['season_palette'];
		}

		// フォールバックとしてデフォルトのパレットを返す
		return array(
			'#e3f2fd', '#bbdefb', '#90caf9', '#64b5f6', '#42a5f5',
			'#2196f3', '#1e88e5', '#1976d2', '#1565c0', '#0d47a1',
			'#ff5722', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5'
		);
	}

    /**
     * シーズンコードからhp-classレベルを取得
     *
     * @param string $season_code シーズンコード
     * @param string $tour_id ツアーID
     * @param int $duration 日数
     * @return int|null hp-classレベル（0-10）またはnull
     */
    public static function getHpLevel( $season_code, $tour_id, $duration ) {
        $map = self::map( $tour_id, date('Y'), $duration );
        return $map['season_to_hp'][ $season_code ] ?? null;
    }

    /**
     * シーズンコードから色を取得
     *
     * @param string $season_code シーズンコード
     * @param string $tour_id ツアーID
     * @param int $duration 日数
     * @return string|null 色コードまたはnull
     */
    public static function getColor( $season_code, $tour_id, $duration ) {
        $map = self::map( $tour_id, date('Y'), $duration );
        return $map['season_to_color'][ $season_code ] ?? null;
    }
}