<?php
/**
 * Plugin Name: Millaredos GDPR Comments
 * Plugin URI:  https://millaredos.com/millaredos-gdpr-comments
 * Description: Adds GDPR compliance checkboxes and legal information to WordPress comments.
 * Version:     0.9.9
 * Author:      millaredos
 * Author URI:  https://millaredos.com
 * License:     GPL-2.0+
 * Requires at least: 6.5
 * Requires PHP: 8.2
 * Text Domain: millaredos-gdpr-comments
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	exit;
}

// Define plugin constants
define( 'MGC_VERSION', '0.9.9' );
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
	}

	/**
	 * Automatically create the GDPR Rights page.
	 */
	private static function maybe_create_arco_page() {
		$page_title = __( 'GDPR Rights', 'millaredos-gdpr-comments' );

		// Use get_posts instead of deprecated get_page_by_title
		$pages = get_posts(
			array(
				'post_type'              => 'page',
				'title'                  => $page_title,
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		if ( empty( $pages ) ) {
			$new_page = array(
				'post_type'    => 'page',
				'post_title'   => $page_title,
				'post_content' => '[mgc_gdpr_dashboard]',
				'post_status'  => 'publish',
				'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
			);
			wp_insert_post( $new_page );
		}
	}
}

/**
 * Starts the plugin.
 */
function mgc_run_plugin() {
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

// Hook to plugins_loaded to ensure WordPress environment is fully ready.
add_action( 'plugins_loaded', 'mgc_run_plugin' );
