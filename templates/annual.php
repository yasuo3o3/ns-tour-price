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
     data-year="<?php echo esc_attr( $year ); ?>">
	
	<div class="tpc-annual-header">
		<h3 class="tpc-annual-title">
			<?php printf( 
				esc_html__( '%d年 年間価格概要 - %s（%d日間）', 'ns-tour_price' ), 
				$year, 
				esc_html( $tour ), 
				$duration 
			); ?>
		</h3>
		<?php if ( ! empty( $annual_calendar['months'] ) ) : ?>
			<div class="tpc-annual-stats">
				<?php
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
				printf( 
					esc_html__( '対象期間: %d日 / 全%d日 (%.1f%%)', 'ns-tour_price' ),
					$covered_days,
					$total_days,
					$total_days > 0 ? ( $covered_days / $total_days * 100 ) : 0
				); 
				?>
			</div>
		<?php endif; ?>
	</div>

	<?php if ( ! empty( $annual_calendar['months'] ) ) : ?>
	<div class="tpc-annual-calendars">
		<h4 class="tpc-section-title"><?php esc_html_e( '月別シーズン表示', 'ns-tour_price' ); ?></h4>
		<div class="tpc-mini-calendars">
			<?php foreach ( $annual_calendar['months'] as $month_data ) : ?>
				<div class="tpc-mini-calendar">
					<div class="tpc-mini-header">
						<span class="tpc-mini-month">
							<?php echo esc_html( $month_data['month'] . '月' ); ?>
						</span>
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
							$season_color = '#f5f5f5'; // デフォルト（グレー）
							
							if ( ! empty( $day_data['season_code'] ) ) {
								$day_classes[] = 'tpc-has-season';
								$season_color = $day_data['season_color'] ?? '#e0e0e0';
							}
							if ( ! empty( $day_data['is_today'] ) ) {
								$day_classes[] = 'tpc-today';
							}
							if ( ! empty( $day_data['is_weekend'] ) ) {
								$day_classes[] = 'tpc-weekend';
							}
							?>
							<div class="<?php echo esc_attr( implode( ' ', $day_classes ) ); ?>"
								 style="background-color: <?php echo esc_attr( $season_color ); ?>"
								 title="<?php echo esc_attr( $day_data['date'] . ( ! empty( $day_data['season_code'] ) ? ' (' . $day_data['season_code'] . ')' : '' ) ); ?>">
								<?php echo esc_html( $day_data['day'] ); ?>
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
							<span class="tpc-season-chip" 
								  style="background-color: <?php echo esc_attr( $season['color'] ); ?>"></span>
							<span class="tpc-season-label">
								<?php echo esc_html( $season['season_label'] ); ?>
							</span>
						</td>
						<td class="tpc-season-periods">
							<?php echo esc_html( $season['periods'] ); ?>
						</td>
						<td class="tpc-season-price">
							<?php echo esc_html( $season['price'] ); ?>
						</td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
	<?php endif; ?>
</div>