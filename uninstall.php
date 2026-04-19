<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://millaredos.com
 * @since      0.1.0
 * @package    Millaredos_GDPR_Comments
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Settings Deletion
if ( get_option( 'mgc_erase_settings' ) ) {
    delete_option( 'mgc_responsible' );
    delete_option( 'mgc_purpose' );
    delete_option( 'mgc_legitimation' );
    delete_option( 'mgc_hosting_provider' );
    delete_option( 'mgc_recipients' );
    delete_option( 'mgc_rights' );
    delete_option( 'mgc_policy_link' );
    delete_option( 'mgc_additional_info' );
    
    // Also delete maintenance flags themselves
    delete_option( 'mgc_erase_settings' );
    // Wait to delete mgc_drop_table until after using it
}

// 2. Drop Audit Table (Optional)
// Only if user explicitly requested AND (implicit logic: commonly if settings are erased, but user asked for sub-option)
// The user requirement: "sub opción... borrar también la tabla".
if ( get_option( 'mgc_drop_table' ) ) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'mgc_audit_log';
    $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
    delete_option( 'mgc_db_version' ); // Reset db version
    delete_option( 'mgc_drop_table' );
}

// NOTE: We do NOT delete comment metadata generally, as it's attached to comments. 
// If specific request to purge all gdpr meta comes, we would need another option.
?>
