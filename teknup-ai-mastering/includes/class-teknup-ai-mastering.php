<?php
/**
 * Main plugin class
 *
 * @package Teknup
 */

namespace Teknup;

use Teknup\Core\Database;
use Teknup\Core\Storage;
use Teknup\Core\Jobs;
use Teknup\Core\Subscriptions;
use Teknup\Core\Cron;
use Teknup\API\REST;
use Teknup\Admin\Admin;
use Teknup\Public\Public_Controller;
use Teknup\Emails\Notifications;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Teknup AI Mastering class - Singleton pattern
 */
final class Teknup_AI_Mastering {

	/**
	 * Single instance of the class
	 *
	 * @var Teknup_AI_Mastering
	 */
	private static $instance = null;

	/**
	 * Database instance
	 *
	 * @var Database
	 */
	public $database;

	/**
	 * Storage instance
	 *
	 * @var Storage
	 */
	public $storage;

	/**
	 * Jobs instance
	 *
	 * @var Jobs
	 */
	public $jobs;

	/**
	 * Subscriptions instance
	 *
	 * @var Subscriptions
	 */
	public $subscriptions;

	/**
	 * Cron instance
	 *
	 * @var Cron
	 */
	public $cron;

	/**
	 * REST API instance
	 *
	 * @var REST
	 */
	public $rest;

	/**
	 * Admin instance
	 *
	 * @var Admin
	 */
	public $admin;

	/**
	 * Public controller instance
	 *
	 * @var Public_Controller
	 */
	public $public;

	/**
	 * Notifications instance
	 *
	 * @var Notifications
	 */
	public $notifications;

	/**
	 * Get single instance
	 *
	 * @return Teknup_AI_Mastering
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize the plugin
	 */
	private function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'init' ) );

		// Hook into WooCommerce order completion to add credits
		add_action( 'woocommerce_order_status_completed', array( 'Teknup\Core\Installer', 'add_credits_on_purchase' ) );
	}

	/**
	 * Initialize plugin components
	 */
	private function init_components() {
		// Core components
		$this->database = new Database();
		$this->storage = new Storage();
		$this->jobs = new Jobs();
		$this->subscriptions = new Subscriptions();
		$this->cron = new Cron();

		// API
		$this->rest = new REST();

		// Admin
		if ( is_admin() ) {
			$this->admin = new Admin();
		}

		// Public
		if ( ! is_admin() ) {
			$this->public = new Public_Controller();
		}

		// Notifications
		$this->notifications = new Notifications();
	}

	/**
	 * Initialize plugin
	 */
	public function init() {
		/**
		 * Fires after Teknup AI Mastering has been initialized
		 */
		do_action( 'teknup_ai_mastering_init' );
	}

	/**
	 * Load plugin textdomain for translations
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'teknup-ai-mastering',
			false,
			dirname( TEKNUP_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Get plugin setting
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( $key, $default = '' ) {
		$settings = get_option( 'teknup_settings', array() );
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Update plugin setting
	 *
	 * @param string $key Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool
	 */
	public function update_setting( $key, $value ) {
		$settings = get_option( 'teknup_settings', array() );
		$settings[ $key ] = $value;
		return update_option( 'teknup_settings', $settings );
	}

	/**
	 * Check if debug mode is enabled
	 *
	 * @return bool
	 */
	public function is_debug_enabled() {
		return (bool) $this->get_setting( 'debug_mode', false );
	}

	/**
	 * Log message if debug mode is enabled
	 *
	 * @param string $message Log message.
	 * @param string $level Log level (info, warning, error).
	 */
	public function log( $message, $level = 'info' ) {
		if ( ! $this->is_debug_enabled() ) {
			return;
		}

		$log_entry = array(
			'timestamp' => current_time( 'mysql' ),
			'level' => $level,
			'message' => $message,
		);

		$logs = get_option( 'teknup_logs', array() );
		$logs[] = $log_entry;

		// Keep only last 1000 entries
		if ( count( $logs ) > 1000 ) {
			$logs = array_slice( $logs, -1000 );
		}

		update_option( 'teknup_logs', $logs );

		// Also log to error_log in production
		error_log( sprintf( '[Teknup %s] %s', strtoupper( $level ), $message ) );
	}

	/**
	 * Prevent cloning
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
