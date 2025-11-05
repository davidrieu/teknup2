<?php
/**
 * Settings class - Handles plugin settings
 *
 * @package Teknup\Admin
 */

namespace Teknup\Admin;

use Teknup\API\Dolby;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings class
 */
class Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_teknup_test_api_connection', array( $this, 'test_api_connection' ) );
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'teknup_settings',
			'teknup_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		// Dolby.io section
		add_settings_section(
			'teknup_dolby_section',
			__( 'Dolby.io API Configuration', 'teknup-ai-mastering' ),
			array( $this, 'render_dolby_section' ),
			'teknup_settings'
		);

		add_settings_field(
			'dolby_api_key',
			__( 'API Key', 'teknup-ai-mastering' ),
			array( $this, 'render_api_key_field' ),
			'teknup_settings',
			'teknup_dolby_section'
		);

		// Advanced settings section
		add_settings_section(
			'teknup_advanced_section',
			__( 'Advanced Settings', 'teknup-ai-mastering' ),
			array( $this, 'render_advanced_section' ),
			'teknup_settings'
		);

		add_settings_field(
			'max_file_size',
			__( 'Maximum File Size (MB)', 'teknup-ai-mastering' ),
			array( $this, 'render_max_file_size_field' ),
			'teknup_settings',
			'teknup_advanced_section'
		);

		add_settings_field(
			'default_intensity',
			__( 'Default Intensity', 'teknup-ai-mastering' ),
			array( $this, 'render_default_intensity_field' ),
			'teknup_settings',
			'teknup_advanced_section'
		);

		add_settings_field(
			'default_lufs',
			__( 'Default Target LUFS', 'teknup-ai-mastering' ),
			array( $this, 'render_default_lufs_field' ),
			'teknup_settings',
			'teknup_advanced_section'
		);

		add_settings_field(
			'file_retention_days',
			__( 'File Retention Days', 'teknup-ai-mastering' ),
			array( $this, 'render_file_retention_field' ),
			'teknup_settings',
			'teknup_advanced_section'
		);

		add_settings_field(
			'debug_mode',
			__( 'Debug Mode', 'teknup-ai-mastering' ),
			array( $this, 'render_debug_mode_field' ),
			'teknup_settings',
			'teknup_advanced_section'
		);

		add_settings_field(
			'enable_notifications',
			__( 'Email Notifications', 'teknup-ai-mastering' ),
			array( $this, 'render_notifications_field' ),
			'teknup_settings',
			'teknup_advanced_section'
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input values.
	 * @return array Sanitized values.
	 */
	public function sanitize_settings( $input ) {
		$sanitized = array();

		if ( isset( $input['dolby_api_key'] ) ) {
			$sanitized['dolby_api_key'] = sanitize_text_field( $input['dolby_api_key'] );
		}

		if ( isset( $input['max_file_size'] ) ) {
			$sanitized['max_file_size'] = absint( $input['max_file_size'] );
		}

		if ( isset( $input['default_intensity'] ) ) {
			$sanitized['default_intensity'] = sanitize_text_field( $input['default_intensity'] );
		}

		if ( isset( $input['default_lufs'] ) ) {
			$sanitized['default_lufs'] = (float) $input['default_lufs'];
		}

		if ( isset( $input['file_retention_days'] ) ) {
			$sanitized['file_retention_days'] = absint( $input['file_retention_days'] );
		}

		$sanitized['debug_mode'] = isset( $input['debug_mode'] ) ? (bool) $input['debug_mode'] : false;
		$sanitized['enable_notifications'] = isset( $input['enable_notifications'] ) ? (bool) $input['enable_notifications'] : false;

		return $sanitized;
	}

	/**
	 * Render Dolby section description
	 */
	public function render_dolby_section() {
		echo '<p>' . esc_html__( 'Configure your Dolby.io Media Enhancement API credentials. You can get your API key from the Dolby.io dashboard.', 'teknup-ai-mastering' ) . '</p>';
	}

	/**
	 * Render advanced section description
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced configuration options for the mastering service.', 'teknup-ai-mastering' ) . '</p>';
	}

	/**
	 * Render API key field
	 */
	public function render_api_key_field() {
		$value = teknup_ai_mastering()->get_setting( 'dolby_api_key', '' );
		?>
		<input
			type="password"
			name="teknup_settings[dolby_api_key]"
			id="dolby_api_key"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<button type="button" id="test-api-connection" class="button">
			<?php esc_html_e( 'Test Connection', 'teknup-ai-mastering' ); ?>
		</button>
		<span id="api-connection-status"></span>
		<p class="description">
			<?php esc_html_e( 'Your Dolby.io API key. This is stored securely and never exposed to users.', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Render max file size field
	 */
	public function render_max_file_size_field() {
		$value = teknup_ai_mastering()->get_setting( 'max_file_size', 500 );
		?>
		<input
			type="number"
			name="teknup_settings[max_file_size]"
			id="max_file_size"
			value="<?php echo esc_attr( $value ); ?>"
			min="1"
			max="2048"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Maximum file size in megabytes. Default: 500 MB', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Render default intensity field
	 */
	public function render_default_intensity_field() {
		$value = teknup_ai_mastering()->get_setting( 'default_intensity', 'medium' );
		?>
		<select name="teknup_settings[default_intensity]" id="default_intensity">
			<option value="light" <?php selected( $value, 'light' ); ?>><?php esc_html_e( 'Light', 'teknup-ai-mastering' ); ?></option>
			<option value="medium" <?php selected( $value, 'medium' ); ?>><?php esc_html_e( 'Medium', 'teknup-ai-mastering' ); ?></option>
			<option value="heavy" <?php selected( $value, 'heavy' ); ?>><?php esc_html_e( 'Heavy', 'teknup-ai-mastering' ); ?></option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Default mastering intensity. Default: Medium', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Render default LUFS field
	 */
	public function render_default_lufs_field() {
		$value = teknup_ai_mastering()->get_setting( 'default_lufs', -14.0 );
		?>
		<input
			type="number"
			name="teknup_settings[default_lufs]"
			id="default_lufs"
			value="<?php echo esc_attr( $value ); ?>"
			step="0.1"
			min="-30"
			max="0"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Default target LUFS level. Typical values: -14 for streaming, -9 for club playback. Default: -14', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Render file retention field
	 */
	public function render_file_retention_field() {
		$value = teknup_ai_mastering()->get_setting( 'file_retention_days', 30 );
		?>
		<input
			type="number"
			name="teknup_settings[file_retention_days]"
			id="file_retention_days"
			value="<?php echo esc_attr( $value ); ?>"
			min="1"
			max="365"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Number of days to keep files before automatic deletion. Default: 30', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Render debug mode field
	 */
	public function render_debug_mode_field() {
		$value = teknup_ai_mastering()->is_debug_enabled();
		?>
		<label>
			<input
				type="checkbox"
				name="teknup_settings[debug_mode]"
				id="debug_mode"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Enable debug logging', 'teknup-ai-mastering' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, detailed logs will be recorded for troubleshooting. This may impact performance.', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Render notifications field
	 */
	public function render_notifications_field() {
		$value = teknup_ai_mastering()->get_setting( 'enable_notifications', true );
		?>
		<label>
			<input
				type="checkbox"
				name="teknup_settings[enable_notifications]"
				id="enable_notifications"
				value="1"
				<?php checked( $value, true ); ?>
			/>
			<?php esc_html_e( 'Send email notifications to users', 'teknup-ai-mastering' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, users will receive emails when their mastering jobs are completed.', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Test API connection via AJAX
	 */
	public function test_api_connection() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'teknup-ai-mastering' ) ) );
		}

		$api_key = isset( $_POST['api_key'] ) ? sanitize_text_field( $_POST['api_key'] ) : '';

		if ( empty( $api_key ) ) {
			wp_send_json_error( array( 'message' => __( 'API key is required.', 'teknup-ai-mastering' ) ) );
		}

		// Temporarily set the API key for testing
		$current_key = teknup_ai_mastering()->get_setting( 'dolby_api_key' );
		teknup_ai_mastering()->update_setting( 'dolby_api_key', $api_key );

		$dolby = new Dolby();
		$result = $dolby->test_connection();

		// Restore original key
		teknup_ai_mastering()->update_setting( 'dolby_api_key', $current_key );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'teknup-ai-mastering' ) ) );
	}

	/**
	 * Render settings page
	 */
	public function render() {
		include TEKNUP_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
