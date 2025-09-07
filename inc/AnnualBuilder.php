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
		
		// 年間価格データを取得（直接CSV読み込み）
		$yearly_prices = $this->getYearlyPricesDirectly( $tour, $duration, $year );
		
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

				// シーズン情報とカラー決定
				$season_info = $this->getDateSeasonInfo( $tour, $date_str, $duration );
				$season_code = $season_info['season_code'];
				$season_color = $season_color_map[ $season_code ] ?? '#f3f4f6';
				$season_class = $season_code ? 'season-' . strtolower( $season_code ) : '';

				$month_days[] = array(
					'day' => $day,
					'date' => $date_str,
					'weekday' => intval( gmdate( 'w', strtotime( $date_str ) ) ),
					'price' => $price,
					'has_price' => $has_price,
					'season_class' => $season_class,
					'is_today' => $date_str === gmdate( 'Y-m-d' ),
					'has_season' => ! empty( $season_code ),
					'season_code' => $season_code,
					'season_color' => $season_color,
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
		$colors_data = $options['heatmap_colors'] ?? '';
		
		// 型チェック: 既に配列の場合はそのまま使用
		if ( is_array( $colors_data ) ) {
			return array_filter( array_map( 'trim', $colors_data ) );
		}
		
		// 文字列の場合は explode で分割
		if ( ! empty( $colors_data ) && is_string( $colors_data ) ) {
			$colors = array_map( 'trim', explode( ',', $colors_data ) );
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
		// 直接CSV読み込みでシーズン要約を生成
		return $this->getSeasonSummaryDirectly( $tour, $year, $duration );
	}
	
	/**
	 * シーズン要約を直接CSVから生成
	 */
	private function getSeasonSummaryDirectly( $tour_id, $year, $duration ) {
		$seasons_path = NS_TOUR_PRICE_PLUGIN_DIR . 'data/seasons.csv';
		$prices_path = NS_TOUR_PRICE_PLUGIN_DIR . 'data/base_prices.csv';
		
		if ( ! file_exists( $seasons_path ) || ! file_exists( $prices_path ) ) {
			return array();
		}
		
		// シーズンデータを読み込み
		$seasons = array();
		$handle = fopen( $seasons_path, 'r' );
		if ( $handle ) {
			$headers = fgetcsv( $handle );
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				if ( count( $row ) >= 5 && trim( $row[0] ) === $tour_id ) {
					$seasons[] = array(
						'season_code' => trim( $row[1] ),
						'label' => trim( $row[2] ),
						'date_start' => trim( $row[3] ),
						'date_end' => trim( $row[4] ),
					);
				}
			}
			fclose( $handle );
		}
		
		// 価格データを読み込み
		$prices = array();
		$handle = fopen( $prices_path, 'r' );
		if ( $handle ) {
			$headers = fgetcsv( $handle );
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				if ( count( $row ) >= 4 && 
					 trim( $row[0] ) === $tour_id && 
					 intval( $row[2] ) === intval( $duration ) ) {
					$prices[ trim( $row[1] ) ] = intval( $row[3] );
				}
			}
			fclose( $handle );
		}
		
		// シーズン要約を生成
		$summary = array();
		foreach ( $seasons as $season ) {
			$code = $season['season_code'];
			$price = $prices[ $code ] ?? null;
			
			if ( $price === null ) {
				continue;
			}
			
			// 年でフィルタした期間文字列を生成
			$start_date = str_replace( '/', '-', $season['date_start'] );
			$end_date = str_replace( '/', '-', $season['date_end'] );
			
			$start_time = strtotime( $start_date );
			$end_time = strtotime( $end_date );
			$year_start = strtotime( "$year-01-01" );
			$year_end = strtotime( "$year-12-31" );
			
			if ( $end_time < $year_start || $start_time > $year_end ) {
				continue;
			}
			
			$effective_start = max( $start_time, $year_start );
			$effective_end = min( $end_time, $year_end );
			
			$period = date( 'n/j', $effective_start ) . '–' . date( 'n/j', $effective_end );
			
			if ( ! isset( $summary[ $code ] ) ) {
				$summary[ $code ] = array(
					'label' => $season['label'],
					'periods' => array(),
					'price' => $price,
				);
			}
			$summary[ $code ]['periods'][] = $period;
		}
		
		// A-K順でソート
		uksort( $summary, 'strnatcmp' );
		
		// periods重複除去
		foreach ( $summary as &$item ) {
			$item['periods'] = array_values( array_unique( $item['periods'] ) );
			sort( $item['periods'] );
		}
		
		return $summary;
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
		// シーズン固定色マッピングを使用
		$season_color_map = $annual_data['season_colors'] ?? array();

		ob_start();
		?>
		<div class="tpc-annual-view" data-tour="<?php echo esc_attr( $tour ); ?>" data-duration="<?php echo esc_attr( $duration ); ?>" data-year="<?php echo esc_attr( $year ); ?>">
			<div class="tpc-annual-header">
				<h3 class="tpc-annual-title">
					<?php printf( esc_html__( '%d年 年間価格概要 - %s（%d日間）', 'ns-tour_price' ), $year, esc_html( $tour ), $duration ); ?>
				</h3>
				<?php
				// 対象期間統計をコメントとして保存
				$percentage = $annual_data['total_days'] > 0 ? ( $annual_data['covered_days'] / $annual_data['total_days'] * 100 ) : 0;
				printf( 
					'<!-- 対象期間: %d日 / 全%d日 (%.1f%%) -->',
					$annual_data['covered_days'],
					$annual_data['total_days'],
					$percentage
				);
				?>
			</div>

			<?php if ( $opts['show_mini_calendars'] ) : ?>
			<div class="tpc-annual-calendars">
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
									$day_style = '';
									
									if ( $day_data['has_season'] ) {
										$day_classes[] = 'has-season';
										$day_classes[] = $day_data['season_class'];
										$day_style = 'background-color: ' . esc_attr( $day_data['season_color'] ) . ';';
										$day_style .= ' color: ' . esc_attr( $this->season_color_service->getTextColor( $day_data['season_color'] ) ) . ';';
									}
									
									if ( $day_data['is_today'] ?? false ) {
										$day_classes[] = 'tpc-today';
									}
									
									$title = $day_data['date'];
									if ( $day_data['has_season'] ) {
										$title .= ' (Season: ' . $day_data['season_code'] . ')';
										if ( $day_data['has_price'] ) {
											$title .= ' - ¥' . number_format( $day_data['price'] );
										}
									}
									?>
									<div class="<?php echo esc_attr( implode( ' ', $day_classes ) ); ?>"
										 style="<?php echo esc_attr( $day_style ); ?>"
										 title="<?php echo esc_attr( $title ); ?>">
										<span class="day-number"><?php echo esc_html( $day_data['day'] ); ?></span>
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
							<?php foreach ( $season_summary as $code => $season ) : 
								$label = $season['label'] ?? $code;
								$periods = ( isset( $season['periods'] ) && is_array( $season['periods'] ) ) ? $season['periods'] : array();
								$price = $season['price'] ?? null;
								
								$period_text = $periods ? implode( '、', $periods ) : '—';
								$price_text = ( $price !== null && $price > 0 ) ? '¥' . number_format( $price ) : '—';
								
								// シーズン固定色を適用
								$season_color = $season_color_map[ $code ] ?? '#f3f4f6';
								$text_color = $this->season_color_service->getTextColor( $season_color );
								$season_style = 'background-color: ' . esc_attr( $season_color ) . '; color: ' . esc_attr( $text_color ) . ';';
							?>
							<tr data-season="<?php echo esc_attr( $code ); ?>" data-price="<?php echo esc_attr( $price ?? 0 ); ?>" class="season-row season-<?php echo esc_attr( strtolower( $code ) ); ?>" style="<?php echo esc_attr( $season_style ); ?>">
								<td class="season-code"><?php echo esc_html( $label ); ?></td>
								<td class="season-periods"><?php echo esc_html( $period_text ); ?></td>
								<td class="season-price"><?php echo esc_html( $price_text ); ?></td>
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
	 * 年間価格データを直接CSVから取得
	 *
	 * @param string $tour_id ツアーID
	 * @param int $duration 日数
	 * @param int $year 対象年
	 * @return array ['Y-m-d' => price_int, ...]
	 */
	private function getYearlyPricesDirectly( $tour_id, $duration, $year ) {
		$yearly_prices = array();
		
		// seasons.csvを読み込み
		$seasons_path = NS_TOUR_PRICE_PLUGIN_DIR . 'data/seasons.csv';
		$prices_path = NS_TOUR_PRICE_PLUGIN_DIR . 'data/base_prices.csv';
		
		if ( ! file_exists( $seasons_path ) || ! file_exists( $prices_path ) ) {
			error_log( "AnnualBuilder: CSV files not found" );
			return $yearly_prices;
		}
		
		// シーズンデータを読み込み
		$seasons = array();
		$handle = fopen( $seasons_path, 'r' );
		if ( $handle ) {
			$headers = fgetcsv( $handle ); // ヘッダー行をスキップ
			
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				if ( count( $row ) >= 5 && trim( $row[0] ) === $tour_id ) {
					$seasons[] = array(
						'season_code' => trim( $row[1] ),
						'date_start' => trim( $row[3] ),
						'date_end' => trim( $row[4] ),
					);
				}
			}
			fclose( $handle );
		}
		
		// 価格データを読み込み
		$prices = array();
		$handle = fopen( $prices_path, 'r' );
		if ( $handle ) {
			$headers = fgetcsv( $handle ); // ヘッダー行をスキップ
			
			while ( ( $row = fgetcsv( $handle ) ) !== false ) {
				if ( count( $row ) >= 4 && 
					 trim( $row[0] ) === $tour_id && 
					 intval( $row[2] ) === intval( $duration ) ) {
					$prices[ trim( $row[1] ) ] = intval( $row[3] );
				}
			}
			fclose( $handle );
		}
		
		// 年間の日付ごとに価格を設定
		foreach ( $seasons as $season ) {
			$season_code = $season['season_code'];
			$price = $prices[ $season_code ] ?? null;
			
			if ( $price === null ) {
				continue;
			}
			
			// 日付範囲を処理
			$start_date = date_create( str_replace( '/', '-', $season['date_start'] ) );
			$end_date = date_create( str_replace( '/', '-', $season['date_end'] ) );
			
			if ( ! $start_date || ! $end_date ) {
				continue;
			}
			
			// 年でフィルタ
			$year_start = date_create( "$year-01-01" );
			$year_end = date_create( "$year-12-31" );
			
			$effective_start = max( $start_date, $year_start );
			$effective_end = min( $end_date, $year_end );
			
			if ( $effective_start > $effective_end ) {
				continue;
			}
			
			// 期間内の全日に価格を設定
			$current = clone $effective_start;
			while ( $current <= $effective_end ) {
				$date_key = $current->format( 'Y-m-d' );
				$yearly_prices[ $date_key ] = $price;
				$current->add( new DateInterval( 'P1D' ) );
			}
		}
		
		return $yearly_prices;
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