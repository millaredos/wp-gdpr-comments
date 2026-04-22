<?php
/**
 * Plugin Name: Millaredos GDPR Comments
 * Plugin URI:  https://millaredos.com/millaredos-gdpr-comments
 * Description: Adds GDPR compliance checkboxes and legal information to WordPress comments.
 * Version:     0.9.13.3
 * Author:      millaredos
 * Author URI:  https://millaredos.com
 * License:     GPL-2.0+
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Text Domain: millaredos-gdpr-comments
 * Domain Path: /languages
 * Plugin Icon: assets/logo.svg
 */

 // If this file is called directly, abort.
 if ( ! defined( 'WPINC' ) ) {
 exit;
 }

 // Define plugin constants
 define( 'MGC_VERSION', '0.9.13.3' );
 define( 'MGC_DB_VERSION', '1.1' ); // DB Version
 define( 'MGC_PATH', plugin_dir_path( __FILE__ ) );
 define( 'MGC_URL', plugin_dir_url( __FILE__ ) );


/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require_once MGC_PATH . 'includes/class-mgc-audit-log.php';
require_once MGC_PATH . 'includes/class-millaredos-gdpr-comments-admin.php';
require_once MGC_PATH . 'includes/class-millaredos-gdpr-comments-public.php';
require_once MGC_PATH . 'includes/class-mgc-arco-portal.php';
require_once MGC_PATH . 'includes/class-mgc-updater.php';

/**
 * Activation logic.
 */
function mgc_activate() {
	Millaredos_GDPR_Comments_Main::activate();
}
register_activation_hook( __FILE__, 'mgc_activate' );

/**
 * Main plugin class.
 */
class Millaredos_GDPR_Comments_Main {

	/**
	 * Activation tasks.
	 */
	public static function activate() {
		// Load textdomain manually for activation strings (WP 6.7 compliance)
		load_plugin_textdomain( 'millaredos-gdpr-comments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		self::install_db();
		self::maybe_create_arco_page();
		if ( false === get_option( 'mgc_install_date' ) ) {
			add_option( 'mgc_install_date', current_time( 'mysql' ) );
		}
		update_option( 'mgc_version', MGC_VERSION );
	}

	/**
	 * Install custom database tables.
	 */
	private static function install_db() {
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Table: Integrity Fingerprints
		$table_name = $wpdb->prefix . 'mgc_fingerprints';
		$sql        = $wpdb->prepare(
			"CREATE TABLE %i (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			comment_id bigint(20) UNSIGNED NOT NULL,
			fingerprint varchar(64) NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY comment_id (comment_id)
		) $charset_collate;",
			$table_name
		);

		dbDelta( $sql );

		// 2. Table: Audit Log (for modifications and consents)
		$table_audit = $wpdb->prefix . 'mgc_audit_log';
		$sql_audit   = $wpdb->prepare(
			"CREATE TABLE %i (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			comment_id bigint(20) UNSIGNED NULL,
			user_id bigint(20) UNSIGNED NOT NULL,
			change_type varchar(20) NOT NULL,
			old_value longtext NULL,
			new_value longtext NULL,
			integrity_hash varchar(64) NULL,
			modified_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY comment_id (comment_id),
			KEY modified_at (modified_at)
		) $charset_collate;",
			$table_audit
		);

		dbDelta( $sql_audit );

