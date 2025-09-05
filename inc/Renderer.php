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
	private $repo;
	private $booking_preview;

	public function __construct() {
		$this->builder = new NS_Tour_Price_CalendarBuilder();
		$this->repo = NS_Tour_Price_Repo::getInstance();
		$this->booking_preview = new NS_Tour_Price_BookingPreview();
	}

	public function render( $args ) {
		do_action( 'ns_tour_price_before_calendar', $args );

		// HTMLキャッシュを試行
		$cached_html = $this->getCachedHtml( $args );
		if ( false !== $cached_html ) {
			do_action( 'ns_tour_price_after_calendar', $args );
			return $cached_html;
		}

		$calendar_data = $this->builder->buildCalendar( $args );
		
		if ( isset( $calendar_data['error'] ) ) {
			$output = $this->renderError( $calendar_data['message'] );
		} else {
			$output = $this->renderCalendar( $calendar_data );
			// 正常なカレンダーのみキャッシュ
			$this->setCachedHtml( $args, $output );
		}

		do_action( 'ns_tour_price_after_calendar', $args );

		return $output;
	}

	/**
	 * HTMLキャッシュを取得
	 *
	 * @param array $args カレンダー引数
	 * @return string|false キャッシュされたHTML、またはfalse
	 */
	private function getCachedHtml( $args ) {
		$cache_key = $this->buildCacheKey( $args );
		return get_transient( $cache_key );
	}

	/**
	 * HTMLキャッシュを保存
	 *
	 * @param array $args カレンダー引数
	 * @param string $html HTML文字列
	 */
	private function setCachedHtml( $args, $html ) {
		$cache_key = $this->buildCacheKey( $args );
		$options = get_option( 'ns_tour_price_options', array() );
		$expiry = intval( $options['cache_expiry'] ?? 3600 );
		
		set_transient( $cache_key, $html, $expiry );
	}

	/**
	 * キャッシュキーを構築
	 *
	 * @param array $args カレンダー引数
	 * @return string キャッシュキー
	 */
	private function buildCacheKey( $args ) {
		$options = get_option( 'ns_tour_price_options', array() );
		
		$key_components = array(
			'tpc_html',
			sanitize_text_field( $args['tour'] ?? 'A1' ),
			sanitize_text_field( $args['month'] ?? gmdate( 'Y-m' ) ),
			intval( $args['duration'] ?? 4 ),
			intval( $options['heatmap_bins'] ?? 7 ),
			sanitize_text_field( $options['heatmap_mode'] ?? 'quantile' ),
			$args['heatmap'] ? '1' : '0',
			$args['confirmed_only'] ? '1' : '0',
			$args['show_legend'] ? '1' : '0',
		);

		// CSVファイルのmtimeハッシュも含める（データ変更検知）
		$csv_hash = $this->getCsvMtimeHash();
		if ( $csv_hash ) {
			$key_components[] = $csv_hash;
		}

		return implode( '_', $key_components );
	}

	/**
	 * CSVファイルのmtimeハッシュを取得（データ変更検知用）
	 *
	 * @return string|null ハッシュまたはnull
	 */
	private function getCsvMtimeHash() {
		$csv_files = array( 'seasons.csv', 'base_prices.csv', 'solo_fees.csv', 'daily_flags.csv' );
		$data_paths = array(
			NS_TOUR_PRICE_PLUGIN_DIR . 'data/',
			wp_upload_dir()['basedir'] . '/ns-tour_price/',
		);

		$mtimes = array();
		foreach ( $csv_files as $filename ) {
			foreach ( $data_paths as $path ) {
				$full_path = $path . $filename;
				if ( file_exists( $full_path ) ) {
					$mtimes[ $filename ] = filemtime( $full_path );
					break;
				}
			}
		}

		return empty( $mtimes ) ? null : md5( serialize( $mtimes ) );
	}

	private function renderCalendar( $calendar_data ) {
		$month_data = $calendar_data['month_data'];
		$args = $calendar_data['args'];
		$grid = $this->builder->getCalendarGrid( $calendar_data );
		$weekday_headers = $this->builder->getWeekdayHeaders( $month_data['week_start'] );

		ob_start();
		?>
		<?php
		// 動的CSS生成（新パレット対応）
		if ( $args['heatmap'] && ! empty( $calendar_data['legend'] ) ) {
			echo '<style type="text/css">';
			foreach ( $calendar_data['legend'] as $legend_item ) {
				if ( isset( $legend_item['color'] ) ) {
					$class = esc_attr( $legend_item['class'] );
					$color = esc_attr( $legend_item['color'] );
					$text_color = $this->getTextColor( $color );
					echo ".ns-tour-price-calendar .{$class} { background-color: {$color}; color: {$text_color}; }\n";
				}
			}
			echo '</style>';
		}
		?>
		<div class="tpc-booking-container">
		<div class="ns-tour-price-calendar" 
			 data-tour="<?php echo esc_attr( $args['tour'] ); ?>"
			 data-month="<?php echo esc_attr( $args['month'] ); ?>"
			 data-duration="<?php echo esc_attr( $args['duration'] ); ?>">
			
			<div class="calendar-header">
				<div class="calendar-title-row">
					<h3 class="calendar-title">
						<?php printf( 
							esc_html__( '%1$s年%2$s （%3$d日間）', 'ns-tour_price' ),
							$month_data['year'],
							$month_data['month_name'],
							$args['duration']
						); ?>
					</h3>
					
					<?php 
					$month_nav = NS_Tour_Price_Helpers::month_prev_next( $args['month'] );
					
					// 相対クエリ専用ヘルパーで月ナビURLを生成
					$prev_href = NS_Tour_Price_Helpers::build_relative_query( array( 'tpc_month' => $month_nav['prev'] ) );
					$next_href = NS_Tour_Price_Helpers::build_relative_query( array( 'tpc_month' => $month_nav['next'] ) );
					?>
					
					<nav class="tpc-nav">
						<a href="<?php echo esc_attr( $prev_href ); ?>" 
						   class="tpc-nav__btn tpc-nav__btn--prev tpc-nav-link" 
						   data-month="<?php echo esc_attr( $month_nav['prev'] ); ?>"
						   data-tour="<?php echo esc_attr( $args['tour'] ); ?>"
						   data-duration="<?php echo esc_attr( $args['duration'] ); ?>"
						   data-heatmap="<?php echo esc_attr( $args['heatmap'] ? '1' : '0' ); ?>"
						   data-show-legend="<?php echo esc_attr( $args['show_legend'] ? '1' : '0' ); ?>"
						   data-confirmed-only="<?php echo esc_attr( $args['confirmed_only'] ? '1' : '0' ); ?>"
						   aria-label="<?php esc_attr_e( '前月', 'ns-tour_price' ); ?>">
							<span class="tpc-nav__arrow">◀</span>
						</a>
						<a href="<?php echo esc_attr( $next_href ); ?>" 
						   class="tpc-nav__btn tpc-nav__btn--next tpc-nav-link"
						   data-month="<?php echo esc_attr( $month_nav['next'] ); ?>"
						   data-tour="<?php echo esc_attr( $args['tour'] ); ?>"
						   data-duration="<?php echo esc_attr( $args['duration'] ); ?>"
						   data-heatmap="<?php echo esc_attr( $args['heatmap'] ? '1' : '0' ); ?>"
						   data-show-legend="<?php echo esc_attr( $args['show_legend'] ? '1' : '0' ); ?>"
						   data-confirmed-only="<?php echo esc_attr( $args['confirmed_only'] ? '1' : '0' ); ?>"
						   aria-label="<?php esc_attr_e( '翌月', 'ns-tour_price' ); ?>">
							<span class="tpc-nav__arrow">▶</span>
						</a>
					</nav>
				</div>
				
				<div class="calendar-meta">
					<span class="tour-id"><?php printf( esc_html__( 'ツアー: %s', 'ns-tour_price' ), esc_html( $args['tour'] ) ); ?></span>
				</div>
				
				<?php
				// durationタブの表示
				$repo = NS_Tour_Price_Repo::getInstance();
				$available_durations = $repo->getAvailableDurations( $args['tour'] );
				$current_duration = intval( $args['duration'] );
				
				if ( count( $available_durations ) > 1 ) :
				?>
					<div class="tpc-duration-tabs">
						<?php foreach ( $available_durations as $duration ) : 
							$is_active = ( $duration === $current_duration );
							// 相対クエリ専用ヘルパーでdurationタブURLを生成
							$tab_href = NS_Tour_Price_Helpers::build_relative_query( array( 'tpc_duration' => $duration ) );
							
							$tab_classes = array( 'tpc-duration-tab', 'tpc-nav-link' );
							if ( $is_active ) {
								$tab_classes[] = 'is-active';
							}
						?>
							<a href="<?php echo esc_attr( $tab_href ); ?>" 
							   class="<?php echo esc_attr( implode( ' ', $tab_classes ) ); ?>"
							   data-duration="<?php echo esc_attr( $duration ); ?>"
							   data-tour="<?php echo esc_attr( $args['tour'] ); ?>"
							   data-month="<?php echo esc_attr( $args['month'] ); ?>"
							   data-heatmap="<?php echo esc_attr( $args['heatmap'] ? '1' : '0' ); ?>"
							   data-show-legend="<?php echo esc_attr( $args['show_legend'] ? '1' : '0' ); ?>"
							   data-confirmed-only="<?php echo esc_attr( $args['confirmed_only'] ? '1' : '0' ); ?>"
							   <?php if ( $is_active ) : ?>aria-current="page"<?php endif; ?>
							   aria-label="<?php printf( esc_attr__( '%d日間に切替', 'ns-tour_price' ), $duration ); ?>">
								<?php printf( esc_html__( '%d日', 'ns-tour_price' ), $duration ); ?>
							</a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $calendar_data['invalid_season_codes'] ) ) : ?>
				<div class="tpc-alert tpc-alert--warn">
					<?php printf(
						esc_html__( '一部の season_code（%s）が seasons.csv に存在しません。価格表示が不完全になる可能性があります。', 'ns-tour_price' ),
						esc_html( implode( ', ', $calendar_data['invalid_season_codes'] ) )
					); ?>
				</div>
			<?php endif; ?>

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

		<?php echo $this->renderBookingPanel( $args, $calendar_data ); ?>
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

	/**
	 * 背景色に基づいて適切なテキスト色を決定
	 *
	 * @param string $bg_color 背景色（#ffffff形式）
	 * @return string テキスト色（#000000 または #ffffff）
	 */
	private function getTextColor( $bg_color ) {
		$hex = ltrim( $bg_color, '#' );
		$r = hexdec( substr( $hex, 0, 2 ) );
		$g = hexdec( substr( $hex, 2, 2 ) );
		$b = hexdec( substr( $hex, 4, 2 ) );

		$brightness = ( ( $r * 299 ) + ( $g * 587 ) + ( $b * 114 ) ) / 1000;
		return ( $brightness > 128 ) ? '#000000' : '#ffffff';
	}

	/**
	 * 予約パネルを描画
	 */
	private function renderBookingPanel( $args, $calendar_data ) {
		error_log("renderBookingPanel called with tour: " . $args['tour']); // デバッグ用
		$available_durations = $this->repo->getAvailableDurations( $args['tour'] );
		error_log("renderBookingPanel: got available_durations");
		
		ob_start();
		?>
		<aside class="tpc-booking-panel" aria-label="<?php esc_attr_e( '予約内容の選択', 'ns-tour_price' ); ?>">
			<div class="tpc-booking-date">
				<div class="tpc-booking-date__label"><?php esc_html_e( '出発日', 'ns-tour_price' ); ?></div>
				<div class="tpc-booking-date__value" data-tpc-date><?php esc_html_e( '未選択', 'ns-tour_price' ); ?></div>
				<div class="tpc-booking-date__season" data-tpc-season></div>
			</div>

			<div class="tpc-duration-tabs" data-tpc-duration-tabs>
				<?php foreach ( $available_durations as $duration ) : ?>
					<button type="button" 
							role="tab" 
							class="tpc-duration-tab<?php echo $duration === $args['duration'] ? ' is-active' : ''; ?>" 
							data-duration="<?php echo esc_attr( $duration ); ?>"
							<?php if ( $duration === $args['duration'] ) echo 'aria-current="page"'; ?>>
						<?php printf( esc_html__( '%d日間', 'ns-tour_price' ), $duration ); ?>
					</button>
				<?php endforeach; ?>
			</div>

			<div class="tpc-booking-group">
				<label for="tpc-pax"><?php esc_html_e( '参加人数', 'ns-tour_price' ); ?></label>
				<select id="tpc-pax" data-tpc-pax>
					<option value="1"><?php esc_html_e( '1名', 'ns-tour_price' ); ?></option>
					<option value="2"><?php esc_html_e( '2名', 'ns-tour_price' ); ?></option>
					<option value="3"><?php esc_html_e( '3名', 'ns-tour_price' ); ?></option>
					<option value="4"><?php esc_html_e( '4名', 'ns-tour_price' ); ?></option>
					<option value="5"><?php esc_html_e( '5名', 'ns-tour_price' ); ?></option>
					<option value="6"><?php esc_html_e( '6名', 'ns-tour_price' ); ?></option>
				</select>
			</div>
			
			<?php error_log("Renderer.php:502 - Before tour options section"); ?>

			<?php 
			error_log("Renderer.php:503 - About to get tour options for tour: {$args['tour']}");
			$tour_options = $this->booking_preview->getTourOptions( $args['tour'] );
			error_log("Renderer.php:505 - Got tour options for {$args['tour']}: " . print_r($tour_options, true));
			if ( ! empty( $tour_options ) ) : 
				error_log("Renderer.php:507 - Tour options not empty, showing options section");
			else:
				error_log("Renderer.php:509 - Tour options empty, hiding options section");
			endif;
			if ( ! empty( $tour_options ) ) : ?>
			<div class="tpc-booking-options">
				<div class="tpc-booking-options__label"><?php esc_html_e( 'オプション（任意）', 'ns-tour_price' ); ?></div>
				<?php foreach ( $tour_options as $option ) : ?>
					<label class="tpc-option">
						<input type="checkbox" 
							   data-tpc-option-id="<?php echo esc_attr( $option['option_id'] ); ?>"
							   data-price-min="<?php echo esc_attr( $option['price_min'] ?? 0 ); ?>"
							   data-price-max="<?php echo esc_attr( $option['price_max'] ?? 0 ); ?>"
							   data-affects-total="<?php echo esc_attr( $option['affects_total'] ?? 'false' ); ?>" />
						<span class="option-label">
							<?php echo esc_html( $option['option_label'] ); ?>
							<?php if ( ! empty( $option['show_price'] ) && $option['show_price'] === 'true' ) : ?>
								<?php if ( $option['price_min'] == $option['price_max'] ) : ?>
									（¥<?php echo number_format( $option['price_min'] ); ?>）
								<?php else : ?>
									（¥<?php echo number_format( $option['price_min'] ); ?>～¥<?php echo number_format( $option['price_max'] ); ?>）
								<?php endif; ?>
							<?php endif; ?>
						</span>
						<?php if ( ! empty( $option['description'] ) ) : ?>
							<div class="option-description"><?php echo esc_html( $option['description'] ); ?></div>
						<?php endif; ?>
					</label>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<div class="tpc-quote">
				<div class="tpc-quote__row">
					<span><?php esc_html_e( '基本料金', 'ns-tour_price' ); ?></span>
					<strong data-tpc-base>—</strong>
				</div>
				<div class="tpc-quote__row">
					<span><?php esc_html_e( 'お一人様参加料金', 'ns-tour_price' ); ?></span>
					<strong data-tpc-solo>—</strong>
				</div>
				<div class="tpc-quote__row">
					<span><?php esc_html_e( '参加人数', 'ns-tour_price' ); ?></span>
					<strong data-tpc-pax-view>—</strong>
				</div>
				<div class="tpc-quote__total">
					<span><?php esc_html_e( '合計概算金額', 'ns-tour_price' ); ?></span>
					<strong data-tpc-total>—</strong>
				</div>
				<small class="tpc-quote__note"><?php esc_html_e( '※運賃変動等により金額は変更になる場合があります。', 'ns-tour_price' ); ?></small>
			</div>

			<button class="tpc-submit" data-tpc-submit disabled>
				<?php esc_html_e( '申込フォームへ', 'ns-tour_price' ); ?>
			</button>
		</aside>
		<?php
		
		return ob_get_clean();
	}
}