<?php
/**
 * Admin Interface
 *
 * @package Andw_Tour_Price
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Andw_Tour_Price_Admin {

	private $repo;

	public function __construct() {
		$this->repo = Andw_Tour_Price_Repo::getInstance();
		$this->hooks();
	}

	private function hooks() {
		add_action( 'admin_menu', array( $this, 'addAdminMenu' ) );
		add_action( 'admin_init', array( $this, 'adminInit' ) );
		add_action( 'wp_ajax_andw_tour_price_clear_cache', array( $this, 'ajaxClearCache' ) );
		add_action( 'wp_ajax_andw_tour_price_test_data', array( $this, 'ajaxTestData' ) );
	}

	public function addAdminMenu() {
		add_options_page(
			__( 'andW Tour Price Settings', 'andw-tour-price' ),
			__( 'andW Tour Price', 'andw-tour-price' ),
			'manage_options',
			'andw-tour-price',
			array( $this, 'adminPage' )
		);
	}

	public function adminInit() {
		register_setting( 'andw_tour_price_settings', 'andw_tour_price_options', array( $this, 'sanitizeOptions' ) );
		register_setting( 'andw_tour_price_settings', 'andw_tour_price_season_colors', array( $this, 'sanitizeSeasonColors' ) );

		add_settings_section(
			'andw_tour_price_general',
			__( 'General Settings', 'andw-tour-price' ),
			array( $this, 'generalSectionCallback' ),
			'andw_tour_price_settings'
		);

		add_settings_section(
			'andw_tour_price_annual',
			__( 'Annual View Settings', 'andw-tour-price' ),
			array( $this, 'annualSectionCallback' ),
			'andw_tour_price_settings'
		);

		add_settings_field(
			'data_source',
			__( 'Data Source', 'andw-tour-price' ),
			array( $this, 'dataSourceFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'week_start',
			__( 'Week Starts On', 'andw-tour-price' ),
			array( $this, 'weekStartFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'confirmed_badge_enabled',
			__( 'Show Confirmed Badges', 'andw-tour-price' ),
			array( $this, 'confirmedBadgeFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'cache_expiry',
			__( 'Cache Expiry (seconds)', 'andw-tour-price' ),
			array( $this, 'cacheExpiryFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'heatmap_bins',
			__( 'Heatmap Bins', 'andw-tour-price' ),
			array( $this, 'heatmapBinsFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'heatmap_mode',
			__( 'Heatmap Mode', 'andw-tour-price' ),
			array( $this, 'heatmapModeFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'season_colors',
			__( 'Season Colors', 'andw-tour-price' ),
			array( $this, 'seasonColorsFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_annual'
		);

		// 非推奨設定を非表示化（データは保持）
		/*
		add_settings_field(
			'heatmap_colors',
			__( 'Heatmap Color List', 'andw-tour-price' ),
			array( $this, 'heatmapColorsFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'pricetable_color_mode',
			__( 'Price Table Color Mode', 'andw-tour-price' ),
			array( $this, 'priceTableColorModeFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);
		*/

		add_settings_field(
			'season_palette',
			__( 'Season Palette (15 colors)', 'andw-tour-price' ),
			array( $this, 'seasonPaletteFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		add_settings_field(
			'prune_mode',
			__( 'Color Pruning Mode', 'andw-tour-price' ),
			array( $this, 'pruneModeFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);

		/*
		add_settings_field(
			'pricetable_color_bins',
			__( 'Price Table Color Bins', 'andw-tour-price' ),
			array( $this, 'priceTableColorBinsFieldCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);
		*/

		// 色設定統一の説明
		add_settings_field(
			'color_migration_notice',
			'',
			array( $this, 'colorMigrationNoticeCallback' ),
			'andw_tour_price_settings',
			'andw_tour_price_general'
		);
	}

	public function adminPage() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_GET['settings-updated'] ) ) {
			add_settings_error(
				'andw_tour_price_messages',
				'andw_tour_price_message',
				__( 'Settings saved.', 'andw-tour-price' ),
				'updated'
			);
		}

		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';
		$tabs = array(
			'settings' => __( 'Settings', 'andw-tour-price' ),
			'csv' => __( 'CSV Management', 'andw-tour-price' )
		);

		$data_source_info = $this->repo->getDataSourceInfo();
		$is_data_available = $this->repo->isDataAvailable();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_key => $tab_label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $tab_key, admin_url( 'options-general.php?page=ns-tour-price' ) ) ); ?>"
					   class="nav-tab <?php echo $current_tab === $tab_key ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $tab_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<?php if ( $current_tab === 'csv' ) : ?>
				<?php
				$action = isset( $_GET['action'] ) ? sanitize_text_field( $_GET['action'] ) : '';
				if ( $action === 'view' ) {
					$this->renderCsvViewPage();
				} else {
					$this->renderCsvManagementTab();
				}
				?>
			<?php else : ?>
				<?php $this->renderSettingsTab( $data_source_info, $is_data_available ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	private function renderSettingsTab( $data_source_info, $is_data_available ) {
		?>
		<div class="tab-content">

			<?php settings_errors( 'andw_tour_price_messages' ); ?>

			<div class="notice notice-info">
				<p><strong><?php esc_html_e( 'Data Source Status:', 'andw-tour-price' ); ?></strong></p>
				<?php if ( $is_data_available ) : ?>
					<p style="color: green;">
						✅ <?php
						/* translators: %s is the active data source name */
						printf( esc_html__( 'Active: %s', 'andw-tour-price' ), esc_html( $data_source_info['active'] ) ); ?>
					</p>
				<?php else : ?>
					<p style="color: red;">
						❌ <?php esc_html_e( 'No data source available. Please check CSV files.', 'andw-tour-price' ); ?>
					</p>
				<?php endif; ?>
				
				<details>
					<summary><?php esc_html_e( 'Show all data sources', 'andw-tour-price' ); ?></summary>
					<ul>
					<?php foreach ( $data_source_info['all'] as $key => $info ) : ?>
						<li>
							<?php echo esc_html( $info['name'] ); ?>: 
							<?php if ( $info['available'] ) : ?>
								<span style="color: green;"><?php esc_html_e( 'Available', 'andw-tour-price' ); ?></span>
							<?php else : ?>
								<span style="color: red;"><?php esc_html_e( 'Not available', 'andw-tour-price' ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
					</ul>
				</details>
			</div>

			<!-- 開発用クイックキャッシュクリア -->
			<div class="card" style="background: #f0f8ff; border-left: 4px solid #0073aa; margin: 10px 0;">
				<h3 style="margin-top: 10px;"><?php esc_html_e( 'Quick Cache Clear', 'andw-tour-price' ); ?></h3>
				<p style="margin: 5px 0;"><?php esc_html_e( 'Development shortcut - Clear all cached data quickly.', 'andw-tour-price' ); ?></p>
				<button type="button" class="button button-primary" id="quick-clear-cache-btn">
					<?php esc_html_e( 'Clear Cache', 'andw-tour-price' ); ?>
				</button>
				<span id="quick-cache-status" style="margin-left: 10px;"></span>
			</div>

			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( 'CSV File Locations:', 'andw-tour-price' ); ?></strong></p>
				<ol>
					<li><code><?php echo esc_html( ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/' ); ?></code> (<?php esc_html_e( 'Priority', 'andw-tour-price' ); ?>)</li>
					<li><code><?php echo esc_html( wp_upload_dir()['basedir'] . '/ns-tour-price/' ); ?></code> (<?php esc_html_e( 'Fallback', 'andw-tour-price' ); ?>)</li>
				</ol>
			</div>

			<form action="options.php" method="post">
				<?php
				settings_fields( 'andw_tour_price_settings' );
				do_settings_sections( 'andw_tour_price_settings' );
				submit_button();
				?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Tools', 'andw-tour-price' ); ?></h2>
			
			<div class="card">
				<h3><?php esc_html_e( 'Cache Management', 'andw-tour-price' ); ?></h3>
				<p><?php esc_html_e( 'Clear all cached data to force reload from CSV files.', 'andw-tour-price' ); ?></p>
				<button type="button" class="button button-secondary" id="clear-cache-btn">
					<?php esc_html_e( 'Clear Cache', 'andw-tour-price' ); ?>
				</button>
				<span id="cache-status"></span>
			</div>

			<div class="card">
				<h3><?php esc_html_e( 'Data Test', 'andw-tour-price' ); ?></h3>
				<p><?php esc_html_e( 'Test data loading for a specific tour ID.', 'andw-tour-price' ); ?></p>
				<input type="text" id="test-tour-id" placeholder="A1" value="A1">
				<button type="button" class="button button-secondary" id="test-data-btn">
					<?php esc_html_e( 'Test Data', 'andw-tour-price' ); ?>
				</button>
				<div id="test-results" style="margin-top: 10px;"></div>
			</div>

			<hr>

			<h2><?php esc_html_e( 'Usage Examples', 'andw-tour-price' ); ?></h2>
			
			<div class="card">
				<h3><?php esc_html_e( 'Shortcode', 'andw-tour-price' ); ?></h3>
				<code>[tour_price tour="A1" month="2024-07" duration="4" heatmap="true" show_legend="true"]</code>
				
				<h3><?php esc_html_e( 'Block', 'andw-tour-price' ); ?></h3>
				<p><?php
			/* translators: %s is the Japanese name of the block */
			printf( esc_html__( 'Search for "%s" in the block editor.', 'andw-tour-price' ), 'ツアー価格カレンダー' ); ?></p>
			</div>

			<hr>

			<h2><?php esc_html_e( 'Solo Fee Settings', 'andw-tour-price' ); ?></h2>

			<div class="card">
				<h3><?php esc_html_e( 'About solo_fees.csv', 'andw-tour-price' ); ?></h3>
				<p><?php esc_html_e( 'This setting is for adding a solo fee to the base tour price. The solo fee is determined by the combination of tour ID and duration.', 'andw-tour-price' ); ?></p>
				
				<p><strong><?php esc_html_e( 'Location:', 'andw-tour-price' ); ?></strong></p>
				<ol>
					<li><code><?php echo esc_html( ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/solo_fees.csv' ); ?></code> (<?php esc_html_e( 'Priority', 'andw-tour-price' ); ?>)</li>
					<li><code><?php echo esc_html( wp_upload_dir()['basedir'] . '/ns-tour-price/solo_fees.csv' ); ?></code> (<?php esc_html_e( 'Fallback', 'andw-tour-price' ); ?>)</li>
				</ol>

				<p><strong><?php esc_html_e( 'CSV Schema:', 'andw-tour-price' ); ?></strong></p>
				<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px;">tour_id,duration_days,solo_fee
A1,4,18000
A1,5,22000
A1,6,26000
A2,5,22000
A2,6,26000</pre>

				<p><strong><?php esc_html_e( 'Features:', 'andw-tour-price' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Specify solo fee by tour_id and duration_days combination', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'The price displayed on the calendar is calculated as "Base Price + Solo Fee"', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'Solo fee is not added on dates where the base price is not available', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'The solo fee is 0 for combinations not found in solo_fees.csv', 'andw-tour-price' ); ?></li>
				</ul>
			</div>

			<hr>

			<h2><?php esc_html_e( 'CSV Data Format', 'andw-tour-price' ); ?></h2>

			<div class="card">
				<h3><?php esc_html_e( 'Date Format in seasons.csv', 'andw-tour-price' ); ?></h3>
				<p><?php esc_html_e( 'The following date formats can be used in the date_start and date_end columns.', 'andw-tour-price' ); ?></p>
				
				<p><strong><?php esc_html_e( 'Recommended Format:', 'andw-tour-price' ); ?></strong></p>
				<ul>
					<li><code>YYYY-MM-DD</code> <?php esc_html_e( '(e.g., 2025-04-15)', 'andw-tour-price' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( 'Accepted Formats:', 'andw-tour-price' ); ?></strong></p>
				<ul>
					<li><code>YYYY/M/D</code> <?php esc_html_e( '(e.g., 2025/4/15)', 'andw-tour-price' ); ?></li>
					<li><code>YYYY/MM/DD</code> <?php esc_html_e( '(e.g., 2025/04/15)', 'andw-tour-price' ); ?></li>
					<li><code>YYYY-M-D</code> <?php esc_html_e( '(e.g., 2025-4-15)', 'andw-tour-price' ); ?></li>
					<li><code>YYYY.M.D</code> <?php esc_html_e( '(e.g., 2025.4.15)', 'andw-tour-price' ); ?></li>
				</ul>

				<p><strong><?php esc_html_e( 'Auto-Normalization Feature:', 'andw-tour-price' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Full-width numbers and symbols are converted to half-width (e.g., ２０２５－０４－１５ → 2025-04-15)', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'All dates are processed internally in YYYY-MM-DD format', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'Detailed statistics are output to error_log on parsing failure', 'andw-tour-price' ); ?></li>
				</ul>
			</div>

			<div class="card">
				<h3><?php esc_html_e( 'Price Range for Heatmap and Legend', 'andw-tour-price' ); ?></h3>
				<p><?php esc_html_e( 'The calendar heatmap and legend are calculated based on the "entire period (all seasons)".', 'andw-tour-price' ); ?></p>
				<ul>
					<li><?php esc_html_e( 'The price range is determined from all base_prices for the same tour_id × duration_days.', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'Color standards and legends are displayed consistently across months.', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'Prices not appearing in the current month are also displayed in the legend.', 'andw-tour-price' ); ?></li>
				</ul>
			</div>

			<hr>

			<h2><?php esc_html_e( 'Season Code Alias', 'andw-tour-price' ); ?></h2>

			<div class="card">
				<h3><?php esc_html_e( 'About season_aliases.csv', 'andw-tour-price' ); ?></h3>
				<p><?php esc_html_e( 'If you use different season_code notations in seasons.csv and base_prices.csv (e.g., A/B/C and LOW/MID/HIGH), you can place season_aliases.csv to automatically resolve the differences.', 'andw-tour-price' ); ?></p>
				
				<p><strong><?php esc_html_e( 'Location:', 'andw-tour-price' ); ?></strong></p>
				<ol>
					<li><code><?php echo esc_html( ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/season_aliases.csv' ); ?></code> (<?php esc_html_e( 'Priority', 'andw-tour-price' ); ?>)</li>
					<li><code><?php echo esc_html( wp_upload_dir()['basedir'] . '/ns-tour-price/season_aliases.csv' ); ?></code> (<?php esc_html_e( 'Fallback', 'andw-tour-price' ); ?>)</li>
				</ol>

				<p><strong><?php esc_html_e( 'CSV Schema:', 'andw-tour-price' ); ?></strong></p>
				<pre style="background: #f1f1f1; padding: 10px; border-radius: 4px;">tour_id,alias,season_code
A1,A,LOW
A1,B,HIGH
A1,C,MID
A1,GREEN,LOW
A1,ハイ,HIGH
A1,WINTER,WINTER</pre>

				<p><strong><?php esc_html_e( 'Features:', 'andw-tour-price' ); ?></strong></p>
				<ul>
					<li><?php esc_html_e( 'Maps "A" in base_prices.csv to "LOW" in seasons.csv', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'Automatically normalizes differences in case, full/half-width characters, and leading/trailing spaces.', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'Even without an alias file, basic differences are absorbed by normalization alone.', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'If there are inconsistencies, details are logged to error_log and a warning is displayed on the frontend.', 'andw-tour-price' ); ?></li>
				</ul>
			</div>
		</div>

		<script type="text/javascript">
		document.addEventListener('DOMContentLoaded', function() {
			const clearCacheBtn = document.getElementById('clear-cache-btn');
			const cacheStatus = document.getElementById('cache-status');
			const quickClearCacheBtn = document.getElementById('quick-clear-cache-btn');
			const quickCacheStatus = document.getElementById('quick-cache-status');
			const testDataBtn = document.getElementById('test-data-btn');
			const testResults = document.getElementById('test-results');

			clearCacheBtn.addEventListener('click', function() {
				clearCacheBtn.disabled = true;
				cacheStatus.innerHTML = '⏳ Clearing...';

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=andw_tour_price_clear_cache&nonce=' + encodeURIComponent('<?php echo esc_js( wp_create_nonce( "andw_tour_price_clear_cache" ) ); ?>')
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

			// 上部のQuick Clear Cacheボタン用のイベントハンドラー（同じ機能）
			quickClearCacheBtn.addEventListener('click', function() {
				quickClearCacheBtn.disabled = true;
				quickCacheStatus.innerHTML = '⏳ Clearing...';

				fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: 'action=andw_tour_price_clear_cache&nonce=' + encodeURIComponent('<?php echo esc_js( wp_create_nonce( "andw_tour_price_clear_cache" ) ); ?>')
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						quickCacheStatus.innerHTML = '<span style="color: green;">✅ Cache cleared successfully!</span>';
					} else {
						quickCacheStatus.innerHTML = '<span style="color: red;">❌ Failed to clear cache</span>';
					}
					quickClearCacheBtn.disabled = false;
					setTimeout(() => { quickCacheStatus.innerHTML = ''; }, 3000);
				})
				.catch(error => {
					quickCacheStatus.innerHTML = '<span style="color: red;">❌ Error occurred</span>';
					quickClearCacheBtn.disabled = false;
					setTimeout(() => { quickCacheStatus.innerHTML = ''; }, 3000);
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
					body: 'action=andw_tour_price_test_data&tour_id=' + encodeURIComponent(tourId) + '&nonce=' + encodeURIComponent('<?php echo esc_js( wp_create_nonce( "andw_tour_price_test_data" ) ); ?>')
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
		</div>
		<?php
	}

	private function renderCsvManagementTab() {
		$csv_files = array(
			'base_prices.csv' => __( 'Base Prices', 'andw-tour-price' ),
			'seasons.csv' => __( 'Seasons', 'andw-tour-price' ),
			'daily_flags.csv' => __( 'Daily Flags', 'andw-tour-price' ),
			'solo_fees.csv' => __( 'Solo Fees', 'andw-tour-price' ),
			'tour_options.csv' => __( 'Tour Options', 'andw-tour-price' ),
			'tours.csv' => __( 'Tours', 'andw-tour-price' ),
		);

		if ( isset( $_POST['upload_csv'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'andw_tour_price_upload_csv' ) ) {
			$this->handleCsvUpload();
		}

		if ( isset( $_POST['delete_csv'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'andw_tour_price_delete_csv' ) ) {
			$this->handleCsvDelete();
		}
		?>
		<div class="tab-content">
			<h2><?php esc_html_e( 'CSV File Management', 'andw-tour-price' ); ?></h2>

			<div class="notice notice-info">
				<p><?php esc_html_e( 'Manage CSV data files. Upload new files or delete existing ones. Files are automatically backed up before replacement.', 'andw-tour-price' ); ?></p>
			</div>

			<!-- Current CSV Files Status -->
			<div class="card">
				<h3><?php esc_html_e( 'Current CSV Files', 'andw-tour-price' ); ?></h3>
				<table class="widefat fixed">
					<thead>
						<tr>
							<th><?php esc_html_e( 'File', 'andw-tour-price' ); ?></th>
							<th><?php esc_html_e( 'Status', 'andw-tour-price' ); ?></th>
							<th><?php esc_html_e( 'Rows', 'andw-tour-price' ); ?></th>
							<th><?php esc_html_e( 'Last Modified', 'andw-tour-price' ); ?></th>
							<th><?php esc_html_e( 'Actions', 'andw-tour-price' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $csv_files as $filename => $label ) : ?>
							<?php
							$file_path = ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/' . $filename;
							$file_exists = file_exists( $file_path );
							$row_count = 0;
							$last_modified = '';

							if ( $file_exists ) {
								$row_count = max( 0, count( file( $file_path ) ) - 1 ); // -1 for header
								$last_modified = date( 'Y-m-d H:i:s', filemtime( $file_path ) );
							}
							?>
							<tr>
								<td><strong><?php echo esc_html( $label ); ?></strong><br><code><?php echo esc_html( $filename ); ?></code></td>
								<td>
									<?php if ( $file_exists ) : ?>
										<span style="color: green;">✅ <?php esc_html_e( 'Exists', 'andw-tour-price' ); ?></span>
									<?php else : ?>
										<span style="color: red;">❌ <?php esc_html_e( 'Missing', 'andw-tour-price' ); ?></span>
									<?php endif; ?>
								</td>
								<td><?php echo $file_exists ? esc_html( number_format( $row_count ) ) : '-'; ?></td>
								<td><?php echo $file_exists ? esc_html( $last_modified ) : '-'; ?></td>
								<td>
									<?php if ( $file_exists ) : ?>
										<a href="<?php echo esc_url( add_query_arg( array( 'tab' => 'csv', 'action' => 'view', 'file' => $filename ), admin_url( 'options-general.php?page=ns-tour-price' ) ) ); ?>"
										   class="button button-secondary" style="margin-right: 5px;">
											<?php esc_html_e( 'View', 'andw-tour-price' ); ?>
										</a>
										<form method="post" style="display: inline;">
											<?php wp_nonce_field( 'andw_tour_price_delete_csv' ); ?>
											<input type="hidden" name="filename" value="<?php echo esc_attr( $filename ); ?>">
											<button type="submit" name="delete_csv" class="button button-secondary"
													onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this file? This action cannot be undone.', 'andw-tour-price' ); ?>')">
												<?php esc_html_e( 'Delete', 'andw-tour-price' ); ?>
											</button>
										</form>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<!-- Upload CSV Files -->
			<div class="card">
				<h3><?php esc_html_e( 'Upload CSV File', 'andw-tour-price' ); ?></h3>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'andw_tour_price_upload_csv' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row"><?php esc_html_e( 'Select CSV File', 'andw-tour-price' ); ?></th>
							<td>
								<input type="file" name="csv_file" accept=".csv" required>
								<p class="description"><?php esc_html_e( 'Select a CSV file to upload. The filename will determine which data it replaces.', 'andw-tour-price' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Backup Option', 'andw-tour-price' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="create_backup" value="1" checked>
									<?php esc_html_e( 'Create backup of existing file before replacement', 'andw-tour-price' ); ?>
								</label>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" name="upload_csv" class="button button-primary">
							<?php esc_html_e( 'Upload CSV', 'andw-tour-price' ); ?>
						</button>
					</p>
				</form>
			</div>

			<!-- CSV Format Information -->
			<div class="card">
				<h3><?php esc_html_e( 'CSV Format Requirements', 'andw-tour-price' ); ?></h3>
				<details>
					<summary><?php esc_html_e( 'Click to view required formats for each CSV file', 'andw-tour-price' ); ?></summary>
					<div style="margin-top: 10px;">
						<?php foreach ( $csv_files as $filename => $label ) : ?>
							<h4><?php echo esc_html( $label ); ?> (<?php echo esc_html( $filename ); ?>)</h4>
							<?php $this->renderCsvFormatInfo( $filename ); ?>
						<?php endforeach; ?>
					</div>
				</details>
			</div>
		</div>
		<?php
	}

	private function handleCsvUpload() {
		if ( ! isset( $_FILES['csv_file'] ) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK ) {
			add_settings_error( 'andw_tour_price_messages', 'upload_error', __( 'File upload failed.', 'andw-tour-price' ), 'error' );
			return;
		}

		$uploaded_file = $_FILES['csv_file'];
		$filename = sanitize_file_name( $uploaded_file['name'] );

		// Check if it's a valid CSV file
		if ( pathinfo( $filename, PATHINFO_EXTENSION ) !== 'csv' ) {
			add_settings_error( 'andw_tour_price_messages', 'invalid_file', __( 'Please upload a CSV file.', 'andw-tour-price' ), 'error' );
			return;
		}

		$target_path = ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/' . $filename;

		// Create backup if requested and file exists
		if ( ! empty( $_POST['create_backup'] ) && file_exists( $target_path ) ) {
			$backup_path = $target_path . '.backup.' . date( 'Y-m-d-H-i-s' );
			if ( ! copy( $target_path, $backup_path ) ) {
				add_settings_error( 'andw_tour_price_messages', 'backup_failed', __( 'Failed to create backup.', 'andw-tour-price' ), 'error' );
				return;
			}
		}

		// Move uploaded file
		if ( move_uploaded_file( $uploaded_file['tmp_name'], $target_path ) ) {
			// Clear cache after successful upload
			$this->repo->clearCache();
			add_settings_error( 'andw_tour_price_messages', 'upload_success',
				/* translators: %s is the filename of the uploaded CSV file */
				sprintf( __( 'CSV file %s uploaded successfully.', 'andw-tour-price' ), $filename ), 'updated' );
		} else {
			add_settings_error( 'andw_tour_price_messages', 'upload_failed', __( 'Failed to save uploaded file.', 'andw-tour-price' ), 'error' );
		}
	}

	private function handleCsvDelete() {
		$filename = sanitize_file_name( $_POST['filename'] );
		$file_path = ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/' . $filename;

		if ( ! file_exists( $file_path ) ) {
			add_settings_error( 'andw_tour_price_messages', 'file_not_found', __( 'File not found.', 'andw-tour-price' ), 'error' );
			return;
		}

		if ( unlink( $file_path ) ) {
			$this->repo->clearCache();
			add_settings_error( 'andw_tour_price_messages', 'delete_success',
				/* translators: %s is the filename of the deleted CSV file */
				sprintf( __( 'CSV file %s deleted successfully.', 'andw-tour-price' ), $filename ), 'updated' );
		} else {
			add_settings_error( 'andw_tour_price_messages', 'delete_failed', __( 'Failed to delete file.', 'andw-tour-price' ), 'error' );
		}
	}

	private function renderCsvFormatInfo( $filename ) {
		$formats = array(
			'base_prices.csv' => 'tour_id,season_code,duration_days,price',
			'seasons.csv' => 'tour_id,season_code,label,date_start,date_end',
			'daily_flags.csv' => 'tour_id,date,is_confirmed,note',
			'solo_fees.csv' => 'tour_id,duration_days,solo_fee',
			'tour_options.csv' => 'tour_id,option_id,option_label,price_min,price_max,show_price,description,image_url,affects_total',
			'tours.csv' => 'tour_id,tour_name,description,category,status'
		);

		if ( isset( $formats[ $filename ] ) ) {
			echo '<pre style="background: #f1f1f1; padding: 8px; border-radius: 3px; font-size: 12px;">' . esc_html( $formats[ $filename ] ) . '</pre>';
		}
	}

	private function renderCsvViewPage() {
		$filename = isset( $_GET['file'] ) ? sanitize_file_name( $_GET['file'] ) : '';
		$allowed_files = array(
			'base_prices.csv', 'seasons.csv', 'daily_flags.csv',
			'solo_fees.csv', 'tour_options.csv', 'tours.csv'
		);

		// セキュリティチェック
		if ( ! in_array( $filename, $allowed_files, true ) ) {
			?>
			<div class="tab-content">
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Invalid file specified.', 'andw-tour-price' ); ?></p>
				</div>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ns-tour-price&tab=csv' ) ); ?>" class="button">
					<?php esc_html_e( '← Back to CSV Management', 'andw-tour-price' ); ?>
				</a>
			</div>
			<?php
			return;
		}

		$file_path = ANDW_TOUR_PRICE_PLUGIN_DIR . 'data/' . $filename;

		if ( ! file_exists( $file_path ) ) {
			?>
			<div class="tab-content">
				<div class="notice notice-error">
					<p><?php esc_html_e( 'File not found.', 'andw-tour-price' ); ?></p>
				</div>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ns-tour-price&tab=csv' ) ); ?>" class="button">
					<?php esc_html_e( '← Back to CSV Management', 'andw-tour-price' ); ?>
				</a>
			</div>
			<?php
			return;
		}

		// CSVデータ読み込み
		$csv_data = array();
		$max_rows = 1000; // 表示制限
		$row_count = 0;

		if ( ( $handle = fopen( $file_path, 'r' ) ) !== false ) {
			while ( ( $data = fgetcsv( $handle, 1000, ',' ) ) !== false && $row_count < $max_rows ) {
				// BOM除去（最初の行のみ）
				if ( $row_count === 0 && ! empty( $data[0] ) ) {
					$data[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $data[0] );
				}
				$csv_data[] = $data;
				$row_count++;
			}
			fclose( $handle );
		}

		$file_labels = array(
			'base_prices.csv' => __( 'Base Prices', 'andw-tour-price' ),
			'seasons.csv' => __( 'Seasons', 'andw-tour-price' ),
			'daily_flags.csv' => __( 'Daily Flags', 'andw-tour-price' ),
			'solo_fees.csv' => __( 'Solo Fees', 'andw-tour-price' ),
			'tour_options.csv' => __( 'Tour Options', 'andw-tour-price' ),
			'tours.csv' => __( 'Tours', 'andw-tour-price' ),
		);

		$file_label = isset( $file_labels[ $filename ] ) ? $file_labels[ $filename ] : $filename;
		$total_file_rows = count( file( $file_path ) );
		?>
		<div class="tab-content">
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
				<h2><?php
			/* translators: %s is the name of the CSV file being viewed */
			printf( esc_html__( 'Viewing: %s', 'andw-tour-price' ), esc_html( $file_label ) ); ?></h2>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ns-tour-price&tab=csv' ) ); ?>" class="button">
					<?php esc_html_e( '← Back to CSV Management', 'andw-tour-price' ); ?>
				</a>
			</div>

			<div class="notice notice-info">
				<p>
					<strong><?php esc_html_e( 'File:', 'andw-tour-price' ); ?></strong> <?php echo esc_html( $filename ); ?><br>
					<strong><?php esc_html_e( 'Total Rows:', 'andw-tour-price' ); ?></strong> <?php echo esc_html( number_format( $total_file_rows ) ); ?>
					<?php if ( $total_file_rows > $max_rows ) : ?>
						<br><strong><?php esc_html_e( 'Note:', 'andw-tour-price' ); ?></strong>
						<?php
					/* translators: %d is the number of rows shown */
					printf( esc_html__( 'Showing first %d rows only.', 'andw-tour-price' ), esc_html( absint( $max_rows ) ) ); ?>
					<?php endif; ?>
				</p>
			</div>

			<?php if ( ! empty( $csv_data ) ) : ?>
				<div class="card">
					<table class="widefat fixed" style="table-layout: auto;">
						<thead>
							<tr>
								<th style="width: 50px;"><?php esc_html_e( 'Row', 'andw-tour-price' ); ?></th>
								<?php if ( ! empty( $csv_data[0] ) ) : ?>
									<?php foreach ( $csv_data[0] as $index => $header ) : ?>
										<th style="font-weight: bold; background-color: #f9f9f9;">
											<?php echo esc_html( $header ); ?>
										</th>
									<?php endforeach; ?>
								<?php endif; ?>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $csv_data as $row_index => $row_data ) : ?>
								<?php if ( $row_index === 0 ) continue; // ヘッダー行をスキップ ?>
								<tr>
									<td style="background-color: #f9f9f9; text-align: center; font-weight: bold;">
										<?php echo esc_html( $row_index + 1 ); ?>
									</td>
									<?php foreach ( $row_data as $cell_data ) : ?>
										<td style="border: 1px solid #ddd; padding: 8px;">
											<?php
											// 長いテキストの場合は省略表示（エスケープ前に処理）
											$cell_raw = (string) $cell_data;
											if ( strlen( $cell_raw ) > 100 ) {
												$cell_raw = substr( $cell_raw, 0, 100 ) . '...';
											}
											echo esc_html( $cell_raw );
											?>
										</td>
									<?php endforeach; ?>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php else : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No data found in this CSV file.', 'andw-tour-price' ); ?></p>
				</div>
			<?php endif; ?>

			<div style="margin-top: 20px;">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=ns-tour-price&tab=csv' ) ); ?>" class="button button-primary">
					<?php esc_html_e( '← Back to CSV Management', 'andw-tour-price' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	public function generalSectionCallback() {
		echo '<p>' . esc_html__( 'Color settings are unified in the Season Palette. Heatmap-related settings have been deprecated.', 'andw-tour-price' ) . '</p>';
	}

	public function dataSourceFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$current = $options['data_source'] ?? 'csv';
		$sources = $this->repo->getDataSourceInfo()['all'];
		?>
		<select name="andw_tour_price_options[data_source]">
			<?php foreach ( $sources as $key => $info ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>>
					<?php echo esc_html( $info['name'] ); ?>
					<?php if ( ! $info['available'] ) : ?>
						(<?php esc_html_e( 'Not Available', 'andw-tour-price' ); ?>)
					<?php endif; ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public function weekStartFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$current = $options['week_start'] ?? 'sunday';
		?>
		<select name="andw_tour_price_options[week_start]">
			<option value="sunday" <?php selected( $current, 'sunday' ); ?>><?php esc_html_e( 'Sunday', 'andw-tour-price' ); ?></option>
			<option value="monday" <?php selected( $current, 'monday' ); ?>><?php esc_html_e( 'Monday', 'andw-tour-price' ); ?></option>
		</select>
		<?php
	}

	public function confirmedBadgeFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$current = $options['confirmed_badge_enabled'] ?? false;
		?>
		<input type="checkbox" name="andw_tour_price_options[confirmed_badge_enabled]" value="1" <?php checked( $current ); ?>>
		<span><?php esc_html_e( 'Show confirmed booking badges when daily_flags.csv is available', 'andw-tour-price' ); ?></span>
		<?php
	}

	public function cacheExpiryFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$current = $options['cache_expiry'] ?? 3600;
		?>
		<input type="number" name="andw_tour_price_options[cache_expiry]" value="<?php echo esc_attr( $current ); ?>" min="300" max="86400">
		<span><?php esc_html_e( '(300-86400 seconds)', 'andw-tour-price' ); ?></span>
		<?php
	}

	public function heatmapBinsFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$current = intval( $options['heatmap_bins'] ?? 7 );
		?>
		<select name="andw_tour_price_options[heatmap_bins]">
			<option value="5" <?php selected( $current, 5 ); ?>>5 <?php esc_html_e( 'bins', 'andw-tour-price' ); ?></option>
			<option value="7" <?php selected( $current, 7 ); ?>>7 <?php esc_html_e( 'bins', 'andw-tour-price' ); ?></option>
			<option value="10" <?php selected( $current, 10 ); ?>>10 <?php esc_html_e( 'bins', 'andw-tour-price' ); ?></option>
		</select>
		<span><?php esc_html_e( 'Number of heatmap color levels', 'andw-tour-price' ); ?></span>
		<?php
	}

	public function heatmapModeFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$current = sanitize_text_field( $options['heatmap_mode'] ?? 'quantile' );
		?>
		<select name="andw_tour_price_options[heatmap_mode]">
			<option value="quantile" <?php selected( $current, 'quantile' ); ?>><?php esc_html_e( 'Quantile (Recommended)', 'andw-tour-price' ); ?></option>
			<option value="linear" <?php selected( $current, 'linear' ); ?>><?php esc_html_e( 'Linear', 'andw-tour-price' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Quantile mode distributes colors more evenly across price ranges, avoiding blue bias.', 'andw-tour-price' ); ?>
		</p>
		<?php
	}

	public function heatmapColorsFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$default_colors = array(
			'#ADCCEB', '#ADE0EB', '#ADEBE0', '#ADEBCC', '#ADEBB3', '#C7EBAD',
			'#EBEBAD', '#EBE0AD', '#EBD6AD', '#EBCCAD', '#EBBDAD', '#EBADAD', '#EAADC6'
		);
		$current_colors = isset( $options['heatmap_colors'] ) ? $options['heatmap_colors'] : $default_colors;
		$colors_text = implode( "\n", $current_colors );
		?>
		<textarea id="heatmap_colors" name="andw_tour_price_options[heatmap_colors]" rows="13" cols="50"><?php echo esc_textarea( $colors_text ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'ヒートマップの色を1行1色で指定してください（#RRGGBB形式）。色数がビン数と異なる場合は自動的に調整されます。', 'andw-tour-price' ); ?><br>
			<?php esc_html_e( '空の場合はデフォルトの13色パレット（安→高：寒色→暖色）が使用されます。', 'andw-tour-price' ); ?>
		</p>
		<div id="heatmap-color-preview" style="margin-top: 10px;"></div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var textarea = document.getElementById('heatmap_colors');
			var preview = document.getElementById('heatmap-color-preview');
			
			function updatePreview() {
				var colors = textarea.value.split('\n').filter(function(c) {
					return c.trim() && c.match(/^#[0-9A-Fa-f]{6}$/);
				});
				
				preview.innerHTML = '';
				if (colors.length > 0) {
					preview.innerHTML = '<strong>プレビュー (' + colors.length + '色):</strong><br>';
					colors.forEach(function(color) {
						var swatch = document.createElement('span');
						swatch.style.cssText = 'display:inline-block;width:20px;height:20px;background-color:' + color + ';margin:2px;border:1px solid #ddd;';
						preview.appendChild(swatch);
					});
				}
			}
			
			textarea.addEventListener('input', updatePreview);
			updatePreview();
		});
		</script>
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

		// ヒートマップ色リストの検証
		if ( isset( $input['heatmap_colors'] ) ) {
			$colors_text = sanitize_textarea_field( $input['heatmap_colors'] );
			$colors = array();
			
			if ( ! empty( $colors_text ) ) {
				$lines = explode( "\n", $colors_text );
				foreach ( $lines as $line ) {
					$color = trim( $line );
					if ( ! empty( $color ) ) {
						$validated_color = sanitize_hex_color( $color );
						if ( $validated_color ) {
							$colors[] = $validated_color;
						}
					}
				}
			}
			
			// 有効な色が1つもない場合はデフォルトを使用
			if ( empty( $colors ) ) {
				$colors = array(
					'#ADCCEB', '#ADE0EB', '#ADEBE0', '#ADEBCC', '#ADEBB3', '#C7EBAD',
					'#EBEBAD', '#EBE0AD', '#EBD6AD', '#EBCCAD', '#EBBDAD', '#EBADAD', '#EAADC6'
				);
			}
			
			$sanitized['heatmap_colors'] = $colors;
		}

		// シーズンパレットの検証
		if ( isset( $input['season_palette'] ) ) {
			$colors_text = sanitize_textarea_field( $input['season_palette'] );
			$colors = array();
			
			if ( ! empty( $colors_text ) ) {
				$lines = explode( "\n", $colors_text );
				foreach ( $lines as $line ) {
					$color = trim( $line );
					if ( ! empty( $color ) ) {
						$validated_color = sanitize_hex_color( $color );
						if ( $validated_color ) {
							$colors[] = $validated_color;
						}
					}
				}
			}
			
			// デフォルト15色パレット
			if ( empty( $colors ) ) {
				$colors = array(
					'#e3f2fd', '#bbdefb', '#90caf9', '#64b5f6', '#42a5f5',
					'#2196f3', '#1e88e5', '#1976d2', '#1565c0', '#0d47a1',
					'#ff5722', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5'
				);
			}
			
			$sanitized['season_palette'] = $colors;
		}

		// 間引きモードの検証
		if ( isset( $input['prune_mode'] ) ) {
			$mode = sanitize_text_field( $input['prune_mode'] );
			$sanitized['prune_mode'] = in_array( $mode, array( 'tail', 'balanced' ), true ) ? $mode : 'tail';
		}

		return $sanitized;
	}

	public function ajaxClearCache() {
		if ( ! wp_verify_nonce( $_POST['nonce'], 'andw_tour_price_clear_cache' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		// キャッシュクリア実行
		$this->repo->clearCache();
		
		// キャッシュクリア後の確認とログ出力のため、強制的に各CSVを再読込
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'andW Tour Price: Cache cleared, forcing CSV reload for log verification' );
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
		if ( ! wp_verify_nonce( $_POST['nonce'], 'andw_tour_price_test_data' ) ) {
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
			'tour_options' => $this->repo->getTourOptions( $tour_id ),
			'tours' => $this->repo->getTours(),
			'data_source' => $this->repo->getDataSourceInfo(),
		);

		wp_send_json_success( $test_data );
	}

	/**
	 * 年間価格概要の説明セクション
	 */
	public function renderAnnualViewHelpSection() {
		?>
		<div class="postbox">
			<h2 class="hndle"><?php esc_html_e( '年間価格概要機能', 'andw-tour-price' ); ?></h2>
			<div class="inside">
				<p><?php esc_html_e( 'メインカレンダーの下に「年間価格概要を表示」チェックボックスが表示されます。これを有効にすると：', 'andw-tour-price' ); ?></p>
				
				<h4><?php esc_html_e( '12ヶ月ミニカレンダー', 'andw-tour-price' ); ?></h4>
				<ul>
					<li><?php esc_html_e( '1年間の全日がシーズン色で表示されます', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( '価格テキストは表示せず、色のみでシーズンを識別', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( '4〜10月運用でも1〜12月すべてを表示（該当なし日はグレー）', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'ホバー時に日付とシーズンコードがツールチップで表示', 'andw-tour-price' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'シーズン料金まとめ表', 'andw-tour-price' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'シーズンコード、期間、料金を一覧表示', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( '年跨ぎ期間は当年分にトリミング', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( '複数期間がある場合はカンマ区切りで結合（例: 4/1–5/31, 6/15–6/30）', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( '料金順で自動ソート', 'andw-tour-price' ); ?></li>
				</ul>

				<h4><?php esc_html_e( '動作仕様', 'andw-tour-price' ); ?></h4>
				<ul>
					<li><?php esc_html_e( 'Ajax部分差し替えに対応（JS無効時は通常遷移）', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( '月送り・日数タブ切替で自動更新（年が変わった場合）', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( 'メモリキャッシュとTransientキャッシュで高速化', 'andw-tour-price' ); ?></li>
					<li><?php esc_html_e( '「Clear Cache」で年間ビューのキャッシュも削除', 'andw-tour-price' ); ?></li>
				</ul>

				<h4><?php esc_html_e( 'シーズン色の設定', 'andw-tour-price' ); ?></h4>
				<p><?php esc_html_e( '上記「ヒートマップ色パレット」設定が年間ビューのシーズン色にも使用されます。', 'andw-tour-price' ); ?></p>
				<p><?php esc_html_e( 'シーズンコード別の色マッピングを変更したい場合は、inc/AnnualBuilder.phpのgetSeasonColor()メソッドを修正してください。', 'andw-tour-price' ); ?></p>

				<div style="background: #f1f1f1; padding: 10px; margin: 10px 0; border-left: 4px solid #0073aa;">
					<strong><?php esc_html_e( 'レスポンシブ対応', 'andw-tour-price' ); ?>:</strong><br>
					<?php esc_html_e( 'スマホ（480px以下）では、12ヶ月カレンダーは1列表示、シーズン表はカード型表示に切り替わります。', 'andw-tour-price' ); ?>
				</div>

				<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107;">
					<strong><?php esc_html_e( '注意', 'andw-tour-price' ); ?>:</strong><br>
					<?php esc_html_e( '年間ビューはCSVデータを基準とします。seasons.csvやbase_prices.csvが正しく設定されていることを確認してください。', 'andw-tour-price' ); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * 年間ビューセクションコールバック
	 */
	public function annualSectionCallback() {
		echo '<p>' . esc_html__( 'Settings for the Annual Price Overview feature.', 'andw-tour-price' ) . '</p>';
	}

	/**
	 * シーズン色設定フィールド
	 */
	public function seasonColorsFieldCallback() {
		$season_colors = get_option( 'andw_tour_price_season_colors', array() );
		$season_codes = $this->repo->getDistinctSeasonCodes();
		
		if ( empty( $season_codes ) ) {
			echo '<p>' . esc_html__( 'There are currently no season records in seasons.csv. Please upload the CSV file.', 'andw-tour-price' ) . '</p>';
			return;
		}

		echo '<table class="widefat">';
		echo '<thead><tr><th>' . esc_html__( 'Season Code', 'andw-tour-price' ) . '</th><th>' . esc_html__( 'Color', 'andw-tour-price' ) . '</th></tr></thead>';
		echo '<tbody>';
		
		foreach ( $season_codes as $code ) {
			$current = $season_colors[ $code ] ?? '';
			$color = $current !== '' ? $current : $this->getDefaultSeasonColor( $code );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $code ) . '</strong></td>';
			echo '<td>';
			echo '<input type="color" name="andw_tour_price_season_colors[' . esc_attr( $code ) . ']" value="' . esc_attr( $color ) . '" />';
			echo ' <code>' . esc_html( $color ) . '</code>';
			echo '</td>';
			echo '</tr>';
		}
		
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Set the display colors for seasons in the annual view. If not set, default colors will be used.', 'andw-tour-price' ) . '</p>';
	}

	/**
	 * デフォルトシーズン色を取得
	 */
	private function getDefaultSeasonColor( $season_code ) {
		$default_colors = array(
			'A' => '#4CAF50', // 緑
			'B' => '#E91E63', // ピンク
			'C' => '#FF9800', // オレンジ
			'D' => '#2196F3', // 青
			'E' => '#9C27B0', // 紫
			'F' => '#795548'  // 茶
		);

		return $default_colors[ $season_code ] ?? '#9E9E9E';
	}

	/**
	 * シーズン色設定のサニタイズ
	 */
	public function sanitizeSeasonColors( $input ) {
		$sanitized = array();
		
		if ( is_array( $input ) ) {
			foreach ( $input as $code => $color ) {
				$sanitized_code = sanitize_text_field( $code );
				$sanitized_color = sanitize_hex_color( $color );
				
				if ( $sanitized_color ) {
					$sanitized[ $sanitized_code ] = $sanitized_color;
				}
			}
		}
		
		return $sanitized;
	}

	/**
	 * 価格表の色分けモード設定フィールド
	 */
	public function priceTableColorModeFieldCallback() {
		$options = get_option( 'andw_tour_price_options' );
		$value = $options['pricetable_color_mode'] ?? 'linear';
		?>
		<select name="andw_tour_price_options[pricetable_color_mode]">
			<option value="linear" <?php selected( $value, 'linear' ); ?>>Linear</option>
			<option value="quantile" <?php selected( $value, 'quantile' ); ?>>Quantile</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Color binning method for price table. Linear provides more even price distribution, useful for distinguishing close prices like I/J/K seasons.', 'andw-tour-price' ); ?>
		</p>
		<?php
	}

	/**
	 * 価格表の色ビン数設定フィールド
	 */
	public function seasonPaletteFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$default_palette = array(
			'#e3f2fd', '#bbdefb', '#90caf9', '#64b5f6', '#42a5f5',
			'#2196f3', '#1e88e5', '#1976d2', '#1565c0', '#0d47a1',
			'#ff5722', '#e91e63', '#9c27b0', '#673ab7', '#3f51b5'
		);
		$current_colors = isset( $options['season_palette'] ) ? $options['season_palette'] : $default_palette;
		$colors_text = implode( "\n", $current_colors );
		?>
		<textarea id="season_palette" name="andw_tour_price_options[season_palette]" rows="15" cols="50"><?php echo esc_textarea( $colors_text ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'A fixed palette for seasons (15 colors recommended). Used for a unified color display across legends, calendars, and season tables. Colors are assigned based on price order: the cheapest season gets the first color, and the most expensive gets the last.', 'andw-tour-price' ); ?><br>
			<?php esc_html_e( 'Specify one color per line in #RRGGBB format. If empty, the default 15-color palette is used.', 'andw-tour-price' ); ?>
		</p>
		<div id="season-palette-preview" style="margin-top: 10px;"></div>
		<script>
		document.addEventListener('DOMContentLoaded', function() {
			var textarea = document.getElementById('season_palette');
			var preview = document.getElementById('season-palette-preview');
			
			function updatePreview() {
				var colors = textarea.value.split('\n').filter(function(c) {
					return c.trim() && c.match(/^#[0-9A-Fa-f]{6}$/);
				});
				
				preview.innerHTML = '';
				if (colors.length > 0) {
					preview.innerHTML = '<strong>Season Palette Preview (' + colors.length + ' colors):</strong><br>';
					colors.forEach(function(color, index) {
						var swatch = document.createElement('span');
						swatch.style.cssText = 'display:inline-block;width:30px;height:30px;background-color:' + color + ';margin:2px;border:1px solid #ddd;border-radius:3px;position:relative;';
						swatch.title = 'Index ' + index + ': ' + color;
						preview.appendChild(swatch);
					});
				}
			}
			
			textarea.addEventListener('input', updatePreview);
			updatePreview();
		});
		</script>
		<?php
	}

	public function pruneModeFieldCallback() {
		$options = get_option( 'andw_tour_price_options', array() );
		$current = sanitize_text_field( $options['prune_mode'] ?? 'tail' );
		?>
		<select name="andw_tour_price_options[prune_mode]">
			<option value="tail" <?php selected( $current, 'tail' ); ?>><?php esc_html_e( 'Tail Pruning', 'andw-tour-price' ); ?></option>
			<option value="balanced" <?php selected( $current, 'balanced' ); ?>><?php esc_html_e( 'Balanced Pruning', 'andw-tour-price' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Method for pruning colors when the number of seasons exceeds the number of palette colors. "Tail Pruning" removes colors from the right side (higher prices) first. "Balanced Pruning" distributes removal evenly.', 'andw-tour-price' ); ?><br>
			<?php esc_html_e( 'The colors for the cheapest and most expensive seasons are always fixed at the ends of the palette.', 'andw-tour-price' ); ?>
		</p>
		<?php
	}

	/**
	 * 色設定統一の説明コールバック
	 */
	public function colorMigrationNoticeCallback() {
		// 非推奨オプションが残っているかチェック
		$options = get_option( 'andw_tour_price_options', array() );
		$deprecated_keys = array( 'heatmap_color_list', 'pricetable_color_mode', 'pricetable_color_bins', 'annual_view_season_colors' );
		$has_deprecated = false;
		
		foreach ( $deprecated_keys as $key ) {
			if ( isset( $options[ $key ] ) ) {
				$has_deprecated = true;
				break;
			}
		}
		
		// 非推奨キー記録
		if ( $has_deprecated ) {
			$deprecated_data = array();
			foreach ( $deprecated_keys as $key ) {
				if ( isset( $options[ $key ] ) ) {
					$deprecated_data[ $key ] = $options[ $key ];
				}
			}
			update_option( 'andw_tour_price_deprecated', $deprecated_data );
		}
		?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'Color settings are now unified in the Season Palette. Heatmap-related settings are deprecated.', 'andw-tour-price' ); ?></p>
			<?php if ( WP_DEBUG && $has_deprecated ) : ?>
				<p class="description" style="color: #666;">
					<?php esc_html_e( '[For Developers] Deprecated options remain (internal data is retained).', 'andw-tour-price' ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	// 非推奨コールバック（非表示だが残しておく）
	/*
	public function priceTableColorBinsFieldCallback() {
		$options = get_option( 'andw_tour_price_options' );
		$value = intval( $options['pricetable_color_bins'] ?? 10 );
		?>
		<input type="number" name="andw_tour_price_options[pricetable_color_bins]" value="<?php echo esc_attr( $value ); ?>" min="5" max="20" />
		<p class="description">
			<?php esc_html_e( 'Number of color bins for price table. Higher values show more fine price gradations. Default: 10', 'andw-tour-price' ); ?>
		</p>
		<?php
	}
	*/
}