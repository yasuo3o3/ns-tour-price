<?php
/**
 * Annual View Builder
 * 年間価格概要（12ヶ月ミニカレンダー + シーズン料金表）を生成
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Annual_Builder {

	private $repo;
	private $heatmap;

	public function __construct() {
		$this->repo = NS_Tour_Price_Repo::getInstance();
		$this->heatmap = new NS_Tour_Price_Heatmap();
	}

	/**
	 * 年間ビューを構築
	 *
	 * @param string $tour ツアーID
	 * @param int $duration 日数
	 * @param int $year 対象年
	 * @param array $opts オプション
	 * @return array {html: string, meta: array}
	 */
	public function build( $tour, $duration, $year, $opts = array() ) {
		$defaults = array(
			'show_mini_calendars' => true,
			'show_season_table' => true,
		);
		$opts = wp_parse_args( $opts, $defaults );

		if ( ! $this->repo->isDataAvailable() ) {
			return array(
				'html' => $this->buildErrorView( __( 'データが見つかりません', 'ns-tour_price' ) ),
				'meta' => array(),
			);
		}

		// キャッシュチェック
		$cache_key = $this->getCacheKey( $tour, $duration, $year );
		$cached = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		// 年間データを生成
		$annual_data = $this->generateAnnualData( $tour, $duration, $year, $opts );
		$season_summary = $this->summarizeSeasonPrices( $tour, $duration, $year );

		$html = $this->renderAnnualView( $annual_data, $season_summary, $tour, $duration, $year, $opts );

		$result = array(
			'html' => $html,
			'meta' => array(
				'tour' => $tour,
				'duration' => $duration,
				'year' => $year,
				'seasons_count' => count( $season_summary ),
				'total_days' => $annual_data['total_days'],
				'covered_days' => $annual_data['covered_days'],
			),
		);

		// キャッシュに保存（4時間）
		set_transient( $cache_key, $result, 4 * HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * 年間データ生成（価格ベースのヒートマップ色適用）
	 */
	private function generateAnnualData( $tour, $duration, $year, $opts = array() ) {
		$show_empty_months = $opts['show_empty_months'] ?? false;
		
		// 年間価格データを取得
		$yearly_prices = $this->repo->getYearlyPrices( $tour, $duration, $year );
		
		// ヒートマップ用の色分け計算
		$prices_array = array_values( $yearly_prices );
		$heatmap_data = $this->calculateHeatmapBins( $prices_array );

		$months_data = array();
		$total_days = 0;
		$covered_days = 0;
		$months_rendered = 0;

		for ( $month = 1; $month <= 12; $month++ ) {
			$days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
			$month_days = array();
			$priced_days_in_month = 0;

			for ( $day = 1; $day <= $days_in_month; $day++ ) {
				$date_str = sprintf( '%04d-%02d-%02d', $year, $month, $day );
				$total_days++;

				$price = $yearly_prices[ $date_str ] ?? 0;
				$has_price = $price > 0;
				
				if ( $has_price ) {
					$covered_days++;
					$priced_days_in_month++;
				}

				// ヒートマップクラス決定
				$heatmap_class = '';
				if ( $has_price ) {
					$bin_index = $this->getPriceBinIndex( $price, $heatmap_data['bins'] );
					$heatmap_class = 'hp-' . $bin_index;
				}

				$month_days[] = array(
					'day' => $day,
					'date' => $date_str,
					'weekday' => intval( gmdate( 'w', strtotime( $date_str ) ) ),
					'price' => $price,
					'has_price' => $has_price,
					'heatmap_class' => $heatmap_class,
					'is_today' => $date_str === gmdate( 'Y-m-d' ),
				);
			}

			// 空月除外（価格がある日が0の場合）
			if ( ! $show_empty_months && $priced_days_in_month === 0 ) {
				continue;
			}

			$months_rendered++;
			$months_data[ $month ] = array(
				'month' => $month,
				'month_name' => $this->getMonthName( $month ),
				'year' => $year,
				'days_in_month' => $days_in_month,
				'priced_days' => $priced_days_in_month,
				'days' => $month_days,
			);
		}

		return array(
			'months' => $months_data,
			'total_days' => $total_days,
			'covered_days' => $covered_days,
			'months_rendered' => $months_rendered,
			'heatmap' => $heatmap_data,
		);
	}

	/**
	 * ヒートマップのビン計算
	 */
	private function calculateHeatmapBins( $prices ) {
		if ( empty( $prices ) ) {
			return array(
				'bins' => array(),
				'colors' => array(),
				'min' => 0,
				'max' => 0,
			);
		}

		$min_price = min( $prices );
		$max_price = max( $prices );
		
		// 管理画面設定から色パレットとビン数を取得
		$options = get_option( 'ns_tour_price_options', array() );
		$bins_count = intval( $options['heatmap_bins'] ?? 5 );
		$colors = $this->getHeatmapColors();

		if ( $min_price === $max_price ) {
			return array(
				'bins' => array( $min_price ),
				'colors' => array( $colors[0] ?? '#e0e0e0' ),
				'min' => $min_price,
				'max' => $max_price,
			);
		}

		$bins = array();
		$bin_colors = array();
		$range = $max_price - $min_price;

		for ( $i = 0; $i < $bins_count; $i++ ) {
			$bin_min = $min_price + ( $range * $i / $bins_count );
			$bin_max = ( $i === $bins_count - 1 ) ? $max_price : $min_price + ( $range * ( $i + 1 ) / $bins_count );
			
			$bins[] = array(
				'min' => $bin_min,
				'max' => $bin_max,
			);
			
			$color_index = min( $i, count( $colors ) - 1 );
			$bin_colors[] = $colors[ $color_index ];
		}

		return array(
			'bins' => $bins,
			'colors' => $bin_colors,
			'min' => $min_price,
			'max' => $max_price,
		);
	}

	/**
	 * 価格に対応するビンインデックスを取得
	 */
	private function getPriceBinIndex( $price, $bins ) {
		foreach ( $bins as $index => $bin ) {
			if ( $price >= $bin['min'] && $price <= $bin['max'] ) {
				return $index;
			}
		}
		return 0;
	}

	/**
	 * ヒートマップカラーパレットを取得
	 */
	private function getHeatmapColors() {
		$options = get_option( 'ns_tour_price_options', array() );
		$colors_string = $options['heatmap_colors'] ?? '';
		
		if ( ! empty( $colors_string ) ) {
			$colors = array_map( 'trim', explode( ',', $colors_string ) );
			return array_filter( $colors );
		}

		// デフォルト色
		return array(
			'#e0f2fe',
			'#b3e5fc', 
			'#81d4fa',
			'#4fc3f7',
			'#29b6f6',
			'#03a9f4',
		);
	}

	/**
	 * 指定日のシーズン情報を取得
	 */
	private function getDateSeasonInfo( $tour, $date, $duration ) {
		// シーズン判定ロジックはRepoから取得
		$season_code = $this->repo->getSeasonForDate( $tour, $date );
		
		if ( empty( $season_code ) ) {
			return array(
				'season_code' => '',
				'color' => '#f5f5f5', // グレー（対象外）
			);
		}

		// シーズンの色を取得（ヒートマップ設定から）
		$color = $this->getSeasonColor( $season_code );

		return array(
			'season_code' => $season_code,
			'color' => $color,
		);
	}

	/**
	 * シーズン料金要約を生成（結合・並び・価格修正版）
	 */
	private function summarizeSeasonPrices( $tour, $duration, $year ) {
		$seasons_data = $this->repo->getSeasons( $tour );
		$prices_data = $this->repo->getBasePrices( $tour );
		$groups = array();

		// season_code 単位でグループ化
		foreach ( $seasons_data as $season ) {
			$season_code = $season['season_code'];
			
			// 当年に該当する期間チェック
			$periods = $this->getSeasonRangesForYear( $season, $year );
			if ( empty( $periods ) ) {
				continue;
			}

			if ( ! isset( $groups[ $season_code ] ) ) {
				$groups[ $season_code ] = array(
					'label' => $season['label'] ?? $season_code,
					'ranges' => array(),
					'price' => null,
					'color' => '#6c757d',
				);
			}

			// 期間を追加
			$groups[ $season_code ]['ranges'] = array_merge( $groups[ $season_code ]['ranges'], $periods );
		}

		// A→Z順で並び替え
		uksort( $groups, 'strnatcmp' );

		// 価格と色を設定
		foreach ( $groups as $season_code => &$group ) {
			// 価格検索
			$group['price'] = $this->findSeasonPrice( $tour, $season_code, $duration, $prices_data );
			
			// シーズン色取得
			$group['color'] = $this->getSeasonColorFromMap( $season_code );
			
			// 期間をテキスト化（「、」で結合）
			$group['ranges_text'] = implode( '、', array_map( function( $range ) {
				return sprintf( '%d/%d–%d/%d', 
					$range['start_month'], $range['start_day'],
					$range['end_month'], $range['end_day']
				);
			}, $group['ranges'] ) );
		}

		return $groups;
	}

	/**
	 * 指定年に該当するシーズン期間を抽出（日付正規化対応）
	 */
	private function getSeasonRangesForYear( $season, $year ) {
		$ranges = array();
		
		// 日付正規化（/ → -）
		$date_start = strtr( trim( $season['date_start'] ?? '' ), array( '/' => '-' ) );
		$date_end = strtr( trim( $season['date_end'] ?? '' ), array( '/' => '-' ) );

		// DateTime作成
		$start_date = date_create( $date_start );
		if ( ! $start_date ) {
			$start_date = date_create_from_format( 'Y-m-d', $date_start );
		}

		$end_date = date_create( $date_end );
		if ( ! $end_date ) {
			$end_date = date_create_from_format( 'Y-m-d', $date_end );
		}

		if ( ! $start_date || ! $end_date ) {
			return $ranges;
		}

		$start_year = intval( $start_date->format( 'Y' ) );
		$end_year = intval( $end_date->format( 'Y' ) );

		// 年跨ぎの場合、当年分のみをトリム
		if ( $start_year <= $year && $end_year >= $year ) {
			$actual_start = ( $start_year === $year ) ? $start_date : date_create( sprintf( '%04d-01-01', $year ) );
			$actual_end = ( $end_year === $year ) ? $end_date : date_create( sprintf( '%04d-12-31', $year ) );

			$ranges[] = array(
				'start_month' => intval( $actual_start->format( 'n' ) ),
				'start_day' => intval( $actual_start->format( 'j' ) ),
				'end_month' => intval( $actual_end->format( 'n' ) ),
				'end_day' => intval( $actual_end->format( 'j' ) ),
			);
		}

		return $ranges;
	}

	/**
	 * シーズン価格検索
	 */
	private function findSeasonPrice( $tour, $season_code, $duration, $prices_data ) {
		foreach ( $prices_data as $price ) {
			if ( $price['tour_id'] === $tour &&
				 $price['season_code'] === $season_code &&
				 intval( $price['duration_days'] ) === intval( $duration ) ) {
				return intval( $price['price'] );
			}
		}
		return null;
	}

	/**
	 * シーズン色マップから色取得
	 */
	private function getSeasonColorFromMap( $season_code ) {
		$season_colors = get_option( 'ns_tour_price_season_colors', array() );
		
		if ( isset( $season_colors[ $season_code ] ) ) {
			return $season_colors[ $season_code ];
		}

		// デフォルト色
		$default_colors = array(
			'A' => '#4CAF50', 'B' => '#E91E63', 'C' => '#FF9800', 'D' => '#2196F3',
			'E' => '#9C27B0', 'F' => '#795548', 'G' => '#607D8B', 'H' => '#FFC107',
			'I' => '#8BC34A', 'J' => '#00BCD4', 'K' => '#FF5722', 'L' => '#3F51B5',
		);

		return $default_colors[ $season_code ] ?? '#6c757d';
	}

	/**
	 * 指定年に該当するシーズン期間を抽出
	 */
	private function getSeasonPeriodsForYear( $season, $year ) {
		$periods = array();
		
		$start_date = NS_Tour_Price_Helpers::normalize_date( $season['date_start'] );
		$end_date = NS_Tour_Price_Helpers::normalize_date( $season['date_end'] );
		
		if ( false === $start_date || false === $end_date ) {
			return $periods;
		}

		$start_year = intval( substr( $start_date, 0, 4 ) );
		$end_year = intval( substr( $end_date, 0, 4 ) );

		// 年跨ぎの場合、当年分のみをトリム
		if ( $start_year <= $year && $end_year >= $year ) {
			$actual_start = ( $start_year === $year ) ? $start_date : sprintf( '%04d-01-01', $year );
			$actual_end = ( $end_year === $year ) ? $end_date : sprintf( '%04d-12-31', $year );

			$periods[] = $this->formatPeriod( $actual_start, $actual_end );
		}

		return $periods;
	}

	/**
	 * 期間をフォーマット
	 */
	private function formatPeriod( $start_date, $end_date ) {
		$start_formatted = gmdate( 'n/j', strtotime( $start_date ) );
		$end_formatted = gmdate( 'n/j', strtotime( $end_date ) );
		return $start_formatted . '–' . $end_formatted;
	}

	/**
	 * シーズンの色を取得
	 */
	private function getSeasonColor( $season_code ) {
		// 簡易的なシーズンコード→色マッピング
		// 実際の実装では管理画面設定や既存ロジックを活用
		$color_map = array(
			'HIGH' => '#ff6b6b',
			'MID' => '#4ecdc4',
			'LOW' => '#45b7d1',
			'PEAK' => '#d63384',
			'REGULAR' => '#6f42c1',
		);

		return $color_map[ $season_code ] ?? '#6c757d';
	}

	/**
	 * 年間ビューのHTMLを生成
	 */
	private function renderAnnualView( $annual_data, $season_summary, $tour, $duration, $year, $opts ) {
		ob_start();
		?>
		<div class="tpc-annual-view" data-tour="<?php echo esc_attr( $tour ); ?>" data-duration="<?php echo esc_attr( $duration ); ?>" data-year="<?php echo esc_attr( $year ); ?>">
			<div class="tpc-annual-header">
				<h3 class="tpc-annual-title">
					<?php printf( esc_html__( '%d年 年間価格概要 - %s（%d日間）', 'ns-tour_price' ), $year, esc_html( $tour ), $duration ); ?>
				</h3>
				<div class="tpc-annual-stats">
					<?php printf( 
						esc_html__( '対象期間: %d日 / 全%d日 (%.1f%%)', 'ns-tour_price' ),
						$annual_data['covered_days'],
						$annual_data['total_days'],
						$annual_data['total_days'] > 0 ? ( $annual_data['covered_days'] / $annual_data['total_days'] * 100 ) : 0
					); ?>
				</div>
			</div>

			<?php if ( $opts['show_mini_calendars'] ) : ?>
			<div class="tpc-annual-calendars">
				<h4 class="tpc-section-title"><?php esc_html_e( '月別シーズン表示', 'ns-tour_price' ); ?></h4>
				<div class="tpc-mini-calendars">
					<?php foreach ( $annual_data['months'] as $month_data ) : ?>
						<div class="tpc-mini-calendar">
							<div class="tpc-mini-header">
								<span class="tpc-mini-month"><?php echo esc_html( $month_data['month_name'] ); ?></span>
							</div>
							<div class="tpc-mini-grid">
								<?php
								// 週ヘッダー（簡略版）
								$weekdays = array( '日', '月', '火', '水', '木', '金', '土' );
								foreach ( $weekdays as $wd ) : ?>
									<div class="tpc-mini-weekday"><?php echo esc_html( $wd ); ?></div>
								<?php endforeach;

								// 最初の週の空セルを追加
								$first_weekday = intval( gmdate( 'w', strtotime( sprintf( '%04d-%02d-01', $year, $month_data['month'] ) ) ) );
								for ( $i = 0; $i < $first_weekday; $i++ ) : ?>
									<div class="tpc-mini-day tpc-mini-empty"></div>
								<?php endfor;

								// 日付セル
								foreach ( $month_data['days'] as $day_data ) :
									$day_classes = array( 'tpc-mini-day' );
									if ( $day_data['has_season'] ) {
										$day_classes[] = 'tpc-has-season';
									}
									if ( $day_data['is_today'] ) {
										$day_classes[] = 'tpc-today';
									}
									?>
									<div class="<?php echo esc_attr( implode( ' ', $day_classes ) ); ?>"
										 style="background-color: <?php echo esc_attr( $day_data['season_color'] ); ?>"
										 title="<?php echo esc_attr( $day_data['date'] . ( $day_data['season_code'] ? ' (' . $day_data['season_code'] . ')' : '' ) ); ?>">
										<?php echo esc_html( $day_data['day'] ); ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>

			<?php if ( $opts['show_season_table'] && ! empty( $season_summary ) ) : ?>
			<div class="tpc-annual-seasons">
				<h4 class="tpc-section-title"><?php esc_html_e( 'シーズン料金一覧', 'ns-tour_price' ); ?></h4>
				<div class="tpc-season-table-container">
					<table class="tpc-season-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'シーズン', 'ns-tour_price' ); ?></th>
								<th><?php esc_html_e( '期間', 'ns-tour_price' ); ?></th>
								<th><?php esc_html_e( '料金', 'ns-tour_price' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $season_summary as $season ) : ?>
							<tr>
								<td>
									<span class="tpc-season-chip" style="background-color: <?php echo esc_attr( $season['color'] ); ?>"></span>
									<span class="tpc-season-label"><?php echo esc_html( $season['season_label'] ); ?></span>
								</td>
								<td class="tpc-season-periods">
									<?php echo esc_html( implode( ', ', $season['periods'] ) ); ?>
								</td>
								<td class="tpc-season-price">
									<?php echo esc_html( $season['formatted_price'] ); ?>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * エラービューを構築
	 */
	private function buildErrorView( $message ) {
		return sprintf( 
			'<div class="tpc-annual-error">%s</div>',
			esc_html( $message )
		);
	}

	/**
	 * キャッシュキーを生成
	 */
	private function getCacheKey( $tour, $duration, $year ) {
		// CSVの最終更新時刻なども含めてハッシュ化
		$data_hash = '';
		if ( $this->repo->isDataAvailable() ) {
			// 簡易的にcurrent_time()を使用してキャッシュキーを生成
			$data_hash = gmdate( 'Y-m-d-H' ); // 時間単位でキャッシュを更新
		}
		
		return 'tpc:annual:' . md5( $tour . ':' . $duration . ':' . $year . ':' . $data_hash );
	}

	/**
	 * 価格をフォーマット
	 */
	private function formatPrice( $price ) {
		if ( null === $price || $price <= 0 ) {
			return __( '設定なし', 'ns-tour_price' );
		}

		return '¥' . number_format( $price );
	}

	/**
	 * 月名を取得
	 */
	private function getMonthName( $month ) {
		$names = array(
			1 => __( '1月', 'ns-tour_price' ),
			2 => __( '2月', 'ns-tour_price' ),
			3 => __( '3月', 'ns-tour_price' ),
			4 => __( '4月', 'ns-tour_price' ),
			5 => __( '5月', 'ns-tour_price' ),
			6 => __( '6月', 'ns-tour_price' ),
			7 => __( '7月', 'ns-tour_price' ),
			8 => __( '8月', 'ns-tour_price' ),
			9 => __( '9月', 'ns-tour_price' ),
			10 => __( '10月', 'ns-tour_price' ),
			11 => __( '11月', 'ns-tour_price' ),
			12 => __( '12月', 'ns-tour_price' ),
		);

		return $names[ $month ] ?? (string) $month;
	}
}