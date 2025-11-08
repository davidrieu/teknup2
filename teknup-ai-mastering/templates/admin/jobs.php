<?php
/**
 * Admin Jobs Template
 *
 * @package Teknup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get pagination parameters
$paged = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = 50;
$offset = ( $paged - 1 ) * $per_page;

// Get filter parameters
$status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
$user_filter = isset( $_GET['user_id'] ) ? intval( $_GET['user_id'] ) : 0;

// Build query args
$args = array(
	'limit' => $per_page,
	'offset' => $offset,
	'orderby' => 'created_at',
	'order' => 'DESC',
);

if ( ! empty( $status_filter ) ) {
	$args['status'] = $status_filter;
}

if ( ! empty( $user_filter ) ) {
	$args['user_id'] = $user_filter;
}

// Get jobs and total count
$jobs = teknup_ai_mastering()->jobs->get_all_jobs( $args );
$total_jobs = teknup_ai_mastering()->jobs->get_all_jobs_count( $args );
$total_pages = ceil( $total_jobs / $per_page );

// Get statistics
$stats = teknup_ai_mastering()->jobs->get_statistics();
?>

<div class="wrap teknup-jobs">
	<h1><?php esc_html_e( 'All Jobs', 'teknup-ai-mastering' ); ?></h1>

	<!-- Quick Stats -->
	<div class="teknup-jobs-stats">
		<div class="teknup-job-stat">
			<span class="count"><?php echo esc_html( number_format_i18n( $stats['total_jobs'] ) ); ?></span>
			<span class="label"><?php esc_html_e( 'Total', 'teknup-ai-mastering' ); ?></span>
		</div>
		<div class="teknup-job-stat">
			<span class="count"><?php echo esc_html( number_format_i18n( $stats['completed_jobs'] ) ); ?></span>
			<span class="label"><?php esc_html_e( 'Completed', 'teknup-ai-mastering' ); ?></span>
		</div>
		<div class="teknup-job-stat">
			<span class="count"><?php echo esc_html( number_format_i18n( $stats['failed_jobs'] ) ); ?></span>
			<span class="label"><?php esc_html_e( 'Failed', 'teknup-ai-mastering' ); ?></span>
		</div>
		<div class="teknup-job-stat">
			<span class="count"><?php echo esc_html( number_format_i18n( $stats['jobs_this_month'] ) ); ?></span>
			<span class="label"><?php esc_html_e( 'This Month', 'teknup-ai-mastering' ); ?></span>
		</div>
	</div>

	<!-- Filters -->
	<div class="teknup-jobs-filters">
		<form method="get" action="">
			<input type="hidden" name="page" value="teknup-jobs">

			<select name="status" id="status-filter">
				<option value=""><?php esc_html_e( 'All Statuses', 'teknup-ai-mastering' ); ?></option>
				<option value="pending" <?php selected( $status_filter, 'pending' ); ?>><?php esc_html_e( 'Pending', 'teknup-ai-mastering' ); ?></option>
				<option value="uploaded" <?php selected( $status_filter, 'uploaded' ); ?>><?php esc_html_e( 'Uploaded', 'teknup-ai-mastering' ); ?></option>
				<option value="processing" <?php selected( $status_filter, 'processing' ); ?>><?php esc_html_e( 'Processing', 'teknup-ai-mastering' ); ?></option>
				<option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php esc_html_e( 'Completed', 'teknup-ai-mastering' ); ?></option>
				<option value="failed" <?php selected( $status_filter, 'failed' ); ?>><?php esc_html_e( 'Failed', 'teknup-ai-mastering' ); ?></option>
			</select>

			<input type="submit" class="button" value="<?php esc_attr_e( 'Filter', 'teknup-ai-mastering' ); ?>">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=teknup-jobs' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'teknup-ai-mastering' ); ?></a>
		</form>
	</div>

	<!-- Jobs Table -->
	<div class="teknup-jobs-table-wrapper">
		<table class="wp-list-table widefat fixed striped teknup-jobs-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'User', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Filename', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Status', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Intensity', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Genre', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Created', 'teknup-ai-mastering' ); ?></th>
					<th><?php esc_html_e( 'Completed', 'teknup-ai-mastering' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $jobs ) ) : ?>
					<tr>
						<td colspan="8" class="no-jobs"><?php esc_html_e( 'No jobs found.', 'teknup-ai-mastering' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $jobs as $job ) : ?>
						<tr>
							<td><strong>#<?php echo esc_html( $job->id ); ?></strong></td>
							<td>
								<?php
								$user = get_userdata( $job->user_id );
								if ( $user ) {
									echo esc_html( $user->display_name );
									echo '<br><small>' . esc_html( $user->user_email ) . '</small>';
								} else {
									esc_html_e( 'Unknown', 'teknup-ai-mastering' );
								}
								?>
							</td>
							<td>
								<?php echo esc_html( $job->original_filename ); ?>
								<?php if ( $job->download_url ) : ?>
									<br><a href="<?php echo esc_url( $job->download_url ); ?>" class="download-link" target="_blank">⬇ <?php esc_html_e( 'Download', 'teknup-ai-mastering' ); ?></a>
								<?php endif; ?>
							</td>
							<td>
								<span class="teknup-status-badge teknup-status-<?php echo esc_attr( $job->status ); ?>">
									<?php echo esc_html( ucfirst( str_replace( '_', ' ', $job->status ) ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( ucfirst( $job->intensity ) ); ?></td>
							<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $job->genre ) ) ); ?></td>
							<td>
								<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $job->created_at ) ) ); ?>
								<br><small><?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $job->created_at ) ) ); ?></small>
							</td>
							<td>
								<?php if ( $job->completed_at ) : ?>
									<?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $job->completed_at ) ) ); ?>
									<br><small><?php echo esc_html( date_i18n( get_option( 'time_format' ), strtotime( $job->completed_at ) ) ); ?></small>
								<?php else : ?>
									<span class="pending-text">-</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
	</div>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: Number of jobs */
						_n( '%s job', '%s jobs', $total_jobs, 'teknup-ai-mastering' ),
						number_format_i18n( $total_jobs )
					);
					?>
				</span>
				<?php
				$page_links = paginate_links(
					array(
						'base' => add_query_arg( 'paged', '%#%' ),
						'format' => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total' => $total_pages,
						'current' => $paged,
					)
				);
				if ( $page_links ) {
					echo '<span class="pagination-links">' . $page_links . '</span>';
				}
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
