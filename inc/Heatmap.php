<?php
/**
 * Heatmap Generator
 *
 * @package Andw_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Andw_Tour_Price_Heatmap {

	private $levels = 10;

	/**
	 * 分位（quantile）ベースのビンを構築
	 *
	 * @param array $prices 価格配列
	 * @param int $bins ビン数（5/7/10）
	 * @param string $mode 'quantile' | 'linear'
	 * @return array ビン境界の配列
	 */
	public function buildBuckets( $prices, $bins = 7, $mode = 'quantile' ) {
		if ( empty( $prices ) ) {
			return array();
		}

		$unique_prices = array_values( array_unique( $prices ) );
		sort( $unique_prices );

		if ( 'linear' === $mode ) {
			// 従来の線形ビン
			$min_price = min( $unique_prices );
			$max_price = max( $unique_prices );
			$step = ( $max_price - $min_price ) / $bins;
			
			$buckets = array();
			for ( $i = 0; $i < $bins; $i++ ) {
				$buckets[] = $min_price + ( $step * ( $i + 1 ) );
			}
			return $buckets;
		}

		// quantileベースのビン
		$count = count( $unique_prices );
		$buckets = array();
		
		for ( $i = 1; $i <= $bins; $i++ ) {
			$percentile = $i / $bins;
			$index = max( 0, min( $count - 1, floor( $percentile * $count ) ) );
			$buckets[] = $unique_prices[ $index ];
		}

		// 重複除去して昇順ソート
		$buckets = array_values( array_unique( $buckets ) );
		sort( $buckets );

		return $buckets;
	}

	/**
	 * ビン境界を使用したヒートマップクラス生成
	 *
	 * @param array $prices 価格配列
	 * @param array $buckets ビン境界配列
	 * @param int $max_levels 最大レベル数（既定10）
	 * @return array 価格=>レベルのマッピング
	 */
	public function generateHeatmapClassesWithBuckets( $prices, $buckets, $max_levels = 10 ) {
		if ( empty( $prices ) || empty( $buckets ) ) {
			return array();
		}

		$classes = array();
		$bucket_count = count( $buckets );
		
		foreach ( $prices as $price ) {
			$level = 0;
			
			// 価格がどのビンに属するか判定
			for ( $i = 0; $i < $bucket_count; $i++ ) {
				if ( $price <= $buckets[ $i ] ) {
					$level = $i;
					break;
				}
			}
			
			// max_levelsに正規化
			if ( $bucket_count > 0 ) {
				$level = min( $max_levels - 1, floor( $level * $max_levels / $bucket_count ) );
			}
			
			$classes[ $price ] = $level;
		}

		return $classes;
	}

	public function generateHeatmapClasses( $prices, $global_min = null, $global_max = null ) {
		if ( empty( $prices ) ) {
			return array();
		}

		$unique_prices = array_unique( $prices );
		
		// 外部から指定されたmin/maxを使用、なければ渡された価格から計算
		if ( null !== $global_min && null !== $global_max ) {
			$min_price = $global_min;
			$max_price = $global_max;
		} else {
			if ( count( $unique_prices ) === 1 ) {
				// 全て同じ価格の場合は中間色
				$single_price = $unique_prices[0];
				return array( $single_price => 5 );
			}
			$min_price = min( $prices );
			$max_price = max( $prices );
		}

		$range = $max_price - $min_price;
		if ( 0 === $range ) {
			// 全て同じ価格または単一価格の場合は中間色
			$classes = array();
			foreach ( $unique_prices as $price ) {
				$classes[ $price ] = 5;
			}
			return $classes;
		}

		$classes = array();
		foreach ( $prices as $price ) {
			$normalized = ( $price - $min_price ) / $range;
			$level = min( 9, max( 0, floor( $normalized * $this->levels ) ) );
			$classes[ $price ] = $level;
		}

		return $classes;
	}

	/**
	 * ヒートマップ凡例データを生成（新パレット対応）
	 *
	 * @param array $prices 価格配列
	 * @param array $classes 価格=>レベルのマッピング
	 * @param int $bins 実際のビン数（動的パレット生成用）
	 * @return array 凡例データ
	 */
	public function getHeatmapLegendData( $prices, $classes, $bins = null ) {
		if ( empty( $prices ) || empty( $classes ) ) {
			return array();
		}

		// ビン数が指定されていない場合は従来の10色を使用
		if ( null === $bins ) {
			$bins = $this->levels;
		}

		$levels = array();
		for ( $i = 0; $i < $this->levels; $i++ ) {
			$levels[ $i ] = array();
		}

		foreach ( $prices as $price ) {
			if ( isset( $classes[ $price ] ) ) {
				$level = $classes[ $price ];
				$levels[ $level ][] = $price;
			}
		}

		// 管理画面設定の色リストから実際のビン数に合わせた色を取得
		$colors = $this->getHeatmapColors();
		$adjusted_colors = self::adjustColorsForBins( $colors, $bins );

		$legend_data = array();
		foreach ( $levels as $level => $level_prices ) {
			if ( ! empty( $level_prices ) ) {
				// 色は調整済み色配列から取得
				$color_index = min( $level, count( $adjusted_colors ) - 1 );
				$color = $adjusted_colors[ $color_index ] ?? '#cccccc';
				
				$legend_data[] = array(
					'level' => $level,
					'class' => 'hp-' . $level,
					'min_price' => min( $level_prices ),
					'max_price' => max( $level_prices ),
					'count' => count( $level_prices ),
					'color' => $color,
				);
			}
		}

		return $legend_data;
	}

	/**
	 * 色リストとビン数に応じて適切な色配列を生成
	 * - 色数 > ビン数: 等間隔サンプリング
	 * - 色数 < ビン数: 色をループして使用
	 * - 色数 == ビン数: そのまま使用
	 *
	 * @param array $colors 色配列（安→高）
	 * @param int $bins 実際に使う段数
	 * @return array bins 個の色（安→高）
	 */
	public static function adjustColorsForBins( array $colors, int $bins ): array {
		$color_count = count( $colors );
		
		if ( $bins <= 1 ) {
			return array( $colors[0] ?? '#cccccc' );
		}
		
		if ( $color_count === $bins ) {
			// 色数とビン数が一致：そのまま使用
			return $colors;
		}
		
		if ( $color_count > $bins ) {
			// 色数 > ビン数：等間隔サンプリング
			return self::sampleColors( $colors, $bins );
		} else {
			// 色数 < ビン数：色をループして使用
			return self::loopColors( $colors, $bins );
		}
	}

	/**
	 * 等間隔サンプリングで色を抽出
	 *
	 * @param array $colors 色配列
	 * @param int $bins 目標ビン数
	 * @return array サンプリングされた色配列
	 */
	private static function sampleColors( array $colors, int $bins ): array {
		$color_count = count( $colors );
		$out = array();
		
		for ( $j = 0; $j < $bins; $j++ ) {
			$idx = (int) round( $j * ( $color_count - 1 ) / ( $bins - 1 ) );
			$out[] = $colors[ $idx ];
		}
		
		// 重複除去（保守的処理）
		$out = array_values( array_unique( $out ) );
		while ( count( $out ) < $bins ) {
			$out[] = end( $colors );
		}
		
		return $out;
	}

	/**
	 * 色をループして必要なビン数まで拡張
	 *
	 * @param array $colors 色配列
	 * @param int $bins 目標ビン数
	 * @return array ループ拡張された色配列
	 */
	private static function loopColors( array $colors, int $bins ): array {
		$color_count = count( $colors );
		$out = array();
		
		for ( $i = 0; $i < $bins; $i++ ) {
			$out[] = $colors[ $i % $color_count ];
		}
		
		return $out;
	}

	/**
	 * 管理画面設定またはデフォルトの色パレットを取得
	 *
	 * @return array 色パレット（安→高）
	 */
	public function getHeatmapColors(): array {
		$loader = new Andw_Tour_Price_Loader();
		return $loader->getHeatmapColors();
	}

	public function getLevelColor( $level, $bins = null ) {
		if ( null === $bins ) {
			$bins = $this->levels;
		}
		
		// 管理画面設定の色リストを取得
		$colors = $this->getHeatmapColors();
		$adjusted_colors = self::adjustColorsForBins( $colors, $bins );
		
		return $adjusted_colors[ $level ] ?? '#cccccc';
	}

	/**
	 * 動的CSSルールを生成（新パレット対応）
	 *
	 * @param int $bins 実際のビン数（省略時は10）
	 * @return string CSS文字列
	 */
	public function generateCssRules( $bins = null ) {
		if ( null === $bins ) {
			$bins = $this->levels;
		}

		// 管理画面設定の色リストから指定ビン数の色を取得
		$colors = $this->getHeatmapColors();
		$adjusted_colors = self::adjustColorsForBins( $colors, $bins );
		
		$css = '';
		for ( $i = 0; $i < $this->levels; $i++ ) {
			$color_index = min( $i, count( $adjusted_colors ) - 1 );
			$color = $adjusted_colors[ $color_index ];
			
			$css .= ".ns-tour-price-calendar .hp-{$i} {\n";
			$css .= "  background-color: {$color};\n";
			$css .= "  color: " . $this->getTextColor( $color ) . ";\n";
			$css .= "}\n\n";
		}
		return $css;
	}

	private function getTextColor( $bg_color ) {
		$hex = ltrim( $bg_color, '#' );
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$brightness = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
		return ( $brightness > 128 ) ? '#000000' : '#ffffff';
	}

	public function getHeatmapStats( $prices, $classes ) {
		if ( empty( $prices ) || empty( $classes ) ) {
			return array();
		}

		$min_price = min( $prices );
		$max_price = max( $prices );
		$avg_price = array_sum( $prices ) / count( $prices );

		$level_counts = array_count_values( array_values( $classes ) );
		$most_common_level = array_keys( $level_counts, max( $level_counts ) )[0];

		return array(
			'min_price' => $min_price,
			'max_price' => $max_price,
			'avg_price' => round( $avg_price ),
			'price_range' => $max_price - $min_price,
			'total_days' => count( $prices ),
			'levels_used' => count( $level_counts ),
			'most_common_level' => $most_common_level,
			'level_distribution' => $level_counts,
		);
	}

	public function isHeatmapEffective( $prices ) {
		if ( count( $prices ) < 5 ) {
			return false;
		}

		$unique_prices = array_unique( $prices );
		if ( count( $unique_prices ) < 3 ) {
			return false;
		}

		$min_price = min( $prices );
		$max_price = max( $prices );
		$range = $max_price - $min_price;
		$avg_price = array_sum( $prices ) / count( $prices );

		// 価格差が平均の20%以上ある場合にヒートマップが効果的
		$threshold = $avg_price * 0.2;
		return $range >= $threshold;
	}
}