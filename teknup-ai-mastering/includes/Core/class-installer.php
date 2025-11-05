<?php
/**
 * Installer class - Handles plugin activation and deactivation
 *
 * @package Teknup\Core
 */

namespace Teknup\Core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installer class
 */
class Installer {

	/**
	 * Plugin activation
	 */
	public static function activate() {
		// Check WordPress version
		if ( version_compare( get_bloginfo( 'version' ), '5.8', '<' ) ) {
			deactivate_plugins( TEKNUP_PLUGIN_BASENAME );
			wp_die( __( 'Teknup AI Mastering requires WordPress 5.8 or higher.', 'teknup-ai-mastering' ) );
		}

		// Check PHP version
		if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
			deactivate_plugins( TEKNUP_PLUGIN_BASENAME );
			wp_die( __( 'Teknup AI Mastering requires PHP 8.0 or higher.', 'teknup-ai-mastering' ) );
		}

		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			deactivate_plugins( TEKNUP_PLUGIN_BASENAME );
			wp_die( __( 'Teknup AI Mastering requires WooCommerce to be installed and activated.', 'teknup-ai-mastering' ) );
		}

		// Create database tables
		self::create_tables();

		// Create upload directories
		self::create_directories();

		// Set default options
		self::set_default_options();

		// Schedule cron events
		self::schedule_cron_events();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Set activation flag
		set_transient( 'teknup_activated', true, 30 );
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		// Clear scheduled cron events
		self::clear_cron_events();

		// Flush rewrite rules
		flush_rewrite_rules();
	}

	/**
	 * Create database tables
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name = $wpdb->prefix . TEKNUP_TABLE_JOBS;

		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id bigint(20) UNSIGNED NOT NULL,
			dolby_job_id varchar(255) DEFAULT NULL,
			original_filename varchar(255) NOT NULL,
			original_filepath varchar(500) NOT NULL,
			mastered_filepath varchar(500) DEFAULT NULL,
			file_size bigint(20) UNSIGNED NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			intensity varchar(50) DEFAULT 'medium',
			genre varchar(100) DEFAULT NULL,
			target_lufs decimal(5,2) DEFAULT NULL,
			error_message text DEFAULT NULL,
			dolby_response longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			uploaded_at datetime DEFAULT NULL,
			sent_to_dolby_at datetime DEFAULT NULL,
			processing_started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			failed_at datetime DEFAULT NULL,
			notified_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY dolby_job_id (dolby_job_id)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Update database version
		update_option( 'teknup_db_version', TEKNUP_VERSION );
	}

	/**
	 * Create upload directories
	 */
	private static function create_directories() {
		$directories = array(
			TEKNUP_UPLOADS_DIR,
			TEKNUP_ORIGINAL_DIR,
			TEKNUP_MASTERED_DIR,
		);

		foreach ( $directories as $dir ) {
			if ( ! file_exists( $dir ) ) {
				wp_mkdir_p( $dir );
			}

			// Create .htaccess to protect direct access
			$htaccess_file = $dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				$htaccess_content = "# Teknup AI Mastering - Deny direct access\n";
				$htaccess_content .= "Order deny,allow\n";
				$htaccess_content .= "Deny from all\n";
				file_put_contents( $htaccess_file, $htaccess_content );
			}

			// Create index.php to prevent directory listing
			$index_file = $dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, '<?php // Silence is golden' );
			}
		}
	}

	/**
	 * Set default options
	 */
	private static function set_default_options() {
		$default_settings = array(
			'dolby_api_key' => '',
			'max_file_size' => 500, // MB
			'default_intensity' => 'medium',
			'default_lufs' => -14.0,
			'file_retention_days' => 30,
			'debug_mode' => false,
			'enable_notifications' => true,
		);

		add_option( 'teknup_settings', $default_settings );
	}

	/**
	 * Schedule cron events
	 */
	private static function schedule_cron_events() {
		// Check pending jobs every 5 minutes
		if ( ! wp_next_scheduled( 'teknup_check_pending_jobs' ) ) {
			wp_schedule_event( time(), 'teknup_five_minutes', 'teknup_check_pending_jobs' );
		}

		// Clean old files daily
		if ( ! wp_next_scheduled( 'teknup_cleanup_old_files' ) ) {
			wp_schedule_event( time(), 'daily', 'teknup_cleanup_old_files' );
		}

		// Clean old jobs daily
		if ( ! wp_next_scheduled( 'teknup_cleanup_old_jobs' ) ) {
			wp_schedule_event( time(), 'daily', 'teknup_cleanup_old_jobs' );
		}

		// Clean expired transients daily
		if ( ! wp_next_scheduled( 'teknup_cleanup_transients' ) ) {
			wp_schedule_event( time(), 'daily', 'teknup_cleanup_transients' );
		}
	}

	/**
	 * Clear cron events
	 */
	private static function clear_cron_events() {
		wp_clear_scheduled_hook( 'teknup_check_pending_jobs' );
		wp_clear_scheduled_hook( 'teknup_cleanup_old_files' );
		wp_clear_scheduled_hook( 'teknup_cleanup_old_jobs' );
		wp_clear_scheduled_hook( 'teknup_cleanup_transients' );
	}
}
