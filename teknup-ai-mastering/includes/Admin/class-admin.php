<?php
/**
 * Admin class - Handles admin functionality
 *
 * @package Teknup\Admin
 */

namespace Teknup\Admin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class
 */
class Admin {

	/**
	 * Settings instance
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Dashboard instance
	 *
	 * @var Dashboard
	 */
	private $dashboard;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->settings = new Settings();
		$this->dashboard = new Dashboard();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'wp_ajax_teknup_clear_logs', array( $this, 'clear_logs' ) );
	}

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		// Main menu
		add_menu_page(
			__( 'Teknup AI Mastering', 'teknup-ai-mastering' ),
			__( 'Teknup', 'teknup-ai-mastering' ),
			'manage_options',
			'teknup-ai-mastering',
			array( $this->dashboard, 'render' ),
			'dashicons-format-audio',
			30
		);

		// Dashboard submenu
		add_submenu_page(
			'teknup-ai-mastering',
			__( 'Dashboard', 'teknup-ai-mastering' ),
			__( 'Dashboard', 'teknup-ai-mastering' ),
			'manage_options',
			'teknup-ai-mastering',
			array( $this->dashboard, 'render' )
		);

		// Settings submenu
		add_submenu_page(
			'teknup-ai-mastering',
			__( 'Settings', 'teknup-ai-mastering' ),
			__( 'Settings', 'teknup-ai-mastering' ),
			'manage_options',
			'teknup-settings',
			array( $this->settings, 'render' )
		);

		// Jobs submenu
		add_submenu_page(
			'teknup-ai-mastering',
			__( 'All Jobs', 'teknup-ai-mastering' ),
			__( 'All Jobs', 'teknup-ai-mastering' ),
			'manage_options',
			'teknup-jobs',
			array( $this, 'render_jobs_page' )
		);

		// Logs submenu (only if debug mode is enabled)
		if ( teknup_ai_mastering()->is_debug_enabled() ) {
			add_submenu_page(
				'teknup-ai-mastering',
				__( 'Logs', 'teknup-ai-mastering' ),
				__( 'Logs', 'teknup-ai-mastering' ),
				'manage_options',
				'teknup-logs',
				array( $this, 'render_logs_page' )
			);
		}
	}

	/**
	 * Enqueue admin scripts and styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our admin pages
		if ( strpos( $hook, 'teknup' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'teknup-admin',
			TEKNUP_PLUGIN_URL . 'assets/dist/admin.css',
			array(),
			TEKNUP_VERSION
		);

		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
			array(),
			'4.4.0',
			true
		);

		wp_enqueue_script(
			'teknup-admin',
			TEKNUP_PLUGIN_URL . 'assets/dist/admin.js',
			array( 'jquery', 'chart-js' ),
			TEKNUP_VERSION,
			true
		);

		wp_localize_script(
			'teknup-admin',
			'teknupAdmin',
			array(
				'restUrl' => rest_url( 'teknup/v1/' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/**
	 * Show admin notices
	 */
	public function admin_notices() {
		// Show activation notice
		if ( get_transient( 'teknup_activated' ) ) {
			delete_transient( 'teknup_activated' );
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Teknup AI Mastering has been activated! Please configure your Tonn ROEX API token in the settings.', 'teknup-ai-mastering' ); ?></p>
			</div>
			<?php
		}

		// Check if API key is configured
		$api_key = teknup_ai_mastering()->get_setting( 'tonn_api_token' );
		if ( empty( $api_key ) && isset( $_GET['page'] ) && strpos( $_GET['page'], 'teknup' ) !== false ) {
			?>
			<div class="notice notice-warning">
				<p>
					<?php
					printf(
						/* translators: %s: settings page URL */
						__( 'Teknup AI Mastering: Please <a href="%s">configure your Tonn ROEX API token</a> to start mastering audio files.', 'teknup-ai-mastering' ),
						admin_url( 'admin.php?page=teknup-settings' )
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render jobs page
	 */
	public function render_jobs_page() {
		include TEKNUP_PLUGIN_DIR . 'templates/admin/jobs.php';
	}

	/**
	 * Render logs page
	 */
	public function render_logs_page() {
		include TEKNUP_PLUGIN_DIR . 'templates/admin/logs.php';
	}

	/**
	 * Clear logs via AJAX
	 */
	public function clear_logs() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'teknup_clear_logs' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ) );
		}

		// Check user capability
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Insufficient permissions' ) );
		}

		// Clear logs
		delete_option( 'teknup_logs' );

		teknup_ai_mastering()->log( 'Logs cleared by admin', 'info' );

		wp_send_json_success( array( 'message' => 'Logs cleared successfully' ) );
	}
}
