<?php
/**
 * Booking Preview Template - 旅行内容選択フォーム
 * 
 * カレンダー選択後に表示される予約内容確認フォーム
 *
 * @package NS_Tour_Price
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// テンプレート変数の取得
$args = $args ?? array();
$season_info = $season_info ?? array();
$available_durations = $available_durations ?? array();
$departure_options = $departure_options ?? array();
$tour_options = $tour_options ?? array();
$price_calculation = $price_calculation ?? array();

$tour = $args['tour'] ?? '';
$date = $args['date'] ?? '';
$duration = intval( $args['duration'] ?? 4 );
$pax = intval( $args['pax'] ?? 1 );
$departure = $args['departure'] ?? '成田';
$selected_options = (array) ( $args['options'] ?? array() );
?>

<div class="tpc-booking-preview" 
     data-tour="<?php echo esc_attr( $tour ); ?>" 
     data-date="<?php echo esc_attr( $date ); ?>">
	
	<div class="tpc-booking-header">
		<h3 class="tpc-booking-title">
			<?php esc_html_e( '旅行内容選択', 'ns-tour_price' ); ?>
		</h3>
		<p class="tpc-booking-subtitle">
			<?php esc_html_e( '下記内容を確認・変更し、申込フォームへお進みください', 'ns-tour_price' ); ?>
		</p>
	</div>

	<form class="tpc-booking-form" id="tpc-booking-form">
		<!-- 選択済み情報 -->
		<div class="tpc-booking-section tpc-selected-info">
			<h4 class="tpc-section-title"><?php esc_html_e( '選択済み情報', 'ns-tour_price' ); ?></h4>
			
			<div class="tpc-info-grid">
				<div class="tpc-info-item">
					<label><?php esc_html_e( '旅行日付', 'ns-tour_price' ); ?></label>
					<div class="tpc-info-value">
						<strong><?php echo esc_html( date_i18n( 'Y年n月j日', strtotime( $date ) ) ); ?></strong>
						<span class="tpc-weekday">(<?php echo esc_html( date_i18n( 'l', strtotime( $date ) ) ); ?>)</span>
					</div>
				</div>

				<?php if ( ! empty( $season_info['season_code'] ) ) : ?>
				<div class="tpc-info-item">
					<label><?php esc_html_e( 'シーズン', 'ns-tour_price' ); ?></label>
					<div class="tpc-info-value">
						<span class="tpc-season-badge" 
							  data-season="<?php echo esc_attr( $season_info['season_code'] ); ?>">
							<?php echo esc_html( $season_info['season_label'] ); ?>
						</span>
					</div>
				</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- 日数選択 -->
		<?php if ( ! empty( $available_durations ) && count( $available_durations ) > 1 ) : ?>
		<div class="tpc-booking-section tpc-duration-section">
			<h4 class="tpc-section-title"><?php esc_html_e( '日数選択', 'ns-tour_price' ); ?></h4>
			
			<div class="tpc-duration-tabs">
				<?php foreach ( $available_durations as $dur ) : ?>
					<label class="tpc-duration-tab <?php echo $dur === $duration ? 'active' : ''; ?>">
						<input type="radio" 
							   name="duration" 
							   value="<?php echo esc_attr( $dur ); ?>"
							   <?php checked( $dur, $duration ); ?>>
						<span class="tpc-tab-text"><?php echo esc_html( $dur ); ?>日間</span>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php else : ?>
			<input type="hidden" name="duration" value="<?php echo esc_attr( $duration ); ?>">
		<?php endif; ?>

		<!-- 出発地選択 -->
		<div class="tpc-booking-section tpc-departure-section">
			<h4 class="tpc-section-title"><?php esc_html_e( '出発地・帰着地', 'ns-tour_price' ); ?></h4>
			
			<select name="departure" class="tpc-departure-select">
				<?php foreach ( $departure_options as $code => $label ) : ?>
					<option value="<?php echo esc_attr( $code ); ?>" 
							<?php selected( $code, $departure ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<!-- 人数選択 -->
		<div class="tpc-booking-section tpc-pax-section">
			<h4 class="tpc-section-title"><?php esc_html_e( '人数（ご本人含む）', 'ns-tour_price' ); ?></h4>
			
			<div class="tpc-pax-controls">
				<button type="button" class="tpc-pax-btn tpc-pax-minus" data-action="minus">−</button>
				<input type="number" 
					   name="pax" 
					   class="tpc-pax-input" 
					   value="<?php echo esc_attr( $pax ); ?>" 
					   min="1" 
					   max="20">
				<button type="button" class="tpc-pax-btn tpc-pax-plus" data-action="plus">＋</button>
				<span class="tpc-pax-unit">名様</span>
			</div>
			
			<?php if ( $pax === 1 ) : ?>
				<p class="tpc-pax-notice">
					<small><?php esc_html_e( 'お一人様参加の場合は追加料金が発生します', 'ns-tour_price' ); ?></small>
				</p>
			<?php endif; ?>
		</div>

		<!-- オプション選択 -->
		<?php if ( ! empty( $tour_options ) ) : ?>
		<div class="tpc-booking-section tpc-options-section">
			<h4 class="tpc-section-title"><?php esc_html_e( 'オプション', 'ns-tour_price' ); ?></h4>
			
			<div class="tpc-options-list">
				<?php foreach ( $tour_options as $option ) : ?>
					<label class="tpc-option-item">
						<input type="checkbox" 
							   name="options[]" 
							   value="<?php echo esc_attr( $option['option_id'] ); ?>"
							   <?php checked( in_array( $option['option_id'], $selected_options ) ); ?>>
						
						<div class="tpc-option-info">
							<div class="tpc-option-header">
								<span class="tpc-option-label">
									<?php echo esc_html( $option['option_label'] ); ?>
								</span>
								<span class="tpc-option-price">
									<?php if ( $option['price_min'] === $option['price_max'] ) : ?>
										¥<?php echo number_format( $option['price_min'] ); ?>
									<?php else : ?>
										¥<?php echo number_format( $option['price_min'] ); ?>〜<?php echo number_format( $option['price_max'] ); ?>
									<?php endif; ?>
								</span>
							</div>
							
							<?php if ( ! empty( $option['description'] ) ) : ?>
								<p class="tpc-option-description">
									<?php echo esc_html( $option['description'] ); ?>
								</p>
							<?php endif; ?>
						</div>
					</label>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endif; ?>

		<!-- 料金表示 -->
		<div class="tpc-booking-section tpc-pricing-section">
			<h4 class="tpc-section-title"><?php esc_html_e( '料金概算', 'ns-tour_price' ); ?></h4>
			
			<div class="tpc-pricing-breakdown" id="tpc-pricing-breakdown">
				<div class="tpc-price-row">
					<span class="tpc-price-label">
						<?php esc_html_e( '基本料金', 'ns-tour_price' ); ?>
						<small>(<?php echo esc_html( $pax ); ?>名 × <?php echo esc_html( $duration ); ?>日間)</small>
					</span>
					<span class="tpc-price-value" id="tpc-base-total">
						<?php echo esc_html( $price_calculation['formatted']['base_total'] ); ?>
					</span>
				</div>

				<div class="tpc-price-row" id="tpc-solo-fee-row" style="display: <?php echo $pax === 1 ? 'flex' : 'none'; ?>;">
					<span class="tpc-price-label">
						<?php esc_html_e( 'お一人様参加料金', 'ns-tour_price' ); ?>
					</span>
					<span class="tpc-price-value" id="tpc-solo-fee">
						<?php echo esc_html( $price_calculation['formatted']['solo_fee'] ); ?>
					</span>
				</div>

				<div class="tpc-price-row" id="tpc-option-total-row" style="display: <?php echo $price_calculation['option_total'] > 0 ? 'flex' : 'none'; ?>;">
					<span class="tpc-price-label">
						<?php esc_html_e( 'オプション料金', 'ns-tour_price' ); ?>
					</span>
					<span class="tpc-price-value" id="tpc-option-total">
						<?php echo esc_html( $price_calculation['formatted']['option_total'] ); ?>
					</span>
				</div>

				<div class="tpc-price-row tpc-total-row">
					<span class="tpc-price-label">
						<?php esc_html_e( '合計金額', 'ns-tour_price' ); ?>
					</span>
					<span class="tpc-price-value tpc-total-price" id="tpc-total-price">
						<?php echo esc_html( $price_calculation['formatted']['total'] ); ?>
					</span>
				</div>
			</div>

			<p class="tpc-pricing-note">
				<small><?php esc_html_e( '※ 概算金額です。正確な料金は申込フォームで確認いただけます。', 'ns-tour_price' ); ?></small>
			</p>
		</div>

		<!-- 申込ボタン -->
		<div class="tpc-booking-section tpc-submit-section">
			<button type="submit" class="tpc-submit-btn">
				<span class="tpc-submit-text"><?php esc_html_e( '申込フォームへ', 'ns-tour_price' ); ?></span>
				<span class="tpc-submit-arrow">→</span>
			</button>
			
			<p class="tpc-submit-note">
				<small><?php esc_html_e( '次の画面で詳細情報をご入力いただきます', 'ns-tour_price' ); ?></small>
			</p>
		</div>

		<!-- 隠しフィールド -->
		<input type="hidden" name="tour" value="<?php echo esc_attr( $tour ); ?>">
		<input type="hidden" name="date" value="<?php echo esc_attr( $date ); ?>">
	</form>
</div>