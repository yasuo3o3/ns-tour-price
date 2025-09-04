<?php
/**
 * Admin Interface
 *
 * @package NS_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class NS_Tour_Price_Admin {

	private $repo;

	public function __construct() {
		$this->repo = NS_Tour_Price_Repo::getInstance();
		$this->hooks();
	}

	private function hooks() {
		add_action( 'admin_menu', array( $this, 'addAdminMenu' ) );
		add_action( 'admin_init', array( $this, 'adminInit' ) );
		add_action( 'wp_ajax_ns_tour_price_clear_cache', array( $this, 'ajaxClearCache' ) );
		add_action( 'wp_ajax_ns_tour_price_test_data', array( $this, 'ajaxTestData' ) );
	}

	public function addAdminMenu() {
		add_management_page(
			__( 'NS Tour Price Settings', 'ns-tour_price' ),
			__( 'NS Tour Price', 'ns-tour_price' ),
			'manage_options',
			'ns-tour-price',
			array( $this, 'adminPage' )
		);
	}

	public function adminInit() {
		register_setting( 'ns_tour_price_settings', 'ns_tour_price_options', array( $this, 'sanitizeOptions' ) );

		add_settings_section(
			'ns_tour_price_general',
			__( 'General Settings', 'ns-tour_price' ),
			array( $this, 'generalSectionCallback' ),
			'ns_tour_price_settings'
		);

		add_settings_field(
			'data_source',
			__( 'Data Source', 'ns-tour_price' ),
			array( $this, 'dataSourceFieldCallback' ),
			'ns_tour_price_settings',
			'ns_tour_price_general'
		);

		add_settings_field(
			'week_start',
			__( 'Week Starts On', 'ns-tour_price' ),
			array( $this, 'weekStartFieldCallback' ),
			'ns_tour_price_settings',
			'ns_tour_price_general'
		);

		add_settings_field(
			'confirmed_badge_enabled',
			__( 'Show Confirmed Badges', 'ns-tour_price' ),
			array( $this, 'confirmedBadgeFieldCallback' ),
			'ns_tour_price_settings',
			'ns_tour_price_general'
		);

		add_settings_field(
			'cache_expiry',
			__( 'Cache Expiry (seconds)', 'ns-tour_price' ),
			array( $this, 'cacheExpiryFieldCallback' ),
			'ns_tour_price_settings',
			'ns_tour_price_general'
		);

		add_settings_field(
			'heatmap_bins',
			__( 'Heatmap Bins', 'ns-tour_price' ),
			array( $this, 'heatmapBinsFieldCallback' ),
			'ns_tour_price_settings',
			'ns_tour_price_general'
		);

		add_settings_field(
			'heatmap_mode',
			__( 'Heatmap Mode', 'ns-tour_price' ),
			array( $this, 'heatmapModeFieldCallback' ),
			'ns_tour_price_settings',
			'ns_tour_price_general'
		);
	}

	public function adminPage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'ns_tour_price_messages',
				'ns_tour_price_message',
				__( 'Settings saved.', 'ns-tour_price' ),
				'updated'
			);
		}

		$data_source_info = $this->repo->getDataSourceInfo();
		$is_data_available = $this->repo->isDataAvailable();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<?php settings_errors( 'ns_tour_price_messages' ); ?>

			<div class="notice notice-info">
				<p><strong><?php esc_html_e( 'Data Source Status:', 'ns-tour_price' ); ?></strong></p>
				<?php if ( $is_data_available ) : ?>
					<p style="color: green;">
						✅ <?php printf( esc_html__( 'Active: %s', 'ns-tour_price' ), esc_html( $data_source_info['active'] ) ); ?>
					</p>
				<?php else : ?>
					<p style="color: red;">
						❌ <?php esc_html_e( 'No data source available. Please check CSV files.', 'ns-tour_price' ); ?>
					</p>
				<?php endif; ?>
				
				<details>
					<summary><?php esc_html_e( 'Show all data sources', 'ns-tour_price' ); ?></summary>
					<ul>
					<?php foreach ( $data_source_info['all'] as $key => $info ) : ?>
						<li>
							<?php echo esc_html( $info['name'] ); ?>: 
							<?php if ( $info['available'] ) : ?>
								<span style="color: green;"><?php esc_html_e( 'Available', 'ns-tour_price' ); ?></span>
							<?php else : ?>
								<span style="color: red;"><?php esc_html_e( 'Not available', 'ns-tour_price' ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
					</ul>
				</details>
			</div>

			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( 'CSV File Locations:', 'ns-tour_price' ); ?></strong></p>
				<ol>
					<li><code><?php echo esc_html( NS_TOUR_PRICE_PLUGIN_DIR . 'data/' ); ?></code> (<?php esc_html_e( 'Priority', 'ns-tour_price' ); ?>)</li>
					<li><code><?php echo esc_html( wp_upload_dir()['basedir'] . '/ns-tour_price/' ); ?></code> (<?php esc_html_e( 'Fallback', 'ns-tour_price' ); ?>)</li>
				</ol>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'ns_tour_price_settings' );
				do_settings_sections( 'ns_tour_price_settings' );
				submit_button();
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Tools', 'ns-tour_price' ); ?></h2>
			
			<div class="card">
				<h3><?php esc_html_e( 'Cache Management', 'ns-tour_price' ); ?></h3>
				<p><?php esc_html_e( 'Clear all cached data to force reload from CSV files.', 'ns-tour_price' ); ?></p>
				<button type="button" class="button button-secondary" id="clear-cache-btn">
					<?php esc_html_e( 'Clear Cache', 'ns-tour_price' ); ?>
				</button>
				<span id="cache-status"></span>
			</div>

			<div class="card">
				<h3><?php esc_html_e( 'Data Test', 'ns-tour_price' ); ?></h3>
				<p><?php esc_html_e( 'Test data loading for a specific tour ID.', 'ns-tour_price' ); ?></p>
				<input type="text" id="test-tour-id" placeholder="A1" value="A1">
				<button type="button" class="button button-secondary" id="test-data-btn">
					<?php esc_html_e( 'Test Data', 'ns-tour_price' ); ?>
				</button>
				<div id="test-results" style="margin-top: 10px;"></div>
			</div>

			<hr>

			<h2><?php esc_html_e( 'Usage Examples', 'ns-tour_price' ); ?></h2>
			
			<div class="card">
				<h3><?php esc_html_e( 'Shortcode', 'ns-tour_price' ); ?></h3>
				<code>[tour_price tour="A1" month="2024-07" duration="4" heatmap="true" show_legend="true"]</code>
				
				<h3><?php esc_html_e( 'Block', 'ns-tour_price' ); ?></h3>
				<p><?php esc_html_e( 'Search for "ツアー価格カレンダー" in the block editor.', 'ns-tour_price' ); ?></p>
			</div>

			<hr>

			<h2><?php esc_html_e( 'ソロフィー設定', 'ns-tour_price' ); ?></h2>

			<div class="card">
				<h3><?php esc_html_e( 'solo_fees.csv について', 'ns-tour_price' ); ?></h3>
				<p><?php esc_html_e( 'ツアーの基本価格にソロフィーを加算するための設定です。ツアーIDと日数の組み合わせでソロフィーが決定されます。', 'ns-tour_price' ); ?></p>
				
				<p><strong><?php esc_html_e( '配置場所:', 'ns-tour_price' ); ?></strong></p>
				<ol>
					<li><code><?php echo esc_html( NS_TOUR_PRICE_PLUGIN_DIR . 'data/solo_fees.csv' ); ?></code> (<?php esc_html_e( '優先', 'ns-tour_price' ); ?>)</li>
					<li><code><?php echo esc_html( wp_upload_dir()['basedir'] . '/ns-tour_price/solo_fees.csv' ); ?></code> (<?php esc_html_e( 'フォールバック', 'ns-tour_price' ); ?>)</li>
				</ol>

				<p><strong><?php esc_html_e( 'CSVスキーマ:', 'ns-tour_price' ); ?></strong></p>
				<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px;">tour_id,duration_days,solo_fee
A1,4,18000
A1,5,22000
A1,6,26000
A2,5,22000
A2,6,26000</pre>

				<p><strong><?php esc_html_e( '機能:', 'ns-tour_price' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'tour_id と duration_days の組み合わせでソロフィーを指定', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( 'カレンダー表示価格は「ベース価格 + ソロフィー」で計算される', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( 'ベース価格が取得できない日付ではソロフィーも加算されない', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( 'solo_fees.csv にない組み合わせのソロフィーは0円', 'ns-tour_price' ); ?></li>
				</ul>
			</div>

			<hr>

			<h2><?php esc_html_e( 'CSV データ書式', 'ns-tour_price' ); ?></h2>

			<div class="card">
				<h3><?php esc_html_e( 'seasons.csv の日付書式', 'ns-tour_price' ); ?></h3>
				<p><?php esc_html_e( 'date_start、date_end 列で使用できる日付書式は以下の通りです。', 'ns-tour_price' ); ?></p>
				
				<p><strong><?php esc_html_e( '推奨書式:', 'ns-tour_price' ); ?></strong></p>
				<ul>
					<li><code>YYYY-MM-DD</code> <?php esc_html_e( '（例: 2025-04-15）', 'ns-tour_price' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( '受入可能書式:', 'ns-tour_price' ); ?></strong></p>
				<ul>
					<li><code>YYYY/M/D</code> <?php esc_html_e( '（例: 2025/4/15）', 'ns-tour_price' ); ?></li>
					<li><code>YYYY/MM/DD</code> <?php esc_html_e( '（例: 2025/04/15）', 'ns-tour_price' ); ?></li>
					<li><code>YYYY-M-D</code> <?php esc_html_e( '（例: 2025-4-15）', 'ns-tour_price' ); ?></li>
					<li><code>YYYY.M.D</code> <?php esc_html_e( '（例: 2025.4.15）', 'ns-tour_price' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( '自動正規化機能:', 'ns-tour_price' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( '全角数字・記号も半角に変換（２０２５－０４－１５ → 2025-04-15）', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( 'すべて内部では YYYY-MM-DD 形式で処理される', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( 'パース失敗時は error_log に詳細な統計情報を出力', 'ns-tour_price' ); ?></li>
				</ul>
			</div>

			<div class="card">
				<h3><?php esc_html_e( 'ヒートマップ・凡例の価格範囲', 'ns-tour_price' ); ?></h3>
				<p><?php esc_html_e( 'カレンダーのヒートマップと凡例は「全期間（全シーズン）」を基準に計算されます。', 'ns-tour_price' ); ?></p>
				<ul>
					<li><?php esc_html_e( '同一 tour_id × duration_days の全ての base_prices から価格範囲を決定', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( '月を跨いでも色基準と凡例が一貫して表示', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( '当月に出ていない価格も凡例に表示される', 'ns-tour_price' ); ?></li>
				</ul>
			</div>

			<hr>

			<h2><?php esc_html_e( 'Season Code エイリアス', 'ns-tour_price' ); ?></h2>

			<div class="card">
				<h3><?php esc_html_e( 'season_aliases.csv について', 'ns-tour_price' ); ?></h3>
				<p><?php esc_html_e( 'seasons.csv と base_prices.csv で異なる season_code 表記（A/B/C と LOW/MID/HIGH など）を使用している場合、season_aliases.csv を配置することで表記差を自動吸収できます。', 'ns-tour_price' ); ?></p>
				
				<p><strong><?php esc_html_e( '配置場所:', 'ns-tour_price' ); ?></strong></p>
				<ol>
					<li><code><?php echo esc_html( NS_TOUR_PRICE_PLUGIN_DIR . 'data/season_aliases.csv' ); ?></code> (<?php esc_html_e( '優先', 'ns-tour_price' ); ?>)</li>
					<li><code><?php echo esc_html( wp_upload_dir()['basedir'] . '/ns-tour_price/season_aliases.csv' ); ?></code> (<?php esc_html_e( 'フォールバック', 'ns-tour_price' ); ?>)</li>
				</ol>

				<p><strong><?php esc_html_e( 'CSVスキーマ:', 'ns-tour_price' ); ?></strong></p>
				<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px;">tour_id,alias,season_code
A1,A,LOW
A1,B,HIGH
A1,C,MID
A1,GREEN,LOW
A1,ハイ,HIGH
A1,WINTER,WINTER</pre>

				<p><strong><?php esc_html_e( '機能:', 'ns-tour_price' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'base_prices.csv で "A" → seasons.csv の "LOW" にマッピング', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( '大文字・小文字、全角・半角、前後空白の差も自動正規化', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( 'エイリアスファイルがない環境でも、正規化のみで基本的な差は吸収', 'ns-tour_price' ); ?></li>
					<li><?php esc_html_e( '不整合がある場合は error_log に詳細、フロントエンドには警告表示', 'ns-tour_price' ); ?></li>
				</ul>
			</div>
		</div>

		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			const clearCacheBtn = document.getElementById('clear-cache-btn');
			const cacheStatus = document.getElementById('cache-status');
			const testDataBtn = document.getElementById('test-data-btn');
			const testResults = document.getElementById('test-results');

			clearCacheBtn.addEventListener('click', function() {
				clearCacheBtn.disabled = true;
				cacheStatus.innerHTML = '⏳ Clearing...';

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=ns_tour_price_clear_cache&nonce=' + encodeURIComponent('<?php echo wp_create_nonce( "ns_tour_price_clear_cache" ); ?>')
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						cacheStatus.innerHTML = '<span style="color: green;">✅ Cache cleared successfully!</span>';
					} else {
						cacheStatus.innerHTML = '<span style="color: red;">❌ Failed to clear cache</span>';
					}
					clearCacheBtn.disabled = false;
					setTimeout(() => { cacheStatus.innerHTML = ''; }, 3000);
				})
				.catch(error => {
					cacheStatus.innerHTML = '<span style="color: red;">❌ Error occurred</span>';
					clearCacheBtn.disabled = false;
					setTimeout(() => { cacheStatus.innerHTML = ''; }, 3000);
				});
			});

			testDataBtn.addEventListener('click', function() {
				const tourId = document.getElementById('test-tour-id').value;
				if (!tourId) {
					alert('Please enter a tour ID');
					return;
				}

				testDataBtn.disabled = true;
				testResults.innerHTML = '⏳ Testing...';

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=ns_tour_price_test_data&tour_id=' + encodeURIComponent(tourId) + '&nonce=' + encodeURIComponent('<?php echo wp_create_nonce( "ns_tour_price_test_data" ); ?>')
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						testResults.innerHTML = '<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto;">' + 
							JSON.stringify(data.data, null, 2) + '</pre>';
					} else {
						testResults.innerHTML = '<div style="color: red;">❌ ' + (data.data || 'Test failed') + '</div>';
					}
					testDataBtn.disabled = false;
				})
				.catch(error => {
					testResults.innerHTML = '<div style="color: red;">❌ Error occurred</div>';
					testDataBtn.disabled = false;
				});
			});
		});
		</script>
		<?php
	}

	public function generalSectionCallback() {
		echo '<p>' . esc_html__( 'Configure the basic settings for NS Tour Price Calendar.', 'ns-tour_price' ) . '</p>';
	}

	public function dataSourceFieldCallback() {
		$options = get_option( 'ns_tour_price_options', array() );
		$current = $options['data_source'] ?? 'csv';
		$sources = $this->repo->getDataSourceInfo()['all'];
		?>
		<select name="ns_tour_price_options[data_source]">
			<?php foreach ( $sources as $key => $info ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
					<?php echo esc_html( $info['name'] ); ?>
					<?php if ( ! $info['available'] ) : ?>
						(<?php esc_html_e( 'Not Available', 'ns-tour_price' ); ?>)
					<?php endif; ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function weekStartFieldCallback() {
		$options = get_option( 'ns_tour_price_options', array() );
		$current = $options['week_start'] ?? 'sunday';
		?>
		<select name="ns_tour_price_options[week_start]">
			<option value="sunday" <?php selected( $current, 'sunday' ); ?>><?php esc_html_e( 'Sunday', 'ns-tour_price' ); ?></option>
			<option value="monday" <?php selected( $current, 'monday' ); ?>><?php esc_html_e( 'Monday', 'ns-tour_price' ); ?></option>
		</select>
		<?php
	}

	public function confirmedBadgeFieldCallback() {
		$options = get_option( 'ns_tour_price_options', array() );
		$current = $options['confirmed_badge_enabled'] ?? false;
		?>
		<input type="checkbox" name="ns_tour_price_options[confirmed_badge_enabled]" value="1" <?php checked( $current ); ?>>
		<span><?php esc_html_e( 'Show confirmed booking badges when daily_flags.csv is available', 'ns-tour_price' ); ?></span>
		<?php
	}

	public function cacheExpiryFieldCallback() {
		$options = get_option( 'ns_tour_price_options', array() );
		$current = $options['cache_expiry'] ?? 3600;
		?>
		<input type="number" name="ns_tour_price_options[cache_expiry]" value="<?php echo esc_attr( $current ); ?>" min="300" max="86400">
		<span><?php esc_html_e( '(300-86400 seconds)', 'ns-tour_price' ); ?></span>
		<?php
	}

	public function heatmapBinsFieldCallback() {
		$options = get_option( 'ns_tour_price_options', array() );
		$current = intval( $options['heatmap_bins'] ?? 7 );
		?>
		<select name="ns_tour_price_options[heatmap_bins]">
			<option value="5" <?php selected( $current, 5 ); ?>>5 <?php esc_html_e( 'bins', 'ns-tour_price' ); ?></option>
			<option value="7" <?php selected( $current, 7 ); ?>>7 <?php esc_html_e( 'bins', 'ns-tour_price' ); ?></option>
			<option value="10" <?php selected( $current, 10 ); ?>>10 <?php esc_html_e( 'bins', 'ns-tour_price' ); ?></option>
		</select>
		<span><?php esc_html_e( 'Number of heatmap color levels', 'ns-tour_price' ); ?></span>
		<?php
	}

	public function heatmapModeFieldCallback() {
		$options = get_option( 'ns_tour_price_options', array() );
		$current = sanitize_text_field( $options['heatmap_mode'] ?? 'quantile' );
		?>
		<select name="ns_tour_price_options[heatmap_mode]">
			<option value="quantile" <?php selected( $current, 'quantile' ); ?>><?php esc_html_e( 'Quantile (Recommended)', 'ns-tour_price' ); ?></option>
			<option value="linear" <?php selected( $current, 'linear' ); ?>><?php esc_html_e( 'Linear', 'ns-tour_price' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Quantile mode distributes colors more evenly across price ranges, avoiding blue bias.', 'ns-tour_price' ); ?>
		</p>
		<?php
	}

	public function sanitizeOptions( $input ) {
		$sanitized = array();
		
		if ( isset( $input['data_source'] ) ) {
			$sanitized['data_source'] = sanitize_key( $input['data_source'] );
		}

		if ( isset( $input['week_start'] ) ) {
			$sanitized['week_start'] = in_array( $input['week_start'], array( 'sunday', 'monday' ) ) 
				? $input['week_start'] : 'sunday';
		}

		$sanitized['confirmed_badge_enabled'] = ! empty( $input['confirmed_badge_enabled'] );

		if ( isset( $input['cache_expiry'] ) ) {
			$cache_expiry = intval( $input['cache_expiry'] );
			$sanitized['cache_expiry'] = max( 300, min( 86400, $cache_expiry ) );
		}

		if ( isset( $input['heatmap_bins'] ) ) {
			$bins = intval( $input['heatmap_bins'] );
			$sanitized['heatmap_bins'] = in_array( $bins, array( 5, 7, 10 ), true ) ? $bins : 7;
		}

		if ( isset( $input['heatmap_mode'] ) ) {
			$mode = sanitize_text_field( $input['heatmap_mode'] );
			$sanitized['heatmap_mode'] = in_array( $mode, array( 'quantile', 'linear' ), true ) ? $mode : 'quantile';
		}

		return $sanitized;
	}

	public function ajaxClearCache() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ns_tour_price_clear_cache' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// キャッシュクリア実行
		$this->repo->clearCache();
		
		// キャッシュクリア後の確認とログ出力のため、強制的に各CSVを再読込
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'NS Tour Price: Cache cleared, forcing CSV reload for log verification' );
		}

		// テスト用のデータロードでログを出力（デバッグ目的）
		$test_tours = array( 'A1', 'A2' ); // よく使われるツアーIDでテスト
		foreach ( $test_tours as $test_tour_id ) {
			$seasons_count = count( $this->repo->getSeasons( $test_tour_id ) );
			$prices_count = count( $this->repo->getBasePrices( $test_tour_id ) );
			$solo_fees_count = count( $this->repo->getSoloFees( $test_tour_id ) );
			
			// 再ロード成功の場合のみ統計ログを出力
			if ( $seasons_count > 0 || $prices_count > 0 ) {
				$this->repo->logNormalizationStatistics( $test_tour_id );
				break; // 一つ成功したら終了
			}
		}

		// データソース可用性も再チェック
		$data_source_info = $this->repo->getDataSourceInfo();
		
		$response_message = sprintf( 
			'Cache cleared successfully. Data source: %s (%s)', 
			$data_source_info['active'] ?? 'none',
			$this->repo->isDataAvailable() ? 'available' : 'unavailable'
		);
		
		wp_send_json_success( $response_message );
	}

	public function ajaxTestData() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'ns_tour_price_test_data' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$tour_id = sanitize_text_field( $_POST['tour_id'] );
		if ( empty( $tour_id ) ) {
			wp_send_json_error( 'Tour ID required' );
		}

		$test_data = array(
			'tour_id' => $tour_id,
			'seasons' => $this->repo->getSeasons( $tour_id ),
			'prices' => $this->repo->getBasePrices( $tour_id ),
			'flags' => $this->repo->getDailyFlags( $tour_id ),
			'solo_fees' => $this->repo->getSoloFees( $tour_id ),
			'data_source' => $this->repo->getDataSourceInfo(),
		);

		wp_send_json_success( $test_data );
	}
}