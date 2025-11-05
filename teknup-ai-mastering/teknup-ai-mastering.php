<?php
/**
 * Plugin Name: Teknup AI Mastering
 * Plugin URI: https://teknup.com
 * Description: Plugin WordPress complet pour un service SaaS de mastering audio professionnel et séparation des stems utilisant l'API Tonn ROEX
 * Version: 2.0.0
 * Author: Teknup
 * Author URI: https://teknup.com
 * Text Domain: teknup-ai-mastering
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'TEKNUP_VERSION', '2.0.0' );
define( 'TEKNUP_PLUGIN_FILE', __FILE__ );
define( 'TEKNUP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TEKNUP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TEKNUP_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'TEKNUP_UPLOADS_DIR', WP_CONTENT_DIR . '/teknup-uploads' );
define( 'TEKNUP_ORIGINAL_DIR', TEKNUP_UPLOADS_DIR . '/original' );
define( 'TEKNUP_MASTERED_DIR', TEKNUP_UPLOADS_DIR . '/mastered' );
define( 'TEKNUP_MAX_FILE_SIZE', 500 * 1024 * 1024 ); // 500 MB
define( 'TEKNUP_FILE_RETENTION_DAYS', 30 );
define( 'TEKNUP_TABLE_JOBS', 'teknup_jobs' );

/**
 * Autoloader for plugin classes
 */
require_once TEKNUP_PLUGIN_DIR . 'includes/class-autoloader.php';

/**
 * Register autoloader
 */
spl_autoload_register( array( 'Teknup\Autoloader', 'autoload' ) );

/**
 * Main plugin class instance
 */
function teknup_ai_mastering() {
	return \Teknup\Teknup_AI_Mastering::instance();
}

// Initialize the plugin
teknup_ai_mastering();

/**
 * Activation hook
 */
register_activation_hook( __FILE__, array( 'Teknup\Core\Installer', 'activate' ) );

/**
 * Deactivation hook
 */
register_deactivation_hook( __FILE__, array( 'Teknup\Core\Installer', 'deactivate' ) );
