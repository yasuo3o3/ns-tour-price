<?php
/**
 * Calendar Template
 * 
 * このテンプレートファイルは将来の拡張用です。
 * 現在はRenderer.phpが直接HTMLを生成していますが、
 * テーマによるカスタマイズを可能にするため、
 * 将来的にはこのテンプレートシステムを使用する予定です。
 *
 * @package NS_Tour_Price
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// テンプレート変数の取得
$calendar_data = $args['calendar_data'] ?? null;
$month_data = $calendar_data['month_data'] ?? null;
$calendar_args = $calendar_data['args'] ?? array();
$grid = $args['grid'] ?? array();
$weekday_headers = $args['weekday_headers'] ?? array();

if ( ! $calendar_data || isset( $calendar_data['error'] ) ) {
	?>
	<div class="ns-tour-price-calendar error">
		<div class="error-message">
			<p><?php echo esc_html( $calendar_data['message'] ?? __( 'エラーが発生しました', 'ns-tour_price' ) ); ?></p>
		</div>
	</div>
	<?php
	return;
}
?>

<div class="ns-tour-price-calendar" 
	 data-tour="<?php echo esc_attr( $calendar_args['tour'] ); ?>"
	 data-month="<?php echo esc_attr( $calendar_args['month'] ); ?>"
	 data-duration="<?php echo esc_attr( $calendar_args['duration'] ); ?>">
	
	<?php do_action( 'ns_tour_price_before_calendar_header', $calendar_data ); ?>
	
	<div class="calendar-header">
		<h3 class="calendar-title">
			<?php printf( 
				esc_html__( '%1$s年%2$s （%3$d日間）', 'ns-tour_price' ),
				$month_data['year'],
				$month_data['month_name'],
				$calendar_args['duration']
			); ?>
		</h3>
		<div class="calendar-meta">
			<span class="tour-id">
				<?php printf( 
					esc_html__( 'ツアー: %s', 'ns-tour_price' ), 
					esc_html( $calendar_args['tour'] ) 
				); ?>
			</span>
		</div>
	</div>

	<?php do_action( 'ns_tour_price_before_calendar_grid', $calendar_data ); ?>

	<div class="calendar-grid">
		<div class="calendar-weekdays">
			<?php foreach ( $weekday_headers as $weekday ) : ?>
				<div class="weekday-header"><?php echo esc_html( $weekday ); ?></div>
			<?php endforeach; ?>
		</div>

		<div class="calendar-weeks">
			<?php foreach ( $grid as $week_index => $week ) : ?>
				<?php do_action( 'ns_tour_price_before_calendar_week', $week, $week_index, $calendar_data ); ?>
				
				<div class="calendar-week">
					<?php foreach ( $week as $day_index => $day ) : ?>
						<?php 
						do_action( 'ns_tour_price_before_calendar_day', $day, $day_index, $week_index, $calendar_data );
						
						// 日付セルのテンプレートパートを読み込み
						$day_template = locate_template( 'ns-tour-price/day.php' );
						if ( $day_template ) {
							include $day_template;
						} else {
							// デフォルトの日付セル表示
							echo $this->renderDay( $day, $calendar_data );
						}
						
						do_action( 'ns_tour_price_after_calendar_day', $day, $day_index, $week_index, $calendar_data );
						?>
					<?php endforeach; ?>
				</div>
				
				<?php do_action( 'ns_tour_price_after_calendar_week', $week, $week_index, $calendar_data ); ?>
			<?php endforeach; ?>
		</div>
	</div>

	<?php do_action( 'ns_tour_price_after_calendar_grid', $calendar_data ); ?>

	<?php if ( $calendar_args['show_legend'] && ! empty( $calendar_data['legend'] ) ) : ?>
		<?php do_action( 'ns_tour_price_before_calendar_legend', $calendar_data ); ?>
		
		<div class="calendar-legend">
			<h4><?php esc_html_e( '価格区分', 'ns-tour_price' ); ?></h4>
			<div class="legend-items">
				<?php foreach ( $calendar_data['legend'] as $legend_index => $legend_item ) : ?>
					<?php do_action( 'ns_tour_price_before_legend_item', $legend_item, $legend_index, $calendar_data ); ?>
					
					<div class="legend-item">
						<span class="legend-color <?php echo esc_attr( $legend_item['class'] ); ?>"></span>
						<span class="legend-label">
							<?php echo esc_html( $legend_item['season_code'] ); ?> <?php echo esc_html( $legend_item['formatted_price'] ); ?>
						</span>
					</div>
					
					<?php do_action( 'ns_tour_price_after_legend_item', $legend_item, $legend_index, $calendar_data ); ?>
				<?php endforeach; ?>
			</div>
		</div>
		
		<?php do_action( 'ns_tour_price_after_calendar_legend', $calendar_data ); ?>
	<?php endif; ?>

	<div class="tpc-annual-toggle">
		<label>
			<input type="checkbox" id="tpc-annual-checkbox"> <?php esc_html_e( '年間価格概要を表示', 'ns-tour_price' ); ?>
		</label>
	</div>

	<?php do_action( 'ns_tour_price_after_calendar_content', $calendar_data ); ?>

</div>

<?php
/*
 * カスタマイズのためのアクションフック一覧:
 * 
 * - ns_tour_price_before_calendar_header
 * - ns_tour_price_before_calendar_grid
 * - ns_tour_price_before_calendar_week
 * - ns_tour_price_before_calendar_day
 * - ns_tour_price_after_calendar_day
 * - ns_tour_price_after_calendar_week
 * - ns_tour_price_after_calendar_grid
 * - ns_tour_price_before_calendar_legend
 * - ns_tour_price_before_legend_item
 * - ns_tour_price_after_legend_item
 * - ns_tour_price_after_calendar_legend
 * - ns_tour_price_after_calendar_content
 * 
 * テーマによるテンプレートオーバーライド:
 * 
 * テーマの以下のパスにファイルを配置することで、
 * デフォルトテンプレートをオーバーライドできます:
 * 
 * - theme-folder/ns-tour-price/calendar.php
 * - theme-folder/ns-tour-price/day.php
 * - theme-folder/ns-tour-price/legend.php
 * 
 */