		// 3. Table: Policy Versions (Snapshots of legal texts)
		$table_versions = $wpdb->prefix . 'mgc_policy_versions';
		$sql_versions   = $wpdb->prepare(
			"CREATE TABLE %i (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			content_json longtext NOT NULL,
			version_hash varchar(64) NOT NULL,
			created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
			PRIMARY KEY  (id),
			KEY version_hash (version_hash)
		) $charset_collate;",
			$table_versions
		);

		dbDelta( $sql_versions );
	}

	/**
	 * Automatically create the GDPR Rights page.
	 */
	private static function maybe_create_arco_page() {
		$page_id = get_option( 'mgc_arco_page_id' );

		// Check if the page exists via ID first.
		if ( ( 0 !== (int) $page_id ) && ( null !== get_post( $page_id ) ) ) {
			return;
		}

		// Fallback: Check if a page with the dashboard shortcode already exists.
		global $wpdb;
		$existing_page = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM %i WHERE post_status != %s AND post_content LIKE %s LIMIT 1", $wpdb->posts, 'trash', '%[mgc_gdpr_dashboard]%' ) );

		if ( null !== $existing_page ) {
			update_option( 'mgc_arco_page_id', absint( $existing_page ) );
			return;
		}

		$page_title = __( 'GDPR Rights', 'millaredos-gdpr-comments' );
		$new_page   = array(
			'post_type'    => 'page',
			'post_title'   => $page_title,
			'post_content' => '[mgc_gdpr_dashboard]',
			'post_status'  => 'publish',
			'post_author'  => ( 0 !== get_current_user_id() ) ? get_current_user_id() : 1,
		);
		$new_id     = wp_insert_post( $new_page );

		if ( ( 0 !== $new_id ) && ( ! is_wp_error( $new_id ) ) ) {
			update_option( 'mgc_arco_page_id', absint( $new_id ) );
		}
	}
}

/**
 * Handles database upgrades and schema migrations safely.
 */
class Millaredos_GDPR_Comments_Upgrade {

	/**
	 * Run upgrade logic.
	 */
	public static function run() {
		global $wpdb;

		$installed_db_version = get_option( 'mgc_db_version', '1.0' );

		if ( true === version_compare( $installed_db_version, MGC_DB_VERSION, '<' ) ) {
			self::migrate_schema();
			self::bootstrap_policies();
			update_option( 'mgc_db_version', MGC_DB_VERSION );
		}
	}

	/**
	 * Physical schema migration.
	 */
	private static function migrate_schema() {
		global $wpdb;
		$table_audit = $wpdb->prefix . 'mgc_audit_log';

		// 1. Correct Audit Log column name if needed.
		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_audit ) );
		if ( null !== $table_exists ) {
			$column_exists = $wpdb->get_results( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $table_audit, 'old_hash' ) );
			if ( ! empty( $column_exists ) ) {
				// Rename old_hash to integrity_hash.
				$wpdb->query( $wpdb->prepare( "ALTER TABLE %i CHANGE COLUMN %i %i varchar(64) NULL", $table_audit, 'old_hash', 'integrity_hash' ) );
			}
		}

		// 2. Ensure all tables are created/updated via dbDelta.
		Millaredos_GDPR_Comments_Main::activate();
	}

	/**
	 * Create the initial policy snapshot if none exists.
	 */
	private static function bootstrap_policies() {
		global $wpdb;
		$table_versions = $wpdb->prefix . 'mgc_policy_versions';

		$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_versions ) );
		if ( null === $table_exists ) {
			return;
		}

		$count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i", $table_versions ) );

		if ( 0 === $count ) {
			$admin = new Millaredos_GDPR_Comments_Admin();
			// Manually trigger a snapshot with current options.
			$admin->maybe_snapshot_policy( 'mgc_responsible', '', get_option( 'mgc_responsible' ) );
		}
	}
}

/**
 * Starts the plugin.
 */
function mgc_run_plugin() {
	// Run potential upgrades (Admin context only).
	if ( is_admin() ) {
		Millaredos_GDPR_Comments_Upgrade::run();
	}

	// Initialize Admin
	if ( is_admin() ) {
		$plugin_admin = new Millaredos_GDPR_Comments_Admin();
		$plugin_admin->init();
	}

	// Initialize Public
	$plugin_public = new Millaredos_GDPR_Comments_Public();
	$plugin_public->init();

	// Initialize ARCO Portal
	$plugin_arco = new Millaredos_GDPR_Comments_ARCO_Portal();
	$plugin_arco->init();

	// Initialize Updater (GitHub Mirror)
	new MGC_Updater( __FILE__ );
}

// Hook to init to ensure translations and WordPress environment are fully ready.
add_action( 'init', 'mgc_run_plugin' );

/**
 * Loads the plugin text domain for translation.
 */
function mgc_load_textdomain() {
	load_plugin_textdomain( 'millaredos-gdpr-comments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'mgc_load_textdomain', 5 );
