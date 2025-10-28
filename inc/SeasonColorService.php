<?php
/**
 * Season Color Service - 端点固定シーズン色割当
 *
 * @package Andw_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Andw_Tour_Price_SeasonColorService {

	/**
	 * シーズンに色を割り当て（フェーズ1：末尾間引き）
	 *
	 * @param array $seasons_by_price 価格昇順のシーズンデータ [['code' => 'A', 'price' => 118000], ...]
	 * @param array $palette 色パレット [#ff0000, #00ff00, ...]
	 * @param string $prune_mode 間引きモード 'tail' | 'balanced'
	 * @return array { season_code => color } のマップ
	 */
	public function assignColors( $seasons_by_price, $palette, $prune_mode = 'tail' ) {
		if ( empty( $seasons_by_price ) || empty( $palette ) ) {
			return array();
		}

		$season_count = count( $seasons_by_price );
		$palette_count = count( $palette );

		// パレット不足の場合は利用可能色のみ使用
		if ( $season_count > $palette_count ) {
			error_log( sprintf( 
				'NS Tour Price: Insufficient palette colors (%d seasons, %d colors available)', 
				$season_count, $palette_count 
			) );
		}

		// 1件のみの場合
		if ( $season_count === 1 ) {
			$season = $seasons_by_price[0];
			return array( $season['code'] => $palette[0] );
		}

		// 2件の場合：端点固定
		if ( $season_count === 2 ) {
			return array(
				$seasons_by_price[0]['code'] => $palette[0], // 最安
				$seasons_by_price[1]['code'] => $palette[ $palette_count - 1 ], // 最高
			);
		}

		// 3件以上：端点固定 + 中間色選定
		return $this->assignColorsMultiple( $seasons_by_price, $palette, $prune_mode );
	}

	/**
	 * 3件以上のシーズンに色を割り当て
	 *
	 * @param array $seasons_by_price
	 * @param array $palette
	 * @param string $prune_mode
	 * @return array
	 */
	private function assignColorsMultiple( $seasons_by_price, $palette, $prune_mode ) {
		$season_count = count( $seasons_by_price );
		$palette_count = count( $palette );
		$available_colors = min( $season_count, $palette_count );

		// 使用する色インデックスを選定
		if ( $available_colors >= $season_count ) {
			// パレット十分：等間隔選択
			$color_indices = $this->selectEvenIndices( $season_count, $palette_count );
		} else {
			// パレット不足：間引き選択
			$color_indices = $this->selectPrunedIndices( $available_colors, $palette_count, $prune_mode );
		}

		// シーズンに色を割り当て
		$result = array();
		for ( $i = 0; $i < min( $season_count, count( $color_indices ) ); $i++ ) {
			$season_code = $seasons_by_price[ $i ]['code'];
			$color_index = $color_indices[ $i ];
			$result[ $season_code ] = $palette[ $color_index ];
		}

		return $result;
	}

	/**
	 * 等間隔で色インデックスを選択（パレット十分時）
	 *
	 * @param int $needed_count
	 * @param int $palette_count
	 * @return array
	 */
	private function selectEvenIndices( $needed_count, $palette_count ) {
		if ( $needed_count <= 1 ) {
			return array( 0 );
		}
		if ( $needed_count === 2 ) {
			return array( 0, $palette_count - 1 );
		}

		$indices = array();
		for ( $i = 0; $i < $needed_count; $i++ ) {
			$ratio = $i / ( $needed_count - 1 );
			$index = round( $ratio * ( $palette_count - 1 ) );
			$indices[] = $index;
		}

		return $indices;
	}

	/**
	 * 間引き選択で色インデックスを選定
	 *
	 * @param int $available_count 利用可能色数
	 * @param int $palette_count パレット総数
	 * @param string $prune_mode 'tail' | 'balanced'
	 * @return array
	 */
	private function selectPrunedIndices( $available_count, $palette_count, $prune_mode ) {
		if ( $available_count <= 1 ) {
			return array( 0 );
		}
		if ( $available_count === 2 ) {
			return array( 0, $palette_count - 1 );
		}

		// 端点は固定
		$selected = array( 0, $palette_count - 1 );
		$middle_needed = $available_count - 2;

		if ( $middle_needed > 0 ) {
			$middle_indices = $this->selectMiddleIndices( $palette_count, $middle_needed, $prune_mode );
			$selected = array_merge( array( 0 ), $middle_indices, array( $palette_count - 1 ) );
			sort( $selected );
		}

		return $selected;
	}

	/**
	 * 中間色インデックス選択
	 *
	 * @param int $palette_count
	 * @param int $middle_needed
	 * @param string $prune_mode
	 * @return array
	 */
	private function selectMiddleIndices( $palette_count, $middle_needed, $prune_mode ) {
		$middle_available = range( 1, $palette_count - 2 ); // 端点除外
		
		if ( count( $middle_available ) <= $middle_needed ) {
			return $middle_available;
		}

		if ( $prune_mode === 'balanced' ) {
			return $this->selectBalancedIndices( $middle_available, $middle_needed );
		} else {
			// フェーズ1: tail（末尾から間引き）
			return $this->selectTailIndices( $middle_available, $middle_needed );
		}
	}

	/**
	 * フェーズ1：末尾から間引き選択
	 *
	 * @param array $middle_available
	 * @param int $needed
	 * @return array
	 */
	private function selectTailIndices( $middle_available, $needed ) {
		// 右側（末尾）から優先的に削除
		$selected = array_slice( $middle_available, 0, $needed );
		return $selected;
	}

	/**
	 * フェーズ2：左右均等間引き選択
	 *
	 * @param array $middle_available
	 * @param int $needed
	 * @return array
	 */
	private function selectBalancedIndices( $middle_available, $needed ) {
		$available_count = count( $middle_available );
		$selected = array();

		// 中央から外側へ均等選択
		for ( $i = 0; $i < $needed; $i++ ) {
			$ratio = $i / ( $needed - 1 );
			$index = round( $ratio * ( $available_count - 1 ) );
			$selected[] = $middle_available[ $index ];
		}

		sort( $selected );
		return $selected;
	}

	/**
	 * シーズンデータを価格昇順でソート
	 *
	 * @param array $seasons [['code' => 'A', 'price' => 118000], ...]
	 * @return array
	 */
	public function sortSeasonsByPrice( $seasons ) {
		usort( $seasons, function( $a, $b ) {
			$price_a = intval( $a['price'] ?? 0 );
			$price_b = intval( $b['price'] ?? 0 );
			
			if ( $price_a === $price_b ) {
				// 価格同じ場合はコード順
				return strcmp( $a['code'] ?? '', $b['code'] ?? '' );
			}
			
			return $price_a <=> $price_b;
		});

		return $seasons;
	}

	/**
	 * 背景色に適した文字色を取得
	 *
	 * @param string $bg_color #ffffff形式
	 * @return string #000000 または #ffffff
	 */
	public function getTextColor( $bg_color ) {
		$hex = ltrim( $bg_color, '#' );
		
		if ( strlen( $hex ) !== 6 ) {
			return '#000000'; // フォールバック
		}

		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		// 輝度計算 (ITU-R BT.709)
		$brightness = ( $r * 0.299 + $g * 0.587 + $b * 0.114 );
		
		return ( $brightness > 128 ) ? '#000000' : '#ffffff';
	}

	/**
	 * デフォルトパレット（15色）を取得
	 *
	 * @return array
	 */
	public static function getDefaultPalette() {
		return array(
			'#e3f2fd', // Light Blue 50
			'#bbdefb', // Light Blue 100
			'#90caf9', // Light Blue 200
			'#64b5f6', // Light Blue 300
			'#42a5f5', // Light Blue 400
			'#2196f3', // Blue 500
			'#1e88e5', // Blue 600
			'#1976d2', // Blue 700
			'#1565c0', // Blue 800
			'#0d47a1', // Blue 900
			'#ff5722', // Deep Orange 500
			'#e91e63', // Pink 500
			'#9c27b0', // Purple 500
			'#673ab7', // Deep Purple 500
			'#3f51b5', // Indigo 500
		);
	}
}