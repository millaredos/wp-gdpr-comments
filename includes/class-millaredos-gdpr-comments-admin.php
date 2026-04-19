<?php

/**
 * Handles the admin area functionality.
 */
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Millaredos_GDPR_Comments_Admin {

	/**
	 * Initialize the class and set its properties.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
		add_action( 'admin_head', array( $this, 'add_plugin_list_icon' ) );
	}

	/**
	 * Adds the plugin icon to the WordPress plugin list.
	 */
	public function add_plugin_list_icon() {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id ) {
			return;
		}
		?>
		<style>
			/* Plugin Icon in the list of plugins */
			.plugins tr[data-slug="millaredos-gdpr-comments"] .plugin-title img,
			.plugins tr[data-slug="millaredos-gdpr-comments"] .plugin-icon {
				background-image: url('<?php echo esc_url( MGC_URL . 'assets/logo.svg' ); ?>') !important;
				background-size: cover !important;
				background-repeat: no-repeat !important;
				background-position: center !important;
				border-radius: 4px;
				background-color: transparent !important;
				box-shadow: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Enqueue admin styles.
	 */
	public function enqueue_admin_styles() {
		// Only load on our page.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( 'millaredos-gdpr-comments' === $page ) {
			wp_enqueue_style( 'mgc-admin-css', MGC_URL . 'assets/css/admin.css', array(), MGC_VERSION );

			// PayPal SDK is needed for the donation button in the shared header.
			wp_enqueue_script( 'mgc-paypal-sdk', 'https://www.paypalobjects.com/donate/sdk/donate-sdk.js', array(), null, true );
			wp_script_add_data( 'mgc-paypal-sdk', 'charset', 'UTF-8' );
		}
	}

	/**
	 * Register the administration menu for this plugin.
	 */
	public function add_plugin_admin_menu() {
		add_menu_page(
			__( 'Millaredos GDPR Comments', 'millaredos-gdpr-comments' ),
			__( 'Millaredos GDPR', 'millaredos-gdpr-comments' ),
			'manage_options',
			'millaredos-gdpr-comments',
			array( $this, 'display_plugin_setup_page' ),
			'dashicons-shield',
			25
		);
	}

	/**
	 * Register settings.
	 */
	public function register_settings() {
		register_setting( 'mgc_compliance_group', 'mgc_responsible', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mgc_compliance_group', 'mgc_purpose', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mgc_compliance_group', 'mgc_legitimation', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mgc_compliance_group', 'mgc_hosting_provider', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mgc_compliance_group', 'mgc_recipients', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mgc_compliance_group', 'mgc_rights', array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'mgc_compliance_group', 'mgc_policy_link', array( 'sanitize_callback' => 'esc_url_raw' ) );
		register_setting( 'mgc_compliance_group', 'mgc_additional_info', array( 'sanitize_callback' => 'esc_url_raw' ) );

		// Section: Legal Information
		add_settings_section(
			'mgc_compliance_main_section',
			__( 'Basic Data Protection Information', 'millaredos-gdpr-comments' ),
			array( $this, 'section_callback' ),
			'millaredos-gdpr-comments'
		);

		// Fields
		add_settings_field( 'mgc_responsible', __( 'Responsible', 'millaredos-gdpr-comments' ), array( $this, 'render_text_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_responsible' ) );
		add_settings_field( 'mgc_purpose', __( 'Purpose', 'millaredos-gdpr-comments' ), array( $this, 'render_text_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_purpose' ) );
		add_settings_field( 'mgc_legitimation', __( 'Legitimation', 'millaredos-gdpr-comments' ), array( $this, 'render_text_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_legitimation', 'default' => __( 'Consent of the interested party', 'millaredos-gdpr-comments' ) ) );
		add_settings_field( 'mgc_hosting_provider', __( 'Hosting Provider', 'millaredos-gdpr-comments' ), array( $this, 'render_text_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_hosting_provider', 'description' => __( 'Ex: SiteGround, AWS...', 'millaredos-gdpr-comments' ) ) );
		add_settings_field( 'mgc_recipients', __( 'Recipients', 'millaredos-gdpr-comments' ), array( $this, 'render_text_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_recipients', 'description' => __( 'Leave empty to use auto-generated text with Hosting Provider.', 'millaredos-gdpr-comments' ) ) );
		add_settings_field( 'mgc_rights', __( 'Rights', 'millaredos-gdpr-comments' ), array( $this, 'render_text_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_rights', 'default' => __( 'Access, rectify and delete data', 'millaredos-gdpr-comments' ) ) );
		add_settings_field( 'mgc_policy_link', __( 'Privacy Policy Link', 'millaredos-gdpr-comments' ), array( $this, 'render_url_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_policy_link' ) );
		add_settings_field( 'mgc_additional_info', __( '"More Info" Link (Optional)', 'millaredos-gdpr-comments' ), array( $this, 'render_url_field' ), 'millaredos-gdpr-comments', 'mgc_compliance_main_section', array( 'label_for' => 'mgc_additional_info' ) );


		// Section: Maintenance (Rendered Manually in Sidebar)
		register_setting( 'mgc_compliance_group', 'mgc_erase_settings', array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );
		register_setting( 'mgc_compliance_group', 'mgc_drop_table', array( 'sanitize_callback' => 'rest_sanitize_boolean' ) );

		// NOTE: We do NOT use add_settings_section/field for maintenance anymore, as we render it manually in sidebar.
	}

	public function section_callback() {
		echo '<p>' . esc_html__( 'Configure here the texts that will appear in the first layer of information in the comment form.', 'millaredos-gdpr-comments' ) . '</p>';
		echo '<div class="notice notice-warning inline"><p><strong>' . esc_html__( 'Legal Note:', 'millaredos-gdpr-comments' ) . '</strong> ' . esc_html__( 'This plugin is a technical tool developed to facilitate regulatory compliance. However, its installation does not automatically guarantee the legality of your website. It does NOT constitute legal advice. We recommend consulting with a specialist to validate your texts and procedures.', 'millaredos-gdpr-comments' ) . '</p></div>';
	}

	public function render_text_field(  $args  ) {
		$option = get_option( $args['label_for'] );
		$value  = ! empty( $option ) ? $option : ( isset( $args['default'] ) ? $args['default'] : '' );
		echo '<input type="text" id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $args['label_for'] ) . '" value="' . esc_attr( $value ) . '" class="regular-text">';
		if ( isset( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	public function render_url_field(  $args  ) {
		$option = get_option( $args['label_for'] );
		$value  = ! empty( $option ) ? $option : '';
		echo '<input type="url" id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $args['label_for'] ) . '" value="' . esc_url( $value ) . '" class="regular-text" placeholder="https://...">';
	}

	public function section_maintenance_callback() {
		echo '<div class="mgc-card maintenance-card">';
		echo '<p>' . esc_html__( 'Configure what happens when you delete the plugin.', 'millaredos-gdpr-comments' ) . '</p>';
		echo '</div>';
	}

	public function render_checkbox_field( $args ) {
		$option = get_option( $args['label_for'] );
		$checked = checked( $option, 1, false );

		echo '<label>';
		echo '<input type="checkbox" id="' . esc_attr( $args['label_for'] ) . '" name="' . esc_attr( $args['label_for'] ) . '" value="1" ' . $checked . '> ';
		echo isset( $args['description'] ) ? esc_html( $args['description'] ) : '';
		echo '</label>';
	}

	public function display_plugin_setup_page() {
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
		?>
		<div class="wrap mgc-wrap">

			<!-- Branded Header -->
			<div class="mgc-header">
				<div class="mgc-brand">
					<h2><?php esc_html_e( 'Millaredos GDPR Comments', 'millaredos-gdpr-comments' ); ?></h2>
					<p><?php esc_html_e( 'by', 'millaredos-gdpr-comments' ); ?> <a href="<?php echo esc_url( 'https://millaredos.com' ); ?>" target="_blank">millaredos.com</a></p>
				</div>
				<div class="mgc-cta">
					<p><?php esc_html_e( 'Discover full details about this plugin in our official post. Leave us a test comment!', 'millaredos-gdpr-comments' ); ?>
					</p>
					<a href="<?php echo esc_url( 'https://millaredos.com/millaredos-gdpr-comments' ); ?>" target="_blank"
						class="button button-primary"><?php esc_html_e( 'Go to Official Post', 'millaredos-gdpr-comments' ); ?></a>

					<!-- PayPal Donation Button -->
					<div id="donate-button-container" style="display:inline-block; vertical-align:middle; margin-left:10px;">
						<div id="donate-button"></div>
						<script>
							window.addEventListener('load', function() {
								if (typeof PayPal !== 'undefined') {
									PayPal.Donation.Button({
										env: 'production',
										hosted_button_id: 'YSB2YH7HHD4L2',
										image: {
											src: 'https://www.paypalobjects.com/en_US/ES/i/btn/btn_donateCC_LG.gif',
											alt: '<?php echo esc_js( __( 'Donate with PayPal button', 'millaredos-gdpr-comments' ) ); ?>',
											title: '<?php echo esc_js( __( 'PayPal - The safer, easier way to pay online!', 'millaredos-gdpr-comments' ) ); ?>',
										}
									}).render('#donate-button');
								}
							});
						</script>
					</div>
				</div>
			</div>

			<!-- Tabs -->
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=millaredos-gdpr-comments&tab=settings' ) ); ?>"
					class="nav-tab <?php echo 'settings' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Settings', 'millaredos-gdpr-comments' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=millaredos-gdpr-comments&tab=audit' ) ); ?>"
					class="nav-tab <?php echo 'audit' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Audit & Fingerprints', 'millaredos-gdpr-comments' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=millaredos-gdpr-comments&tab=stats' ) ); ?>"
					class="nav-tab <?php echo 'stats' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Stats', 'millaredos-gdpr-comments' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=millaredos-gdpr-comments&tab=logs' ) ); ?>"
					class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Logs', 'millaredos-gdpr-comments' ); ?></a>
			</nav>

			<!-- Content -->
			<div class="mgc-content">
				<?php
				switch ( $active_tab ) {
					case 'settings':
						$this->render_settings_tab();
						break;
					case 'audit':
						$this->render_audit_tab();
						break;
					case 'stats':
						$this->render_stats_tab();
						break;
					case 'logs':
						$this->render_logs_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Get data for the stats dashboard.
	 */
	private function mgc_get_stats_dashboard_data() {
		global $wpdb;

		// 1. KPIs
		$total_comments = wp_count_comments();
		$total_all = isset( $total_comments->total_comments ) ? (int) $total_comments->total_comments : 0;

		$total_gdpr = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*) FROM %i
			WHERE meta_key = %s AND meta_value = %s
		", $wpdb->commentmeta, '_mgc_privacy_accepted', 'yes' ) );

		$total_compromised = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*) FROM %i
			WHERE meta_key = %s AND meta_value = %s
		", $wpdb->commentmeta, '_mgc_integrity_compromised', 'yes' ) );

		$install_date = get_option( 'mgc_install_date', '1970-01-01 00:00:00' );
		$total_legacy = (int) $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT(*) FROM %i 
			WHERE comment_date < %s
		", $wpdb->comments, $install_date ) );

		$total_new = $total_all - $total_legacy;
		$compliance_rate = 0 < $total_new ? round( ( $total_gdpr / $total_new ) * 100, 1 ) : 0;
		if ( 100 < $compliance_rate ) {
			$compliance_rate = 100;
		}

		$integrity_health = 0 < $total_gdpr ? round( ( ( $total_gdpr - $total_compromised ) / $total_gdpr ) * 100, 1 ) : 100;

		// 2. Trend (Last 6 Months)
		$trend_results = $wpdb->get_results( $wpdb->prepare( "
			SELECT YEAR(comment_date) as year, MONTH(comment_date) as month, COUNT(*) as count
			FROM %i c
			INNER JOIN %i cm ON c.comment_ID = cm.comment_id
			WHERE cm.meta_key = %s
			AND c.comment_date > DATE_SUB(now(), INTERVAL 6 MONTH)
			GROUP BY year, month
			ORDER BY year ASC, month ASC
		", $wpdb->comments, $wpdb->commentmeta, '_mgc_privacy_accepted' ) );

		// 3. Top Posts
		$top_posts = $wpdb->get_results( $wpdb->prepare( "
			SELECT c.comment_post_ID, p.post_title, COUNT(*) as gdpr_count
			FROM %i c
			INNER JOIN %i cm ON c.comment_ID = cm.comment_id
			INNER JOIN %i p ON c.comment_post_ID = p.ID
			WHERE cm.meta_key = %s
			GROUP BY c.comment_post_ID
			ORDER BY gdpr_count DESC
			LIMIT 10
		", $wpdb->comments, $wpdb->commentmeta, $wpdb->posts, '_mgc_privacy_accepted' ) );

		return array(
			'total_all'        => $total_all,
			'total_gdpr'       => $total_gdpr,
			'total_compromised' => $total_compromised,
			'total_legacy'     => $total_legacy,
			'total_new'        => $total_new,
			'compliance_rate'  => $compliance_rate,
			'integrity_health' => $integrity_health,
			'trend_results'    => $trend_results,
			'top_posts'        => $top_posts,
		);
	}

	private function render_stats_tab() {
		$data = $this->mgc_get_stats_dashboard_data();

		$total_all        = $data['total_all'];
		$total_gdpr       = $data['total_gdpr'];
		$total_compromised = $data['total_compromised'];
		$total_legacy     = $data['total_legacy'];
		$total_new        = $data['total_new'];
		$compliance_rate  = $data['compliance_rate'];
		$integrity_health = $data['integrity_health'];
		$trend_results    = $data['trend_results'];
		$top_posts        = $data['top_posts'];

		?>
		<div class="wrap mgc-stats-dashboard">

			<!-- KPIs -->
			<div class="mgc-kpis">
				<div class="mgc-card kpi">
					<h3><?php esc_html_e( 'GDPR Health', 'millaredos-gdpr-comments' ); ?></h3>
					<div class="number"><?php echo esc_html( (int) $compliance_rate ); ?>%</div>
					<div class="sub">
						<?php printf( esc_html__( 'Of %d Modern Comments', 'millaredos-gdpr-comments' ), absint( $total_new ) ); ?>
					</div>
				</div>
				<div class="mgc-card kpi">
					<h3><?php esc_html_e( 'Integrity', 'millaredos-gdpr-comments' ); ?></h3>
					<div class="number"><?php echo esc_html( (float) $integrity_health ); ?>%</div>
					<div class="sub">
						<?php printf( esc_html__( '%d Compromised', 'millaredos-gdpr-comments' ), absint( $total_compromised ) ); ?></div>
				</div>
				<div class="mgc-card kpi">
					<h3><?php esc_html_e( 'Site Volume', 'millaredos-gdpr-comments' ); ?></h3>
					<div class="number"><?php echo absint( $total_all ); ?></div>
					<div class="sub" style="font-size:11px; line-height:1.4;">
						<span style="color:#888;">🏺
							<?php printf( esc_html__( 'Legacy: %d', 'millaredos-gdpr-comments' ), $total_legacy ); ?></span><br>
						<span style="color:#2271b1;">🛡️
							<?php printf( esc_html__( 'Modern: %d', 'millaredos-gdpr-comments' ), $total_new ); ?></span>
					</div>
				</div>
			</div>

			<!-- Visual Break -->
			<div
				style="background: #fff; padding: 15px; border-radius: 5px; margin-bottom: 20px; border-left: 4px solid #72aee6; box-shadow: 0 1px 1px rgba(0,0,0,.04);">
				<p style="margin:0; font-size:13px; color:#50575e;">
					<strong><?php esc_html_e( 'Site Coverage Analysis:', 'millaredos-gdpr-comments' ); ?></strong>
					<?php
					if ( 0 < $total_all ) {
						$legacy_share = round( ( $total_legacy / $total_all ) * 100 );
						$modern_share = 100 - $legacy_share;
						printf(
							esc_html__( '%d%% of your comments are Legacy (Pre-Plugin). We are protecting %d%% (Modern Era).', 'millaredos-gdpr-comments' ),
							$legacy_share,
							$modern_share
						);
					} else {
						esc_html_e( 'No comments yet.', 'millaredos-gdpr-comments' );
					}
					?>
				</p>
			</div>

			<div class="mgc-stats-grid">
				<!-- Chart -->
				<div class="mgc-card chart-container">
					<h3><?php esc_html_e( 'Consent Trend (Last 6 Months)', 'millaredos-gdpr-comments' ); ?></h3>
					<?php if ( $trend_results ) : ?>
						<div class="mgc-bar-chart">
							<?php
							// Find max for scaling
							$max_count = 0;
							foreach ( $trend_results as $row ) {
								$max_count = max( $max_count, $row->count );
							}

							foreach ( $trend_results as $row ) {
								$height = round( ( $row->count / $max_count ) * 100 );
								$label = substr( $GLOBALS['wp_locale']->get_month_abbrev( $GLOBALS['wp_locale']->get_month( $row->month ) ), 0, 3 );
								echo '<div class="bar-group">';
								echo '<div class="bar" style="height: ' . esc_attr( (int) $height ) . '%;" title="' . esc_attr( $row->count ) . '"></div>';
								echo '<div class="label">' . esc_html( $label ) . '</div>';
								echo '</div>';
							}
							?>
						</div>
					<?php else : ?>
						<p><?php esc_html_e( 'No data yet.', 'millaredos-gdpr-comments' ); ?></p>
					<?php endif; ?>
				</div>

				<!-- Top Posts -->
				<div class="mgc-card top-posts">
					<h3><?php esc_html_e( 'Top commented posts (GDPR)', 'millaredos-gdpr-comments' ); ?></h3>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Post', 'millaredos-gdpr-comments' ); ?></th>
								<th style="width: 80px; text-align: right;">
									<?php esc_html_e( 'Consents', 'millaredos-gdpr-comments' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php if ( $top_posts ) : ?>
								<?php foreach ( $top_posts as $post ) : ?>
									<tr>
										<td><a href="<?php echo esc_url( get_permalink( $post->comment_post_ID ) ); ?>"
												target="_blank"><?php echo esc_html( $post->post_title ); ?></a></td>
										<td style="text-align: right;"><strong><?php echo esc_html( (int) $post->gdpr_count ); ?></strong></td>
									</tr>
								<?php endforeach; ?>
							<?php else : ?>
								<tr>
									<td colspan="2"><?php esc_html_e( 'No data found.', 'millaredos-gdpr-comments' ); ?></td>
								</tr>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</div>

		</div>
		<?php
	}

	public function render_settings_tab() {
		// Get options manually for the custom sidebar
		$erase_settings = get_option( 'mgc_erase_settings' );
		$drop_table = get_option( 'mgc_drop_table' );
		?>
		<form action="options.php" method="post">
			<?php settings_fields( 'mgc_compliance_group' ); ?>

			<!-- SECTION HEADER (FULL WIDTH) -->
			<div class="mgc-settings-header" style="margin-bottom: 30px;">
				<h2><?php esc_html_e( 'Basic Data Protection Information', 'millaredos-gdpr-comments' ); ?></h2>
				<?php $this->section_callback(); // Manually call the callback to render description + legal note ?>
			</div>

			<div class="mgc-settings-container">

				<!-- LEFT COLUMN: Main Settings Form -->
				<div class="mgc-settings-main">
					<div class="mgc-card">
						<table class="form-table" role="presentation">
							<?php
							// Render ONLY the fields for the main section, bypassing the Section Title (we already rendered it)
							do_settings_fields( 'millaredos-gdpr-comments', 'mgc_compliance_main_section' );
							?>
						</table>
						<?php submit_button(); ?>
					</div>
				</div>

				<!-- RIGHT COLUMN: Danger Zone -->
				<div class="mgc-settings-sidebar">
					<div class="mgc-card maintenance-card">
						<h3><span class="dashicons dashicons-warning" style="color: #d63638; margin-right:5px;"></span>
							<?php esc_html_e( 'Plugin Maintenance', 'millaredos-gdpr-comments' ); ?></h3>
						<p class="description" style="margin-bottom: 15px;">
							<?php esc_html_e( 'Configure what happens when you delete the plugin.', 'millaredos-gdpr-comments' ); ?>
						</p>

						<div class="mgc-maintenance-option">
							<label>
								<input type="checkbox" id="mgc_erase_settings" name="mgc_erase_settings" value="1" <?php checked( $erase_settings, 1 ); ?>>
								<strong><?php esc_html_e( 'Clean up Settings on Uninstall', 'millaredos-gdpr-comments' ); ?></strong>
							</label>
							<p class="description">
								<?php esc_html_e( 'Delete all plugin configuration if uninstalled.', 'millaredos-gdpr-comments' ); ?>
							</p>
						</div>

						<div class="mgc-maintenance-option danger">
							<label>
								<input type="checkbox" id="mgc_drop_table" name="mgc_drop_table" value="1" <?php checked( $drop_table, 1 ); ?> 		<?php disabled( $erase_settings, false ); ?>>
								<strong><?php esc_html_e( 'Delete Audit Data on Uninstall', 'millaredos-gdpr-comments' ); ?></strong>
							</label>
							<p class="description warning">
								<?php esc_html_e( 'WARNING: This will delete the integrity table (legal proof).', 'millaredos-gdpr-comments' ); ?>
							</p>
						</div>
					</div>
				</div>

			</div>
		</form>

		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var eraseSettings = document.getElementById('mgc_erase_settings');
				var dropTable = document.getElementById('mgc_drop_table');

				function toggleDropTable() {
					if (!eraseSettings.checked) {
						dropTable.checked = false; // Uncheck
						dropTable.disabled = true; // Disable
						dropTable.closest('.mgc-maintenance-option').style.opacity = '0.5';
					} else {
						dropTable.disabled = false; // Enable
						dropTable.closest('.mgc-maintenance-option').style.opacity = '1';
					}
				}

				if (eraseSettings && dropTable) {
					eraseSettings.addEventListener('change', toggleDropTable);
					toggleDropTable(); // Run on init
				}
			});
		</script>
		<?php
	}

	public function render_audit_tab() {
		// 1. Handle Filters
		$filters = $this->handle_audit_filters();
		$comments = get_comments( $filters['args'] );

		echo '<div class="card" style="margin-top:20px; max-width: 100%;">';
		echo '<h3>' . esc_html__( 'Consent Audit', 'millaredos-gdpr-comments' ) . '</h3>';

		$this->render_audit_filters_bar( $filters );
		$this->render_audit_table( $comments );

		// Pagination
		echo '<div class="tablenav bottom">';
		echo '<div class="tablenav-pages">';
		$prev = 1 < $filters['page'] ? $filters['page'] - 1 : false;
		$next = $filters['limit'] <= count( $comments ) ? $filters['page'] + 1 : false;

		if ( $prev ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $prev, remove_query_arg( 'paged' ) ) ) . '">&laquo; ' . esc_html__( 'Previous', 'millaredos-gdpr-comments' ) . '</a> ';
		}
		if ( $next ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $next, remove_query_arg( 'paged' ) ) ) . '">' . esc_html__( 'Next', 'millaredos-gdpr-comments' ) . ' &raquo;</a>';
		}
		echo '</div></div>';

		echo '</div>';
	}

	private function handle_audit_filters() {
		$status    = isset( $_GET['comment_status'] ) ? sanitize_key( wp_unslash( $_GET['comment_status'] ) ) : 'all';
		$page      = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		$search    = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : ( isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '' );
		$integrity = isset( $_GET['integrity_status'] ) ? sanitize_key( wp_unslash( $_GET['integrity_status'] ) ) : '';
		$m_date    = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : '';

		if ( ! empty( $m_date ) && ( ! is_numeric( $m_date ) || 6 !== strlen( $m_date ) ) ) {
			$m_date = '';
		}

		$limit  = 20;
		$offset = ( $page - 1 ) * $limit;

		$args = array(
			'meta_key' => '_mgc_privacy_accepted',
			'number'   => $limit,
			'offset'   => $offset,
			'orderby'  => 'comment_date',
			'order'    => 'DESC',
			'status'   => 'all' === $status ? '' : $status,
			'search'   => $search,
		);

		if ( 'compromised' === $integrity ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_mgc_integrity_compromised',
					'value'   => 'yes',
					'compare' => '=',
				),
			);
		} elseif ( 'valid' === $integrity ) {
			$args['meta_query'] = array(
				array(
					'key'     => '_mgc_integrity_compromised',
					'compare' => 'NOT EXISTS',
				),
			);
		}

		if ( ! empty( $m_date ) ) {
			$args['date_query'] = array(
				array(
					'year'  => substr( $m_date, 0, 4 ),
					'month' => substr( $m_date, 4, 2 ),
				),
			);
		}

		if ( 'pending' === $status ) {
			$args['status'] = 'hold';
		}

		return array(
			'status'    => $status,
			'page'      => $page,
			'search'    => $search,
			'integrity' => $integrity,
			'm_date'    => $m_date,
			'limit'     => $limit,
			'args'      => $args,
		);
	}

	private function render_audit_filters_bar(  $filters  ) {
		$base_url = remove_query_arg( array( 'comment_status', 'paged' ) );
		$statuses = array(
			'all'     => __( 'All', 'millaredos-gdpr-comments' ),
			'pending' => __( 'Pending', 'millaredos-gdpr-comments' ),
			'approve' => __( 'Approved', 'millaredos-gdpr-comments' ),
			'spam'    => __( 'Spam', 'millaredos-gdpr-comments' ),
			'trash'   => __( 'Trash', 'millaredos-gdpr-comments' ),
		);

		echo '<ul class="subsubsub">';
		$links = array();
		foreach ( $statuses as $key => $label ) {
			$class   = ( $filters['status'] === $key ) ? 'current' : '';
			$url     = add_query_arg( array( 'comment_status' => $key, 'paged' => false ), $base_url );
			$links[] = "<li><a href='" . esc_url( $url ) . "' class='" . esc_attr( $class ) . "'>" . esc_html( $label ) . "</a></li>";
		}
		echo wp_kses( implode( ' | ', $links ), array( 'li' => array(), 'a'  => array( 'href' => array(), 'class' => array() ) ) );
		echo '</ul><br class="clear">';

		echo '<form method="get" action="">';
		echo '<input type="hidden" name="page" value="millaredos-gdpr-comments">';
		echo '<input type="hidden" name="tab" value="audit">';
		if ( 'all' !== $filters['status'] ) {
			echo '<input type="hidden" name="comment_status" value="' . esc_attr( $filters['status'] ) . '">';
		}

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';
		echo '<select name="integrity_status">';
		echo '<option value="">' . esc_html__( 'All Integrity Statuses', 'millaredos-gdpr-comments' ) . '</option>';
		echo '<option value="valid" ' . selected( $filters['integrity'], 'valid', false ) . '>' . esc_html__( 'Valid (Unaltered)', 'millaredos-gdpr-comments' ) . '</option>';
		echo '<option value="compromised" ' . selected( $filters['integrity'], 'compromised', false ) . '>' . esc_html__( 'Modified / Compromised', 'millaredos-gdpr-comments' ) . '</option>';
		echo '</select>';

		global $wpdb;
		$months = $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT YEAR(comment_date) AS year, MONTH(comment_date) AS month FROM %i ORDER BY comment_date DESC", $wpdb->comments ) );
		if ( $months ) {
			echo '<select name="m">';
			echo '<option value="">' . esc_html__( 'All Dates', 'millaredos-gdpr-comments' ) . '</option>';
			foreach ( $months as $arc_row ) {
				$month_year = sprintf( '%04d%02d', $arc_row->year, $arc_row->month );
				$label = sprintf( __( '%1$s %2$d', 'millaredos-gdpr-comments' ), $GLOBALS['wp_locale']->get_month( $arc_row->month ), $arc_row->year );
				echo '<option value="' . esc_attr( $month_year ) . '" ' . selected( $filters['m_date'], $month_year, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		submit_button( __( 'Filter', 'millaredos-gdpr-comments' ), '', 'filter_action', false, array( 'id' => 'post-query-submit' ) );
		echo '</div>';

		echo '<div class="alignleft actions">';
		echo '<input type="search" id="post-search-input" name="s" value="' . esc_attr( $filters['search'] ) . '" placeholder="' . esc_attr__( 'Search email, IP...', 'millaredos-gdpr-comments' ) . '">';
		submit_button( __( 'Search', 'millaredos-gdpr-comments' ), '', '', false, array( 'id' => 'search-submit' ) );
		echo '</div>';
		echo '</div>';
		echo '</form>';

		echo '<p class="description" style="clear:both;">' . esc_html__( 'This table shows the cryptographic evidence of policy acceptance.', 'millaredos-gdpr-comments' ) . '</p>';
		echo '<div class="notice notice-info inline"><p>' . esc_html__( 'This technical record serves as integrity evidence ("Accountability"). In case of legal dispute, provide these hashes to your IT expert or auditor.', 'millaredos-gdpr-comments' ) . '</p></div>';
	}

	private function render_audit_table(  $comments  ) {
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th>' . esc_html__( 'Author', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th>' . esc_html__( 'In Response To', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th>' . esc_html__( 'IP (Anon)', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th>' . esc_html__( 'Timestamp (Meta)', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th>' . esc_html__( 'Integrity Fingerprint (SHA-256)', 'millaredos-gdpr-comments' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( $comments ) {
			$install_date = get_option( 'mgc_install_date', '1970-01-01 00:00:00' );
			foreach ( $comments as $comment ) {
				$meta_ts        = get_comment_meta( $comment->comment_ID, '_mgc_privacy_timestamp', true );
				$meta_ip        = get_comment_meta( $comment->comment_ID, '_mgc_privacy_ip_anon', true );
				$meta_hash      = get_comment_meta( $comment->comment_ID, '_mgc_consent_proof_hash', true );
				$is_compromised = get_comment_meta( $comment->comment_ID, '_mgc_integrity_compromised', true );
				$edit_link      = get_edit_comment_link( $comment->comment_ID );

				echo '<tr>';
				echo '<td>' . ( $edit_link ? '<a href="' . esc_url( $edit_link ) . '">' : '' ) . esc_html( $comment->comment_date ) . ( $edit_link ? '</a>' : '' ) . '</td>';
				echo '<td>' . esc_html( $comment->comment_author ) . '<br><a href="mailto:' . esc_attr( $comment->comment_author_email ) . '">' . esc_html( $comment->comment_author_email ) . '</a></td>';
				echo '<td><a href="' . esc_url( get_permalink( $comment->comment_post_ID ) ) . '" target="_blank">' . esc_html( get_the_title( $comment->comment_post_ID ) ) . '</a></td>';

				if ( ( $comment->comment_date < $install_date ) && ! $meta_hash ) {
					echo '<td colspan="3" style="color: #a0a5aa; font-style: italic; text-align: center;"><span class="dashicons dashicons-clock"></span> ' . esc_html__( 'Pre-Plugin (Legacy)', 'millaredos-gdpr-comments' ) . '</td>';
				} else {
					echo '<td><span class="code">' . esc_html( $meta_ip ) . '</span></td>';
					echo '<td>' . esc_html( $meta_ts ) . '</td>';
					echo '<td style="word-break:break-all; font-family:monospace; font-size:10px;">' . esc_html( $meta_hash );
					if ( 'yes' === $is_compromised ) {
						echo '<br><span style="color:red; font-weight:bold;"><span class="dashicons dashicons-warning"></span> ' . esc_html__( 'Modified after consent', 'millaredos-gdpr-comments' ) . '</span>';
					}
					echo '</td>';
				}
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="6">' . esc_html__( 'No registered consents found.', 'millaredos-gdpr-comments' ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}

	public function render_logs_tab() {
		$m_date      = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : ''; // YYYYMM
		$change_type = isset( $_GET['change_type'] ) ? sanitize_key( wp_unslash( $_GET['change_type'] ) ) : '';

		$page  = isset( $_GET['paged'] ) ? absint( wp_unslash( $_GET['paged'] ) ) : 1;
		$limit = 20;

		$result = MGC_Audit_Log::query(
			array(
				'm'           => $m_date,
				'change_type' => $change_type,
			),
			$page,
			$limit
		);

		$logs = isset( $result['items'] ) ? $result['items'] : array();
		$total = isset( $result['total'] ) ? (int) $result['total'] : 0;
		$total_pages = $limit > 0 ? (int) ceil( $total / $limit ) : 1;

		// Render Header & Filters
		echo '<div class="card" style="margin-top:20px; max-width: 100%;">';
		echo '<h3>' . esc_html__( 'System Audit Logs', 'millaredos-gdpr-comments' ) . '</h3>';

		echo '<form method="get" action="">';
		echo '<input type="hidden" name="page" value="millaredos-gdpr-comments">';
		echo '<input type="hidden" name="tab" value="logs">';

		echo '<div class="tablenav top">';
		echo '<div class="alignleft actions">';

		$types = MGC_Audit_Log::get_distinct_change_types();
		echo '<select name="change_type">';
		echo '<option value="">' . esc_html__( 'All Event Types', 'millaredos-gdpr-comments' ) . '</option>';
		if ( $types ) {
			foreach ( $types as $type ) {
				echo '<option value="' . esc_attr( $type ) . '" ' . selected( $change_type, $type, false ) . '>' . esc_html( $this->translate_change_type( $type ) ) . '</option>';
			}
		}
		echo '</select>';

		// Date Filter
		$months = MGC_Audit_Log::get_distinct_months();
		if ( $months ) {
			echo '<select name="m">';
			echo '<option value="">' . esc_html__( 'All Dates', 'millaredos-gdpr-comments' ) . '</option>';
			foreach ( $months as $arc_row ) {
				$month_year = sprintf( '%04d%02d', $arc_row->year, $arc_row->month );
				$label = sprintf( __( '%1$s %2$d', 'millaredos-gdpr-comments' ), $GLOBALS['wp_locale']->get_month( $arc_row->month ), $arc_row->year );
				echo '<option value="' . esc_attr( $month_year ) . '" ' . selected( $m_date, $month_year, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		submit_button( __( 'Filter', 'millaredos-gdpr-comments' ), '', 'filter_action', false );
		echo '</div>'; // alignleft
		echo '</div>'; // tablenav
		echo '</form>';

		// Table
		echo '<table class="widefat fixed striped">';
		echo '<thead><tr>';
		echo '<th style="width: 160px;">' . esc_html__( 'Date', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th style="width: 150px;">' . esc_html__( 'Event Type', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th style="width: 80px;">' . esc_html__( 'Comment ID', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'millaredos-gdpr-comments' ) . '</th>';
		echo '<th>' . esc_html__( 'New Value / Details', 'millaredos-gdpr-comments' ) . '</th>';
		echo '</tr></thead>';
		echo '<tbody>';

		if ( $logs ) {
			foreach ( $logs as $log ) {
				$user = get_userdata( $log->user_id );
				$user_display = $user ? $user->display_name : ( 0 === (int) $log->user_id ? __( 'System/Guest', 'millaredos-gdpr-comments' ) : sprintf( _x( '#%s', 'User ID prefix', 'millaredos-gdpr-comments' ), $log->user_id ) );

				// Badge style for types
				$badge_class = 'mgc-badge';
				if ( 'consent_given' === $log->change_type ) {
					$badge_class .= ' mgc-badge-success';
				} elseif ( in_array( $log->change_type, array( 'content', 'author', 'email' ), true ) ) {
					$badge_class .= ' mgc-badge-warning';
				}

				echo '<tr>';
				echo '<td>' . esc_html( $log->modified_at ) . '</td>';
				echo '<td><span class="' . esc_attr( $badge_class ) . '">' . esc_html( $this->translate_change_type( $log->change_type ) ) . '</span></td>';
				echo '<td>' . ( $log->comment_id ? '<a href="' . esc_url( get_edit_comment_link( $log->comment_id ) ) . '">' . absint( $log->comment_id ) . '</a>' : '-' ) . '</td>';
				echo '<td>' . esc_html( $user_display ) . '</td>';
				echo '<td><div style="max-height: 60px; overflow-y: auto; font-size: 11px;">' . esc_html( $log->new_value ) . '</div></td>';
				echo '</tr>';
			}
		} else {
			echo '<tr><td colspan="5">' . esc_html__( 'No logs found.', 'millaredos-gdpr-comments' ) . '</td></tr>';
		}
		echo '</tbody></table>';

		// Pagination
		echo '<div class="tablenav bottom">';
		echo '<div class="tablenav-pages">';
		$prev = 1 < $page ? $page - 1 : false;
		$next = $page < $total_pages ? $page + 1 : false;
		if ( $prev ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $prev ) ) . '">&laquo; ' . esc_html__( 'Previous', 'millaredos-gdpr-comments' ) . '</a> ';
		}
		if ( $next ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( 'paged', $next ) ) . '">' . esc_html__( 'Next', 'millaredos-gdpr-comments' ) . ' &raquo;</a>';
		}
		echo '</div></div>';

		echo '</div>';
	}

	/**
	 * Translate change type values.
	 *
	 * @param string $type The change type.
	 * @return string
	 */
	private function translate_change_type( $type ) {
		$map = array(
			'consent_given' => __( 'Consent Given', 'millaredos-gdpr-comments' ),
			'content'       => __( 'Content', 'millaredos-gdpr-comments' ),
			'author'        => __( 'Author', 'millaredos-gdpr-comments' ),
			'email'         => __( 'Email', 'millaredos-gdpr-comments' ),
		);

		return isset( $map[ $type ] ) ? $map[ $type ] : ucfirst( $type );
	}
}
