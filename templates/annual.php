<?php
/**
 * Annual View Template - 年間価格概要テンプレート
 * 
 * 12ヶ月のミニカレンダーとシーズン料金まとめを表示
 *
 * @package NS_Tour_Price
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// テンプレート変数の取得
$annual_calendar = $annual_calendar ?? array();
$season_summary = $season_summary ?? array();
$tour = $tour ?? '';
$duration = $duration ?? 4;
$year = $year ?? gmdate( 'Y' );
?>

<div class="tpc-annual-view" 
     data-tour="<?php echo esc_attr( $tour ); ?>" 
     data-duration="<?php echo esc_attr( $duration ); ?>" 
     data-year="<?php echo esc_attr( $year ); ?>"
     data-static-annual="1">
	
	<div class="tpc-annual-header">
		<h3 class="tpc-annual-title">
			<?php
			/* translators: 1: year, 2: tour name, 3: duration in days */
			printf(
				esc_html__( '%1$d年 年間価格概要 - %2$s（%3$d日間）', 'ns-tour-price' ),
				esc_html( absint( $year ) ),
				esc_html( $tour ),
				esc_html( absint( $duration ) )
			); ?>
		</h3>
		<?php 
		// 統計をコメントとして残す
		if ( ! empty( $annual_calendar['months'] ) ) : 
			$total_days = 0;
			$covered_days = 0;
			foreach ( $annual_calendar['months'] as $month_data ) {
				foreach ( $month_data['days'] as $day ) {
					$total_days++;
					if ( ! empty( $day['season_code'] ) ) {
						$covered_days++;
					}
				}
			}
			$percentage = $total_days > 0 ? ( $covered_days / $total_days * 100 ) : 0;
			// 対象期間: covered_days 日 / 全 total_days 日 (percentage %)
		endif; 
		?>
	</div>

	<?php if ( ! empty( $annual_calendar['months'] ) ) : ?>
	<div class="tpc-annual-calendars">
		<h4 class="tpc-section-title"><?php esc_html_e( '月別価格ヒートマップ', 'ns-tour-price' ); ?></h4>
		
		<?php if ( ! empty( $annual_calendar['heatmap']['colors'] ) ) : ?>
		<div class="tpc-heatmap-legend">
			<span class="legend-label"><?php esc_html_e( '価格帯:', 'ns-tour-price' ); ?></span>
			<?php foreach ( $annual_calendar['heatmap']['colors'] as $i => $color ) : 
				$bin = $annual_calendar['heatmap']['bins'][$i] ?? null;
				if ( $bin ) :
					$min_formatted = '¥' . number_format( $bin['min'] );
					$max_formatted = '¥' . number_format( $bin['max'] );
					$label = ( $bin['min'] === $bin['max'] ) ? $min_formatted : $min_formatted . '～' . $max_formatted;
				?>
				<span class="legend-item hp-<?php echo esc_attr( absint( $i ) ); ?>" style="background-color: <?php echo esc_attr( $color ); ?>">
					<?php echo esc_html( $label ); ?>
				</span>
			<?php endif; endforeach; ?>
		</div>
		<?php endif; ?>
		
		<div class="tpc-mini-calendars">
			<?php foreach ( $annual_calendar['months'] as $month_data ) : ?>
				<div class="tpc-mini-calendar">
					<div class="tpc-mini-header">
						<span class="tpc-mini-month">
							<?php echo esc_html( $month_data['month'] . '月' ); ?>
						</span>
						<small class="tpc-mini-stats">
							(<?php echo esc_html( $month_data['priced_days'] ); ?>日)
						</small>
					</div>
					<div class="tpc-mini-grid">
						<?php
						// 週ヘッダー
						$weekdays = array( '日', '月', '火', '水', '木', '金', '土' );
						foreach ( $weekdays as $wd ) : ?>
							<div class="tpc-mini-weekday"><?php echo esc_html( $wd ); ?></div>
						<?php endforeach;

						// 最初の週の空セルを追加
						$first_day = sprintf( '%04d-%02d-01', $year, $month_data['month'] );
						$first_weekday = intval( gmdate( 'w', strtotime( $first_day ) ) );
						for ( $i = 0; $i < $first_weekday; $i++ ) : ?>
							<div class="tpc-mini-day tpc-mini-empty"></div>
						<?php endfor;

						// 日付セル
						foreach ( $month_data['days'] as $day_data ) :
							$day_classes = array( 'tpc-mini-day' );
							
							if ( $day_data['has_price'] ) {
								$day_classes[] = 'has-price';
								$day_classes[] = $day_data['heatmap_class'];
							}
							if ( ! empty( $day_data['is_today'] ) ) {
								$day_classes[] = 'tpc-today';
							}
							if ( $day_data['weekday'] == 0 || $day_data['weekday'] == 6 ) {
								$day_classes[] = 'tpc-weekend';
							}
							
							$tooltip = $day_data['date'];
							if ( $day_data['has_price'] ) {
								$tooltip .= ' (¥' . number_format( $day_data['price'] ) . ')';
							}
							?>
							<div class="<?php echo esc_attr( implode( ' ', $day_classes ) ); ?>"
								 title="<?php echo esc_attr( $tooltip ); ?>">
								<span class="day-number"><?php echo esc_html( $day_data['day'] ); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! empty( $season_summary ) ) : ?>
	<div class="tpc-annual-seasons">
		<div class="tpc-season-table-container">
			<table class="tpc-season-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'シーズン', 'ns-tour-price' ); ?></th>
						<th><?php esc_html_e( '期間', 'ns-tour-price' ); ?></th>
						<th><?php esc_html_e( '料金', 'ns-tour-price' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $season_summary as $season_code => $season ) : ?>
					<tr data-season="<?php echo esc_attr( $season_code ); ?>">
						<td>
							<span class="tpc-season-chip" 
								  style="background-color: <?php echo esc_attr( $season['color'] ); ?>"></span>
							<span class="tpc-season-label">
								<?php echo esc_html( $season['label'] ); ?>
							</span>
						</td>
						<td class="tpc-season-periods">
							<?php echo esc_html( $season['ranges_text'] ); ?>
						</td>
						<td class="tpc-season-price">
							<?php
							if ( is_null( $season['price'] ) ) {
								echo esc_html( '—' );
							} else {
								echo esc_html( '¥' . number_format( $season['price'] ) );
							}
							?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>
</div>