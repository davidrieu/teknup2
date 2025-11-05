<?php
/**
 * Account class - Handles WooCommerce My Account integration
 *
 * @package Teknup\Public
 */

namespace Teknup\Public;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Account class
 */
class Account {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Add endpoints to WooCommerce My Account
		add_action( 'init', array( $this, 'add_endpoints' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_items' ) );
		add_action( 'woocommerce_account_teknup-dashboard_endpoint', array( $this, 'render_dashboard' ) );
		add_action( 'woocommerce_account_teknup-upload_endpoint', array( $this, 'render_upload' ) );
		add_action( 'woocommerce_account_teknup-history_endpoint', array( $this, 'render_history' ) );

		// Shortcodes
		add_shortcode( 'teknup_dashboard', array( $this, 'render_dashboard_shortcode' ) );
		add_shortcode( 'teknup_history', array( $this, 'render_history_shortcode' ) );
	}

	/**
	 * Add endpoints to WooCommerce My Account
	 */
	public function add_endpoints() {
		add_rewrite_endpoint( 'teknup-dashboard', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'teknup-upload', EP_ROOT | EP_PAGES );
		add_rewrite_endpoint( 'teknup-history', EP_ROOT | EP_PAGES );
	}

	/**
	 * Add menu items to WooCommerce My Account
	 *
	 * @param array $items Menu items.
	 * @return array Modified menu items.
	 */
	public function add_menu_items( $items ) {
		// Insert Teknup items after dashboard
		$new_items = array();

		foreach ( $items as $key => $label ) {
			$new_items[ $key ] = $label;

			if ( $key === 'dashboard' ) {
				$new_items['teknup-dashboard'] = __( 'Teknup Dashboard', 'teknup-ai-mastering' );
				$new_items['teknup-upload'] = __( 'Upload Track', 'teknup-ai-mastering' );
				$new_items['teknup-history'] = __( 'Mastering History', 'teknup-ai-mastering' );
			}
		}

		return $new_items;
	}

	/**
	 * Render dashboard endpoint
	 */
	public function render_dashboard() {
		include TEKNUP_PLUGIN_DIR . 'templates/public/dashboard.php';
	}

	/**
	 * Render upload endpoint
	 */
	public function render_upload() {
		include TEKNUP_PLUGIN_DIR . 'templates/public/upload.php';
	}

	/**
	 * Render history endpoint
	 */
	public function render_history() {
		include TEKNUP_PLUGIN_DIR . 'templates/public/history.php';
	}

	/**
	 * Render dashboard shortcode
	 *
	 * @return string Dashboard HTML.
	 */
	public function render_dashboard_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to view your dashboard.', 'teknup-ai-mastering' ) . '</p>';
		}

		ob_start();
		include TEKNUP_PLUGIN_DIR . 'templates/public/dashboard.php';
		return ob_get_clean();
	}

	/**
	 * Render history shortcode
	 *
	 * @return string History HTML.
	 */
	public function render_history_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to view your history.', 'teknup-ai-mastering' ) . '</p>';
		}

		ob_start();
		include TEKNUP_PLUGIN_DIR . 'templates/public/history.php';
		return ob_get_clean();
	}
}
