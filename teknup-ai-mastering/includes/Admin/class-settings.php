<?php
/**
 * Settings class - Handles plugin settings
 *
 * @package Teknup\Admin
 */

namespace Teknup\Admin;

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
		add_action( 'wp_ajax_teknup_create_products', array( $this, 'create_products' ) );
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

		// Replicate.com section
		add_settings_section(
			'teknup_replicate_section',
			__( 'Replicate.com API Configuration', 'teknup-ai-mastering' ),
			array( $this, 'render_replicate_section' ),
			'teknup_settings'
		);

		add_settings_field(
			'replicate_api_token',
			__( 'API Token', 'teknup-ai-mastering' ),
			array( $this, 'render_api_token_field' ),
			'teknup_settings',
			'teknup_replicate_section'
		);

		// WooCommerce section
		add_settings_section(
			'teknup_woocommerce_section',
			__( 'WooCommerce Products', 'teknup-ai-mastering' ),
			array( $this, 'render_woocommerce_section' ),
			'teknup_settings'
		);

		add_settings_field(
			'woocommerce_products',
			__( 'Subscription Products', 'teknup-ai-mastering' ),
			array( $this, 'render_woocommerce_products_field' ),
			'teknup_settings',
			'teknup_woocommerce_section'
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

		if ( isset( $input['replicate_api_token'] ) ) {
			$sanitized['replicate_api_token'] = sanitize_text_field( $input['replicate_api_token'] );
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
	public function render_replicate_section() {
		echo '<p>' . esc_html__( 'Configure your Replicate.com API credentials. You can get your API token from your Replicate account at replicate.com/account/api-tokens.', 'teknup-ai-mastering' ) . '</p>';
	}

	/**
	 * Render WooCommerce section description
	 */
	public function render_woocommerce_section() {
		echo '<p>' . esc_html__( 'Manage WooCommerce subscription products for Teknup AI Mastering plans.', 'teknup-ai-mastering' ) . '</p>';
	}

	/**
	 * Render advanced section description
	 */
	public function render_advanced_section() {
		echo '<p>' . esc_html__( 'Advanced configuration options for the mastering service.', 'teknup-ai-mastering' ) . '</p>';
	}

	/**
	 * Render WooCommerce products field
	 */
	public function render_woocommerce_products_field() {
		$existing_products = get_option( 'teknup_subscription_products', array() );
		$products_data = array(
			'free_trial' => array( 'name' => 'Teknup Free Trial', 'price' => '0€' ),
			'starter' => array( 'name' => 'Teknup Starter', 'price' => '19$' ),
			'pro' => array( 'name' => 'Teknup Pro', 'price' => '39$' ),
			'label' => array( 'name' => 'Teknup Label', 'price' => '99$' ),
		);
		?>
		<div id="teknup-products-status">
			<?php if ( ! class_exists( 'WC_Subscriptions' ) ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong><?php esc_html_e( 'WooCommerce Subscriptions Required', 'teknup-ai-mastering' ); ?></strong><br>
						<?php esc_html_e( 'Please install and activate WooCommerce Subscriptions to create subscription products.', 'teknup-ai-mastering' ); ?>
					</p>
				</div>
			<?php else : ?>
				<?php if ( empty( $existing_products ) ) : ?>
					<div class="notice notice-info inline">
						<p>
							<strong><?php esc_html_e( 'Products Not Created Yet', 'teknup-ai-mastering' ); ?></strong><br>
							<?php esc_html_e( 'Click the button below to automatically create all subscription products.', 'teknup-ai-mastering' ); ?>
						</p>
					</div>
				<?php else : ?>
					<div class="notice notice-success inline">
						<p><strong><?php esc_html_e( 'Products Created Successfully', 'teknup-ai-mastering' ); ?></strong></p>
					</div>
					<table class="widefat" style="margin-top: 10px; max-width: 600px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Product', 'teknup-ai-mastering' ); ?></th>
								<th><?php esc_html_e( 'Price', 'teknup-ai-mastering' ); ?></th>
								<th><?php esc_html_e( 'Status', 'teknup-ai-mastering' ); ?></th>
								<th><?php esc_html_e( 'Action', 'teknup-ai-mastering' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $products_data as $slug => $data ) : ?>
								<tr>
									<td><?php echo esc_html( $data['name'] ); ?></td>
									<td><?php echo esc_html( $data['price'] ); ?></td>
									<td>
										<?php if ( isset( $existing_products[ $slug ] ) ) : ?>
											<span style="color: green;">✓ <?php esc_html_e( 'Created', 'teknup-ai-mastering' ); ?></span>
										<?php else : ?>
											<span style="color: red;">✗ <?php esc_html_e( 'Missing', 'teknup-ai-mastering' ); ?></span>
										<?php endif; ?>
									</td>
									<td>
										<?php if ( isset( $existing_products[ $slug ] ) ) : ?>
											<a href="<?php echo esc_url( admin_url( 'post.php?post=' . $existing_products[ $slug ] . '&action=edit' ) ); ?>" class="button button-small">
												<?php esc_html_e( 'Edit', 'teknup-ai-mastering' ); ?>
											</a>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>

		<p style="margin-top: 15px;">
			<button type="button" id="teknup-create-products" class="button button-primary" <?php echo ! class_exists( 'WC_Subscriptions' ) ? 'disabled' : ''; ?>>
				<?php esc_html_e( 'Create/Recreate Products', 'teknup-ai-mastering' ); ?>
			</button>
			<span id="teknup-products-status-message" style="margin-left: 10px;"></span>
		</p>

		<p class="description">
			<?php esc_html_e( 'This will create 4 subscription products: Free Trial (0€), Starter (19$), Pro (39$), and Label (99$). Existing products will not be duplicated.', 'teknup-ai-mastering' ); ?>
		</p>
		<?php
	}

	/**
	 * Render API token field
	 */
	public function render_api_token_field() {
		$value = teknup_ai_mastering()->get_setting( 'replicate_api_token', '' );
		?>
		<input
			type="password"
			name="teknup_settings[replicate_api_token]"
			id="replicate_api_token"
			value="<?php echo esc_attr( $value ); ?>"
			class="regular-text"
		/>
		<button type="button" id="test-api-connection" class="button">
			<?php esc_html_e( 'Test Connection', 'teknup-ai-mastering' ); ?>
		</button>
		<span id="api-connection-status"></span>
		<p class="description">
			<?php esc_html_e( 'Your Replicate.com API token. Get it from replicate.com/account/api-tokens. This is stored securely and never exposed to users.', 'teknup-ai-mastering' ); ?>
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

		// Temporarily set the API token for testing
		$current_token = teknup_ai_mastering()->get_setting( 'replicate_api_token' );
		teknup_ai_mastering()->update_setting( 'replicate_api_token', $api_key );

		$replicate = new \Teknup\API\Replicate();
		$result = $replicate->test_connection();

		// Restore original token
		teknup_ai_mastering()->update_setting( 'replicate_api_token', $current_token );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Connection successful!', 'teknup-ai-mastering' ) ) );
	}

	/**
	 * Create WooCommerce products via AJAX
	 */
	public function create_products() {
		check_ajax_referer( 'wp_rest', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Unauthorized', 'teknup-ai-mastering' ) ) );
		}

		// Check if WooCommerce Subscriptions is available
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce Subscriptions is not installed or activated.', 'teknup-ai-mastering' ) ) );
		}

		// Reset the products option to force recreation
		delete_option( 'teknup_subscription_products' );

		// Call the installer method to create products (with force flag)
		\Teknup\Core\Installer::create_woocommerce_products( true );

		// Get the created products
		$products = get_option( 'teknup_subscription_products', array() );

		if ( empty( $products ) ) {
			wp_send_json_error( array( 'message' => __( 'Failed to create products. Please check the error log.', 'teknup-ai-mastering' ) ) );
		}

		wp_send_json_success( array(
			'message' => sprintf(
				__( '%d products created successfully!', 'teknup-ai-mastering' ),
				count( $products )
			),
			'products' => $products,
		) );
	}

	/**
	 * Render settings page
	 */
	public function render() {
		include TEKNUP_PLUGIN_DIR . 'templates/admin/settings.php';
	}
}
