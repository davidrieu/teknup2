<?php
/**
 * Admin Logs Template
 *
 * @package Teknup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$logs = get_option( 'teknup_logs', array() );
$logs = array_reverse( $logs ); // Newest first
?>

<div class="wrap teknup-logs">
	<h1><?php esc_html_e( 'Debug Logs', 'teknup-ai-mastering' ); ?></h1>

	<div class="teknup-logs-filters">
		<select id="teknup-log-level-filter">
			<option value=""><?php esc_html_e( 'All Levels', 'teknup-ai-mastering' ); ?></option>
			<option value="info"><?php esc_html_e( 'Info', 'teknup-ai-mastering' ); ?></option>
			<option value="warning"><?php esc_html_e( 'Warning', 'teknup-ai-mastering' ); ?></option>
			<option value="error"><?php esc_html_e( 'Error', 'teknup-ai-mastering' ); ?></option>
		</select>
		<button type="button" class="button" onclick="location.reload();"><?php esc_html_e( 'Refresh', 'teknup-ai-mastering' ); ?></button>
		<button type="button" class="button button-secondary" id="teknup-clear-logs"><?php esc_html_e( 'Clear Logs', 'teknup-ai-mastering' ); ?></button>
	</div>

	<div class="teknup-logs-container">
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width: 150px;"><?php esc_html_e( 'Timestamp', 'teknup-ai-mastering' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Level', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Message', 'teknup-ai-mastering' ); ?></th>
				</tr>
			</thead>
			<tbody id="teknup-logs-tbody">
				<?php if ( empty( $logs ) ) : ?>
					<tr>
						<td colspan="3"><?php esc_html_e( 'No logs yet.', 'teknup-ai-mastering' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( array_slice( $logs, 0, 500 ) as $log ) : ?>
						<tr class="teknup-log-entry" data-level="<?php echo esc_attr( $log['level'] ); ?>">
							<td><?php echo esc_html( $log['timestamp'] ); ?></td>
							<td>
								<span class="teknup-log-level teknup-log-<?php echo esc_attr( $log['level'] ); ?>">
									<?php echo esc_html( strtoupper( $log['level'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $log['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	$('#teknup-log-level-filter').on('change', function() {
		var level = $(this).val();

		if (level === '') {
			$('.teknup-log-entry').show();
		} else {
			$('.teknup-log-entry').hide();
			$('.teknup-log-entry[data-level="' + level + '"]').show();
		}
	});

	$('#teknup-clear-logs').on('click', function() {
		if (!confirm('<?php esc_html_e( 'Are you sure you want to clear all logs? This cannot be undone.', 'teknup-ai-mastering' ); ?>')) {
			return;
		}

		var button = $(this);
		button.prop('disabled', true).text('<?php esc_html_e( 'Clearing...', 'teknup-ai-mastering' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'teknup_clear_logs',
				nonce: '<?php echo esc_js( wp_create_nonce( 'teknup_clear_logs' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					location.reload();
				} else {
					alert('<?php esc_html_e( 'Failed to clear logs.', 'teknup-ai-mastering' ); ?>');
					button.prop('disabled', false).text('<?php esc_html_e( 'Clear Logs', 'teknup-ai-mastering' ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'Failed to clear logs.', 'teknup-ai-mastering' ); ?>');
				button.prop('disabled', false).text('<?php esc_html_e( 'Clear Logs', 'teknup-ai-mastering' ); ?>');
			}
		});
	});
});
</script>

<style>
.teknup-logs-filters {
	margin-bottom: 20px;
}
.teknup-logs-filters select,
.teknup-logs-filters button {
	margin-right: 10px;
}
.teknup-log-level {
	padding: 2px 8px;
	border-radius: 3px;
	font-weight: bold;
	font-size: 11px;
}
.teknup-log-info {
	background: #d1ecf1;
	color: #0c5460;
}
.teknup-log-warning {
	background: #fff3cd;
	color: #856404;
}
.teknup-log-error {
	background: #f8d7da;
	color: #721c24;
}
</style>
