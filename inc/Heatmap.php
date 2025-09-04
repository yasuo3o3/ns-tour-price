<?php
/**
 * Heatmap Generator
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Heatmap {

	private $levels = 10;

	public function generateHeatmapClasses( $prices ) {
		if ( empty( $prices ) ) {
			return array();
		}

		$unique_prices = array_unique( $prices );
		if ( count( $unique_prices ) === 1 ) {
			// 全て同じ価格の場合は中間色
			$single_price = $unique_prices[0];
			return array( $single_price => 5 );
		}

		$min_price = min( $prices );
		$max_price = max( $prices );
		$range = $max_price - $min_price;

		if ( 0 === $range ) {
			return array( $min_price => 5 );
		}

		$classes = array();
		foreach ( $prices as $price ) {
			$normalized = ( $price - $min_price ) / $range;
			$level = min( 9, max( 0, floor( $normalized * $this->levels ) ) );
			$classes[ $price ] = $level;
		}

		return $classes;
	}

	public function getHeatmapLegendData( $prices, $classes ) {
		if ( empty( $prices ) || empty( $classes ) ) {
			return array();
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

		$legend_data = array();
		foreach ( $levels as $level => $level_prices ) {
			if ( ! empty( $level_prices ) ) {
				$legend_data[] = array(
					'level' => $level,
					'class' => 'hp-' . $level,
					'min_price' => min( $level_prices ),
					'max_price' => max( $level_prices ),
					'count' => count( $level_prices ),
					'color' => $this->getLevelColor( $level ),
				);
			}
		}

		return $legend_data;
	}

	public function getLevelColor( $level ) {
		$colors = array(
			0 => '#1a73e8', // 青（最安）
			1 => '#2d87f0',
			2 => '#4095f7',
			3 => '#54a3ff',
			4 => '#67b1ff',
			5 => '#7bbfff', // 中間
			6 => '#8ecd00', // 緑
			7 => '#ff9f00', // オレンジ
			8 => '#ff6d01', // 濃いオレンジ
			9 => '#d73027', // 赤（最高）
		);

		return $colors[ $level ] ?? '#cccccc';
	}

	public function generateCssRules() {
		$css = '';
		for ( $i = 0; $i < $this->levels; $i++ ) {
			$color = $this->getLevelColor( $i );
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