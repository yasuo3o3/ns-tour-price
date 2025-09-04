<?php
/**
 * Calendar Renderer
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Renderer {

	private $builder;

	public function __construct() {
		$this->builder = new NS_Tour_Price_CalendarBuilder();
	}

	public function render( $args ) {
		do_action( 'ns_tour_price_before_calendar', $args );

		$calendar_data = $this->builder->buildCalendar( $args );
		
		if ( isset( $calendar_data['error'] ) ) {
			$output = $this->renderError( $calendar_data['message'] );
		} else {
			$output = $this->renderCalendar( $calendar_data );
		}

		do_action( 'ns_tour_price_after_calendar', $args );

		return $output;
	}

	private function renderCalendar( $calendar_data ) {
		$month_data = $calendar_data['month_data'];
		$args = $calendar_data['args'];
		$grid = $this->builder->getCalendarGrid( $calendar_data );
		$weekday_headers = $this->builder->getWeekdayHeaders( $month_data['week_start'] );

		ob_start();
		?>
		<div class="ns-tour-price-calendar" 
			 data-tour="<?php echo esc_attr( $args['tour'] ); ?>"
			 data-month="<?php echo esc_attr( $args['month'] ); ?>"
			 data-duration="<?php echo esc_attr( $args['duration'] ); ?>">
			
			<div class="calendar-header">
				<h3 class="calendar-title">
					<?php printf( 
						esc_html__( '%1$s年%2$s （%3$d日間）', 'ns-tour_price' ),
						$month_data['year'],
						$month_data['month_name'],
						$args['duration']
					); ?>
				</h3>
				<div class="calendar-meta">
					<span class="tour-id"><?php printf( esc_html__( 'ツアー: %s', 'ns-tour_price' ), esc_html( $args['tour'] ) ); ?></span>
				</div>
			</div>

			<div class="calendar-grid">
				<div class="calendar-weekdays">
					<?php foreach ( $weekday_headers as $weekday ) : ?>
						<div class="weekday-header"><?php echo esc_html( $weekday ); ?></div>
					<?php endforeach; ?>
				</div>

				<div class="calendar-weeks">
					<?php foreach ( $grid as $week ) : ?>
						<div class="calendar-week">
							<?php foreach ( $week as $day ) : ?>
								<?php echo $this->renderDay( $day, $calendar_data ); ?>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( $args['show_legend'] && ! empty( $calendar_data['legend'] ) ) : ?>
				<div class="calendar-legend">
					<h4><?php esc_html_e( '価格帯', 'ns-tour_price' ); ?></h4>
					<div class="legend-items">
						<?php foreach ( $calendar_data['legend'] as $legend_item ) : ?>
							<div class="legend-item">
								<span class="legend-color <?php echo esc_attr( $legend_item['class'] ); ?>"></span>
								<span class="legend-label">
									<?php echo esc_html( $legend_item['formatted_min'] ); ?>
									<?php if ( $legend_item['min_price'] !== $legend_item['max_price'] ) : ?>
										- <?php echo esc_html( $legend_item['formatted_max'] ); ?>
									<?php endif; ?>
								</span>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
		
		return ob_get_clean();
	}

	private function renderDay( $day, $calendar_data ) {
		if ( isset( $day['empty'] ) ) {
			return '<div class="calendar-day empty"></div>';
		}

		if ( isset( $day['error'] ) ) {
			return '<div class="calendar-day error"><div class="day-content">' . 
				   esc_html( $day['message'] ) . '</div></div>';
		}

		$classes = array( 'calendar-day' );
		$args = $calendar_data['args'];

		if ( $day['is_today'] ) {
			$classes[] = 'today';
		}

		if ( $day['is_weekend'] ) {
			$classes[] = 'weekend';
		}

		if ( ! $day['should_display'] ) {
			$classes[] = 'hidden';
		}

		if ( $day['has_price'] ) {
			$classes[] = 'has-price';
			
			if ( $args['heatmap'] && isset( $calendar_data['heatmap_classes'][ $day['price'] ] ) ) {
				$heatmap_level = $calendar_data['heatmap_classes'][ $day['price'] ];
				$classes[] = 'hp-' . $heatmap_level;
			}
		} else {
			$classes[] = 'no-price';
		}

		if ( $day['is_confirmed'] ) {
			$classes[] = 'confirmed';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" 
			 data-date="<?php echo esc_attr( $day['date'] ); ?>"
			 data-price="<?php echo esc_attr( $day['price'] ); ?>"
			 <?php if ( ! empty( $day['note'] ) ) : ?>
				 title="<?php echo esc_attr( $day['note'] ); ?>"
			 <?php endif; ?>>
			
			<div class="day-content">
				<div class="day-number"><?php echo esc_html( $day['day'] ); ?></div>
				
				<?php if ( $day['should_display'] && $day['has_price'] ) : ?>
					<div class="day-price"><?php echo esc_html( $day['formatted_price'] ); ?></div>
				<?php elseif ( $day['should_display'] && ! $day['has_price'] ) : ?>
					<div class="day-price no-data"><?php esc_html_e( '設定なし', 'ns-tour_price' ); ?></div>
				<?php endif; ?>

				<?php if ( $day['is_confirmed'] ) : ?>
					<div class="confirmed-badge" title="<?php esc_attr_e( '催行確定', 'ns-tour_price' ); ?>">
						<span class="badge-text">✓</span>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	private function renderError( $message ) {
		ob_start();
		?>
		<div class="ns-tour-price-calendar error">
			<div class="error-message">
				<p><?php echo esc_html( $message ); ?></p>
				<details>
					<summary><?php esc_html_e( 'トラブルシューティング', 'ns-tour_price' ); ?></summary>
					<ul>
						<li><?php esc_html_e( 'CSVファイルが正しい場所に配置されているか確認してください', 'ns-tour_price' ); ?></li>
						<li><?php esc_html_e( 'ツアーIDが存在するか確認してください', 'ns-tour_price' ); ?></li>
						<li><?php esc_html_e( '月の形式が YYYY-MM になっているか確認してください', 'ns-tour_price' ); ?></li>
						<li><?php esc_html_e( '管理画面でデータソースの状態を確認してください', 'ns-tour_price' ); ?></li>
					</ul>
				</details>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}

	public function renderPreview( $args ) {
		$args['show_legend'] = false;
		$calendar_data = $this->builder->buildCalendar( $args );
		
		if ( isset( $calendar_data['error'] ) ) {
			return $this->renderError( $calendar_data['message'] );
		}

		$month_data = $calendar_data['month_data'];
		$grid = $this->builder->getCalendarGrid( $calendar_data );
		$weekday_headers = $this->builder->getWeekdayHeaders( $month_data['week_start'] );

		ob_start();
		?>
		<div class="ns-tour-price-calendar preview" 
			 data-tour="<?php echo esc_attr( $args['tour'] ); ?>"
			 data-month="<?php echo esc_attr( $args['month'] ); ?>">
			
			<div class="calendar-header">
				<h4><?php printf( 
					esc_html__( '%1$s年%2$s （%3$d日間）', 'ns-tour_price' ),
					$month_data['year'],
					$month_data['month_name'],
					$args['duration']
				); ?></h4>
			</div>

			<div class="calendar-grid preview-grid">
				<div class="calendar-weekdays">
					<?php foreach ( $weekday_headers as $weekday ) : ?>
						<div class="weekday-header"><?php echo esc_html( $weekday ); ?></div>
					<?php endforeach; ?>
				</div>

				<div class="calendar-weeks">
					<?php foreach ( array_slice( $grid, 0, 3 ) as $week ) : // 最初の3週のみ表示 ?>
						<div class="calendar-week">
							<?php foreach ( $week as $day ) : ?>
								<?php echo $this->renderDay( $day, $calendar_data ); ?>
							<?php endforeach; ?>
						</div>
					<?php endforeach; ?>
				</div>

				<?php if ( count( $grid ) > 3 ) : ?>
					<div class="preview-more">
						<div class="more-indicator">...</div>
					</div>
				<?php endif; ?>
			</div>
		</div>
		<?php

		return ob_get_clean();
	}
}