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

		// Create WooCommerce products
		self::create_woocommerce_products();

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
			tonn_job_id varchar(255) DEFAULT NULL,
			original_filename varchar(255) NOT NULL,
			original_filepath varchar(500) NOT NULL,
			mastered_filepath varchar(500) DEFAULT NULL,
			file_size bigint(20) UNSIGNED NOT NULL,
			status varchar(50) NOT NULL DEFAULT 'pending',
			job_type varchar(50) DEFAULT 'mastering',
			intensity varchar(50) DEFAULT 'medium',
			genre varchar(100) DEFAULT NULL,
			target_lufs decimal(5,2) DEFAULT NULL,
			error_message text DEFAULT NULL,
			stems_data longtext DEFAULT NULL,
			settings longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			uploaded_at datetime DEFAULT NULL,
			sent_to_tonn_at datetime DEFAULT NULL,
			processing_started_at datetime DEFAULT NULL,
			completed_at datetime DEFAULT NULL,
			failed_at datetime DEFAULT NULL,
			notified_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY status (status),
			KEY created_at (created_at),
			KEY tonn_job_id (tonn_job_id)
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
			'tonn_api_token' => '',
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

	/**
	 * Create WooCommerce subscription products
	 *
	 * @param bool $force Force recreation even if products exist.
	 */
	public static function create_woocommerce_products( $force = false ) {
		// Check if WooCommerce Subscriptions is active
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			// Store flag to create products later when Subscriptions is activated
			update_option( 'teknup_needs_products_creation', true );
			return;
		}

		// Check if products already exist (unless forced)
		if ( ! $force ) {
			$existing_products = get_option( 'teknup_subscription_products', array() );
			if ( ! empty( $existing_products ) ) {
				return; // Products already created
			}
		}

		$products_created = array();

		// Define subscription products with new pricing
		$products = array(
			'starter' => array(
				'name' => 'Teknup Starter',
				'price' => 9.99,
				'monthly_limit' => 3,
				'description' => 'Perfect for hobbyists. Get 3 professional AI masters every month.',
				'billing_period' => 'month',
				'billing_interval' => 1,
				'features' => array(
					'3 masters per month',
					'Professional AI mastering',
					'All audio formats supported',
					'Advanced intensity controls',
					'Genre-specific presets',
					'Download history',
					'Email support',
				),
			),
			'pro' => array(
				'name' => 'Teknup Pro',
				'price' => 14.99,
				'monthly_limit' => 6,
				'description' => 'Best value for regular producers. Get 6 professional AI masters every month.',
				'billing_period' => 'month',
				'billing_interval' => 1,
				'features' => array(
					'6 masters per month',
					'Professional AI mastering',
					'All audio formats supported',
					'Advanced intensity controls',
					'Genre-specific presets',
					'Unlimited revisions',
					'Priority email support',
					'Download history',
				),
			),
			'premium' => array(
				'name' => 'Teknup Premium',
				'price' => 24.99,
				'monthly_limit' => 12,
				'description' => 'For serious producers. Get 12 professional AI masters every month.',
				'billing_period' => 'month',
				'billing_interval' => 1,
				'features' => array(
					'12 masters per month',
					'Professional AI mastering',
					'All audio formats supported',
					'Advanced controls',
					'Genre-specific presets',
					'Unlimited revisions',
					'Priority support',
					'Extended file storage',
					'Download history',
				),
			),
		);

		foreach ( $products as $slug => $product_data ) {
			// Check if product already exists by slug
			$existing = get_page_by_path( 'teknup-' . $slug, OBJECT, 'product' );
			if ( $existing && ! $force ) {
				$products_created[ $slug ] = $existing->ID;
				continue;
			}

			// If forcing and product exists, delete it first
			if ( $existing && $force ) {
				wp_delete_post( $existing->ID, true );
			}

			// Create product
			$product = new \WC_Product_Subscription();

			// Basic info
			$product->set_name( $product_data['name'] );
			$product->set_slug( 'teknup-' . $slug );
			$product->set_status( 'publish' );
			$product->set_catalog_visibility( 'visible' );
			$product->set_description( $product_data['description'] );

			// Short description with features
			$short_desc = '<ul>';
			foreach ( $product_data['features'] as $feature ) {
				$short_desc .= '<li>' . esc_html( $feature ) . '</li>';
			}
			$short_desc .= '</ul>';
			$product->set_short_description( $short_desc );

			// Price
			$product->set_regular_price( $product_data['price'] );

			// Subscription settings
			$product->update_meta_data( '_subscription_price', $product_data['price'] );
			$product->update_meta_data( '_subscription_period', $product_data['billing_period'] );
			$product->update_meta_data( '_subscription_period_interval', $product_data['billing_interval'] );
			$product->update_meta_data( '_subscription_length', 0 ); // Never expires

			// Sign-up fee
			$product->update_meta_data( '_subscription_sign_up_fee', 0 );

			// Limit subscriptions
			$product->update_meta_data( '_subscription_limit', 'active' ); // Only one active subscription

			// Teknup plan slug and monthly limit
			$product->update_meta_data( '_teknup_plan_slug', $slug );
			$product->update_meta_data( '_teknup_monthly_limit', $product_data['monthly_limit'] );

			// Virtual product
			$product->set_virtual( true );

			// Save product
			$product_id = $product->save();

			if ( $product_id ) {
				$products_created[ $slug ] = $product_id;

				// Add to Teknup category
				$category_id = self::get_or_create_teknup_category();
				if ( $category_id ) {
					wp_set_post_terms( $product_id, array( $category_id ), 'product_cat' );
				}
			}
		}

		// Save product IDs
		update_option( 'teknup_subscription_products', $products_created );

		// Log creation
		if ( function_exists( 'teknup_ai_mastering' ) ) {
			teknup_ai_mastering()->log( 'WooCommerce subscription products created: ' . implode( ', ', array_keys( $products_created ) ), 'info' );
		}

		return $products_created;
	}

	/**
	 * Get or create Teknup product category
	 *
	 * @return int|null Category term ID or null on failure.
	 */
	private static function get_or_create_teknup_category() {
		// Check if category exists
		$term = get_term_by( 'slug', 'teknup-mastering', 'product_cat' );

		if ( $term ) {
			return $term->term_id;
		}

		// Create category
		$result = wp_insert_term(
			'Teknup AI Mastering',
			'product_cat',
			array(
				'slug' => 'teknup-mastering',
				'description' => 'Professional AI-powered audio mastering subscription plans',
			)
		);

		if ( is_wp_error( $result ) ) {
			return null;
		}

		return $result['term_id'];
	}
}
