<?php
/**
 * Uninstall script - Clean up plugin data
 *
 * @package Teknup
 */

// Exit if not called from WordPress
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Delete database table
$table_name = $wpdb->prefix . 'teknup_jobs';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// Delete plugin options
delete_option( 'teknup_settings' );
delete_option( 'teknup_db_version' );
delete_option( 'teknup_logs' );

// Delete all user meta related to Teknup
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'teknup_%'" );

// Delete all transients
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_teknup_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_teknup_%'" );

// Delete upload directories and files
$upload_dir = WP_CONTENT_DIR . '/teknup-uploads';

if ( file_exists( $upload_dir ) ) {
	// Delete all files in directories
	$directories = array(
		$upload_dir . '/original',
		$upload_dir . '/mastered',
	);

	foreach ( $directories as $dir ) {
		if ( file_exists( $dir ) ) {
			$files = glob( $dir . '/*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
			rmdir( $dir );
		}
	}

	// Delete main directory
	rmdir( $upload_dir );
}

// Clear scheduled cron events
wp_clear_scheduled_hook( 'teknup_check_pending_jobs' );
wp_clear_scheduled_hook( 'teknup_cleanup_old_files' );
wp_clear_scheduled_hook( 'teknup_cleanup_old_jobs' );
wp_clear_scheduled_hook( 'teknup_cleanup_transients' );
