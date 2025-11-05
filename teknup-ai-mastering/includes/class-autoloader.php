<?php
/**
 * Autoloader class
 *
 * @package Teknup
 */

namespace Teknup;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Autoloader class for PSR-4 compatible autoloading
 */
class Autoloader {

	/**
	 * Autoload classes
	 *
	 * @param string $class The class name.
	 */
	public static function autoload( $class ) {
		// Project-specific namespace prefix
		$prefix = 'Teknup\\';

		// Base directory for the namespace prefix
		$base_dir = TEKNUP_PLUGIN_DIR . 'includes/';

		// Does the class use the namespace prefix?
		$len = strlen( $prefix );
		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		// Get the relative class name
		$relative_class = substr( $class, $len );

		// Replace namespace separators with directory separators
		// and append with .php
		$file = $base_dir . str_replace( '\\', '/', $relative_class );

		// Convert class name to filename format (class-name.php)
		$file_parts = explode( '/', $file );
		$class_name = array_pop( $file_parts );
		$class_name = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
		$file = implode( '/', $file_parts ) . '/' . $class_name;

		// If the file exists, require it
		if ( file_exists( $file ) ) {
			require $file;
		}
	}
}
