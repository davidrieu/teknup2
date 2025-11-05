<?php
/**
 * Admin Settings Template
 *
 * @package Teknup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap teknup-settings">
	<h1><?php esc_html_e( 'Teknup AI Mastering Settings', 'teknup-ai-mastering' ); ?></h1>

	<?php settings_errors(); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'teknup_settings' );
		do_settings_sections( 'teknup_settings' );
		submit_button();
		?>
	</form>
</div>

<script>
jQuery(document).ready(function($) {
	// Test API connection
	$('#test-api-connection').on('click', function(e) {
		e.preventDefault();

		var button = $(this);
		var status = $('#api-connection-status');
		var apiKey = $('#tonn_api_token').val();

		if (!apiKey) {
			status.html('<span style="color: red;">Please enter an API key first.</span>');
			return;
		}

		button.prop('disabled', true).text('Testing...');
		status.html('<span style="color: #999;">Testing connection...</span>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'teknup_test_api_connection',
				api_key: apiKey,
				nonce: teknupAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					status.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
				} else {
					status.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
				}
			},
			error: function() {
				status.html('<span style="color: red;">✗ Connection test failed.</span>');
			},
			complete: function() {
				button.prop('disabled', false).text('Test Connection');
			}
		});
	});

	// Create WooCommerce products
	$('#teknup-create-products').on('click', function(e) {
		e.preventDefault();

		if (!confirm('<?php esc_html_e( 'This will create/recreate all subscription products. Continue?', 'teknup-ai-mastering' ); ?>')) {
			return;
		}

		var button = $(this);
		var statusMessage = $('#teknup-products-status-message');

		button.prop('disabled', true).text('<?php esc_html_e( 'Creating...', 'teknup-ai-mastering' ); ?>');
		statusMessage.html('<span style="color: #999;"><?php esc_html_e( 'Creating products...', 'teknup-ai-mastering' ); ?></span>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'teknup_create_products',
				nonce: teknupAdmin.nonce
			},
			success: function(response) {
				if (response.success) {
					statusMessage.html('<span style="color: green;">✓ ' + response.data.message + '</span>');
					// Reload page after 2 seconds to show the updated products table
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					statusMessage.html('<span style="color: red;">✗ ' + response.data.message + '</span>');
					button.prop('disabled', false).text('<?php esc_html_e( 'Create/Recreate Products', 'teknup-ai-mastering' ); ?>');
				}
			},
			error: function() {
				statusMessage.html('<span style="color: red;">✗ <?php esc_html_e( 'Failed to create products.', 'teknup-ai-mastering' ); ?></span>');
				button.prop('disabled', false).text('<?php esc_html_e( 'Create/Recreate Products', 'teknup-ai-mastering' ); ?>');
			}
		});
	});
});
</script>
