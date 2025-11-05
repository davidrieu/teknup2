<?php
/**
 * Upload class - Handles upload page
 *
 * @package Teknup\Public
 */

namespace Teknup\Public;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Upload class
 */
class Upload {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_shortcode( 'teknup_upload', array( $this, 'render_upload_shortcode' ) );
	}

	/**
	 * Render upload shortcode
	 *
	 * @return string Upload form HTML.
	 */
	public function render_upload_shortcode() {
		if ( ! is_user_logged_in() ) {
			return '<p>' . __( 'Please log in to upload files.', 'teknup-ai-mastering' ) . '</p>';
		}

		ob_start();
		include TEKNUP_PLUGIN_DIR . 'templates/public/upload.php';
		return ob_get_clean();
	}
}
