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
	 * Create WooCommerce credit pack products
	 *
	 * @param bool $force Force recreation even if products exist.
	 */
	public static function create_woocommerce_products( $force = false ) {
		// Check if products already exist (unless forced)
		if ( ! $force ) {
			$existing_products = get_option( 'teknup_credit_products', array() );
			if ( ! empty( $existing_products ) ) {
				return; // Products already created
			}
		}

		$products_created = array();

		// Define credit pack products
		$products = array(
			'pack_3' => array(
				'name' => 'Teknup 3 Masters Pack',
				'price' => 9.99,
				'credits' => 3,
				'description' => 'Get 3 professional AI masters for your tracks. Perfect for trying our service.',
				'features' => array(
					'3 master credits',
					'Professional AI mastering',
					'All audio formats supported',
					'High-quality export',
					'Email support',
				),
			),
			'pack_6' => array(
				'name' => 'Teknup 6 Masters Pack',
				'price' => 14.99,
				'credits' => 6,
				'description' => 'Get 6 professional AI masters. Best value for regular producers.',
				'features' => array(
					'6 master credits',
					'Professional AI mastering',
					'All audio formats supported',
					'High-quality export',
					'Priority email support',
					'Save 17% vs 3-pack',
				),
			),
			'pack_12' => array(
				'name' => 'Teknup 12 Masters Pack',
				'price' => 24.99,
				'credits' => 12,
				'description' => 'Get 12 professional AI masters. Maximum value for serious producers.',
				'features' => array(
					'12 master credits',
					'Professional AI mastering',
					'All audio formats supported',
					'High-quality export',
					'Priority support',
					'Save 38% vs 3-pack',
					'Best value!',
				),
			),
		);

		foreach ( $products as $slug => $product_data ) {
			// Check if product already exists by slug
			$existing = get_page_by_path( 'teknup-' . $slug, OBJECT, 'product' );
			if ( $existing ) {
				$products_created[ $slug ] = $existing->ID;
				continue;
			}

			// Create simple product (not subscription)
			$product = new \WC_Product_Simple();

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

			// Store credits in product meta
			$product->update_meta_data( '_teknup_credits', $product_data['credits'] );
			$product->update_meta_data( '_teknup_pack_slug', $slug );

			// Virtual product (no shipping)
			$product->set_virtual( true );
			$product->set_downloadable( false );

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
		update_option( 'teknup_credit_products', $products_created );

		// Log creation
		if ( function_exists( 'teknup_ai_mastering' ) ) {
			teknup_ai_mastering()->log( 'WooCommerce credit pack products created: ' . implode( ', ', array_keys( $products_created ) ), 'info' );
		}

		// Hook into WooCommerce order completion to add credits
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'add_credits_on_purchase' ) );
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
				'description' => 'Professional AI-powered audio mastering credit packs',
			)
		);

		if ( is_wp_error( $result ) ) {
			return null;
		}

		return $result['term_id'];
	}

	/**
	 * Add credits to user when they purchase a pack
	 *
	 * @param int $order_id Order ID.
	 */
	public static function add_credits_on_purchase( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			return;
		}

		$user_id = $order->get_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Check if credits already added
		$credits_added = $order->get_meta( '_teknup_credits_added', true );
		if ( $credits_added ) {
			return; // Already processed
		}

		$total_credits = 0;

		// Loop through order items
		foreach ( $order->get_items() as $item ) {
			$product_id = $item->get_product_id();
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			// Check if this is a Teknup credit pack
			$credits = $product->get_meta( '_teknup_credits', true );

			if ( $credits ) {
				$quantity = $item->get_quantity();
				$credits_to_add = (int) $credits * $quantity;
				$total_credits += $credits_to_add;
			}
		}

		if ( $total_credits > 0 ) {
			// Get current credits
			$current_credits = (int) get_user_meta( $user_id, 'teknup_credits', true );

			// Add new credits
			$new_credits = $current_credits + $total_credits;
			update_user_meta( $user_id, 'teknup_credits', $new_credits );

			// Mark as processed
			$order->update_meta_data( '_teknup_credits_added', true );
			$order->save();

			// Log
			if ( function_exists( 'teknup_ai_mastering' ) ) {
				teknup_ai_mastering()->log(
					sprintf( 'Added %d credits to user %d (order %d). New total: %d', $total_credits, $user_id, $order_id, $new_credits ),
					'info'
				);
			}
		}
	}
}
