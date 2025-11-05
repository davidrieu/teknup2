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
	$('#test-api-connection').on('click', function(e) {
		e.preventDefault();

		var button = $(this);
		var status = $('#api-connection-status');
		var apiKey = $('#dolby_api_key').val();

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
});
</script>
