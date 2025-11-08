<?php
/**
 * Admin Dashboard Template
 *
 * @package Teknup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$dashboard = new \Teknup\Admin\Dashboard();
$data = $dashboard->get_dashboard_data();
$stats = $data['stats'];
$recent_jobs = $data['recent_jobs'];
$subscription_stats = $data['subscription_stats'];
$daily_usage = $data['daily_usage'];
?>

<div class="wrap teknup-dashboard">
	<h1><?php esc_html_e( 'Dashboard', 'teknup-ai-mastering' ); ?></h1>

	<!-- Statistics Cards -->
	<div class="teknup-stats-cards">
		<div class="teknup-stat-card">
			<div class="teknup-stat-icon">📊</div>
			<div class="teknup-stat-value"><?php echo esc_html( number_format_i18n( $stats['total_jobs'] ) ); ?></div>
			<div class="teknup-stat-label"><?php esc_html_e( 'Total Jobs', 'teknup-ai-mastering' ); ?></div>
		</div>

		<div class="teknup-stat-card">
			<div class="teknup-stat-icon">✓</div>
			<div class="teknup-stat-value"><?php echo esc_html( number_format_i18n( $stats['completed_jobs'] ) ); ?></div>
			<div class="teknup-stat-label"><?php esc_html_e( 'Completed', 'teknup-ai-mastering' ); ?></div>
		</div>

		<div class="teknup-stat-card">
			<div class="teknup-stat-icon">✗</div>
			<div class="teknup-stat-value"><?php echo esc_html( number_format_i18n( $stats['failed_jobs'] ) ); ?></div>
			<div class="teknup-stat-label"><?php esc_html_e( 'Failed', 'teknup-ai-mastering' ); ?></div>
		</div>

		<div class="teknup-stat-card">
			<div class="teknup-stat-icon">⏱</div>
			<div class="teknup-stat-value"><?php echo esc_html( number_format_i18n( $stats['avg_processing_time'] ) ); ?>s</div>
			<div class="teknup-stat-label"><?php esc_html_e( 'Avg. Processing Time', 'teknup-ai-mastering' ); ?></div>
		</div>

		<div class="teknup-stat-card">
			<div class="teknup-stat-icon">👥</div>
			<div class="teknup-stat-value"><?php echo esc_html( number_format_i18n( $subscription_stats['total'] ) ); ?></div>
			<div class="teknup-stat-label"><?php esc_html_e( 'Active Subscriptions', 'teknup-ai-mastering' ); ?></div>
		</div>

		<div class="teknup-stat-card">
			<div class="teknup-stat-icon">📈</div>
			<div class="teknup-stat-value"><?php echo esc_html( number_format_i18n( $stats['jobs_this_month'] ) ); ?></div>
			<div class="teknup-stat-label"><?php esc_html_e( 'Jobs This Month', 'teknup-ai-mastering' ); ?></div>
		</div>
	</div>

	<!-- Charts -->
	<div class="teknup-charts">
		<div class="teknup-chart-container">
			<h2><?php esc_html_e( 'Daily Usage (Last 30 Days)', 'teknup-ai-mastering' ); ?></h2>
			<canvas id="teknup-daily-usage-chart"></canvas>
		</div>
	</div>

	<!-- Recent Jobs -->
	<div class="teknup-recent-jobs">
		<h2><?php esc_html_e( 'Recent Jobs', 'teknup-ai-mastering' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'User', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'File', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Status', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Created', 'teknup-ai-mastering' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $recent_jobs ) ) : ?>
					<tr>
						<td colspan="5"><?php esc_html_e( 'No jobs yet.', 'teknup-ai-mastering' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $recent_jobs as $job ) : ?>
						<tr>
							<td><?php echo esc_html( $job->id ); ?></td>
							<td>
								<?php
								$user = get_userdata( $job->user_id );
								echo esc_html( $user ? $user->display_name : 'Unknown' );
								?>
							</td>
							<td><?php echo esc_html( $job->original_filename ); ?></td>
							<td>
								<span class="teknup-status-badge teknup-status-<?php echo esc_attr( $job->status ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $job->status ) ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job->created_at ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=teknup-jobs' ) ); ?>" class="button">
				<?php esc_html_e( 'View All Jobs', 'teknup-ai-mastering' ); ?>
			</a>
		</p>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Daily usage chart
	var ctx = document.getElementById('teknup-daily-usage-chart');
	if (ctx) {
		var dailyUsageData = <?php echo json_encode( array_values( $daily_usage ) ); ?>;
		var dailyUsageLabels = <?php echo json_encode( array_keys( $daily_usage ) ); ?>;

		// Format dates for better display
		var formattedLabels = dailyUsageLabels.map(function(date) {
			var d = new Date(date);
			return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
		});

		new Chart(ctx, {
			type: 'line',
			data: {
				labels: formattedLabels,
				datasets: [{
					label: '<?php esc_html_e( 'Jobs', 'teknup-ai-mastering' ); ?>',
					data: dailyUsageData,
					borderColor: '#9d2c20',
					backgroundColor: 'rgba(157, 44, 32, 0.1)',
					borderWidth: 3,
					tension: 0.4,
					fill: true,
					pointBackgroundColor: '#9d2c20',
					pointBorderColor: '#fff',
					pointBorderWidth: 2,
					pointRadius: 4,
					pointHoverRadius: 6,
					pointHoverBackgroundColor: '#ad1831',
					pointHoverBorderWidth: 3
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					intersect: false,
					mode: 'index'
				},
				plugins: {
					legend: {
						display: false
					},
					tooltip: {
						backgroundColor: 'rgba(35, 40, 45, 0.95)',
						titleColor: '#fff',
						bodyColor: '#fff',
						borderColor: '#9d2c20',
						borderWidth: 1,
						padding: 12,
						displayColors: false,
						callbacks: {
							title: function(context) {
								return context[0].label;
							},
							label: function(context) {
								return context.parsed.y + ' job' + (context.parsed.y !== 1 ? 's' : '');
							}
						}
					}
				},
				scales: {
					x: {
						grid: {
							display: false
						},
						ticks: {
							color: '#666',
							maxRotation: 45,
							minRotation: 45,
							font: {
								size: 11
							}
						}
					},
					y: {
						beginAtZero: true,
						grid: {
							color: 'rgba(0, 0, 0, 0.05)',
							borderDash: [5, 5]
						},
						ticks: {
							precision: 0,
							color: '#666',
							font: {
								size: 12
							}
						}
					}
				}
			}
		});
	}
});
</script>
