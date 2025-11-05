<?php
/**
 * Public Controller class - Handles frontend functionality
 *
 * @package Teknup\Public
 */

namespace Teknup\Public;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Controller class
 */
class Public_Controller {

	/**
	 * Upload instance
	 *
	 * @var Upload
	 */
	private $upload;

	/**
	 * Account instance
	 *
	 * @var Account
	 */
	private $account;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->upload = new Upload();
		$this->account = new Account();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_shortcode( 'teknup_mastering', array( $this, 'render_mastering_shortcode' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_scripts() {
		// Only load on specific pages
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			'teknup-public',
			TEKNUP_PLUGIN_URL . 'assets/dist/teknup-styles.css',
			array(),
			TEKNUP_VERSION
		);

		wp_enqueue_script(
			'teknup-upload',
			TEKNUP_PLUGIN_URL . 'assets/dist/upload.js',
			array( 'wp-element', 'wp-i18n' ),
			TEKNUP_VERSION,
			true
		);

		wp_enqueue_script(
			'teknup-dashboard',
			TEKNUP_PLUGIN_URL . 'assets/dist/dashboard.js',
			array( 'wp-element', 'wp-i18n' ),
			TEKNUP_VERSION,
			true
		);

		wp_enqueue_script(
			'teknup-main',
			TEKNUP_PLUGIN_URL . 'assets/dist/main.js',
			array( 'wp-element', 'wp-i18n' ),
			TEKNUP_VERSION,
			true
		);

		wp_localize_script(
			'teknup-main',
			'teknupData',
			array(
				'restUrl' => rest_url( 'teknup/v1/' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'maxFileSize' => teknup_ai_mastering()->get_setting( 'max_file_size', 500 ) * 1024 * 1024,
				'allowedTypes' => array( 'audio/wav', 'audio/mpeg', 'audio/mp3', 'audio/flac', 'audio/aiff' ),
				'allowedExtensions' => array( 'wav', 'mp3', 'flac', 'aiff', 'aif' ),
			)
		);
	}

	/**
	 * Check if assets should be loaded
	 *
	 * @return bool True if assets should be loaded.
	 */
	private function should_load_assets() {
		// Load on WooCommerce My Account pages
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		// Load on pages with shortcodes
		global $post;
		if ( $post && ( has_shortcode( $post->post_content, 'teknup_upload' ) || has_shortcode( $post->post_content, 'teknup_dashboard' ) || has_shortcode( $post->post_content, 'teknup_mastering' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Render main mastering shortcode
	 *
	 * @return string Mastering app HTML.
	 */
	public function render_mastering_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<div class="teknup-login-message teknup-card">
				<h3>' . __( 'Please Log In', 'teknup-ai-mastering' ) . '</h3>
				<p>' . __( 'You need to be logged in to access the Teknup AI Mastering service.', 'teknup-ai-mastering' ) . '</p>
				<p><a href="' . wp_login_url( get_permalink() ) . '" class="teknup-button">' . __( 'Log In', 'teknup-ai-mastering' ) . '</a></p>
			</div>';
		}

		return '<div id="teknup-mastering-app"></div>';
	}
}
