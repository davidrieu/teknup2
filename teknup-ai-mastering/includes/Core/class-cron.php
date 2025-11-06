<?php
/**
 * Cron class - Handles scheduled tasks
 *
 * @package Teknup\Core
 */

namespace Teknup\Core;

use Teknup\API\Tonn;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron class
 */
class Cron {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Register custom cron schedules
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Register cron hooks
		add_action( 'teknup_check_pending_jobs', array( $this, 'check_pending_jobs' ) );
		add_action( 'teknup_cleanup_old_files', array( $this, 'cleanup_old_files' ) );
		add_action( 'teknup_cleanup_old_jobs', array( $this, 'cleanup_old_jobs' ) );
		add_action( 'teknup_cleanup_transients', array( $this, 'cleanup_transients' ) );
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['teknup_five_minutes'] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display' => __( 'Every 5 Minutes', 'teknup-ai-mastering' ),
		);

		return $schedules;
	}

	/**
	 * Check pending jobs
	 */
	public function check_pending_jobs() {
		teknup_ai_mastering()->log( 'Checking pending jobs...', 'info' );

		$database = new Database();
		$pending_jobs = $database->get_pending_jobs( 50 );

		if ( empty( $pending_jobs ) ) {
			return;
		}

		$tonn = new Tonn();

		foreach ( $pending_jobs as $job ) {
			// Skip if job was recently created (give it some time)
			if ( strtotime( $job->created_at ) > strtotime( '-2 minutes' ) ) {
				continue;
			}

			// Check job status with Tonn
			if ( ! empty( $job->tonn_job_id ) ) {
				$status = $tonn->check_job_status( $job->tonn_job_id );

				if ( is_wp_error( $status ) ) {
					teknup_ai_mastering()->log(
						"Failed to check status for job {$job->id}: " . $status->get_error_message(),
						'error'
					);

					// If job has been processing for more than 15 minutes, mark as failed
					if ( strtotime( $job->sent_to_tonn_at ) < strtotime( '-15 minutes' ) ) {
						teknup_ai_mastering()->jobs->update_status(
							$job->id,
							'failed',
							array( 'error_message' => __( 'Job timed out after 15 minutes.', 'teknup-ai-mastering' ) )
						);
					}

					continue;
				}

				// Check if preview track (with watermark) is ready
				// In preview mode, we use download_url_preview_revived (free mode for testing)
				if ( isset( $status['revivedTrackTaskResults'] ) && ! empty( $status['revivedTrackTaskResults'] ) ) {
					$track_data = $status['revivedTrackTaskResults'];

					// If download_url_preview_revived is available, finalize the job (preview mode)
					if ( isset( $track_data['download_url_preview_revived'] ) && ! empty( $track_data['download_url_preview_revived'] ) ) {
						teknup_ai_mastering()->log( "Cron: Preview track available for job {$job->id}, finalizing (free mode)", 'info' );
						$tonn->handle_webhook(
							array_merge(
								$track_data,
								array(
									'mixrevive_task_id' => $job->tonn_job_id,
									'state' => 'MIXREVIVE_TASK_PREVIEW_COMPLETED',
								)
							)
						);
					}
				}
			} elseif ( $job->status === 'pending' ) {
				// Job hasn't been sent to Tonn yet, mark as stuck if too old
				if ( strtotime( $job->created_at ) < strtotime( '-10 minutes' ) ) {
					teknup_ai_mastering()->jobs->update_status(
						$job->id,
						'failed',
						array( 'error_message' => __( 'Job was not processed in time.', 'teknup-ai-mastering' ) )
					);
				}
			}
		}

		teknup_ai_mastering()->log( 'Pending jobs check completed.', 'info' );
	}

	/**
	 * Cleanup old files
	 */
	public function cleanup_old_files() {
		teknup_ai_mastering()->log( 'Cleaning up old files...', 'info' );

		$retention_days = (int) teknup_ai_mastering()->get_setting( 'file_retention_days', 30 );
		$deleted = teknup_ai_mastering()->storage->clean_old_files( $retention_days );

		teknup_ai_mastering()->log( "Cleaned up {$deleted} old files.", 'info' );

		/**
		 * Fires after old files are cleaned up
		 *
		 * @param int $deleted Number of deleted files.
		 * @param int $retention_days Retention period in days.
		 */
		do_action( 'teknup_old_files_cleaned', $deleted, $retention_days );
	}

	/**
	 * Cleanup old jobs
	 */
	public function cleanup_old_jobs() {
		teknup_ai_mastering()->log( 'Cleaning up old jobs...', 'info' );

		$retention_days = (int) teknup_ai_mastering()->get_setting( 'file_retention_days', 30 );
		$database = new Database();
		$deleted = $database->delete_old_jobs( $retention_days );

		teknup_ai_mastering()->log( "Cleaned up {$deleted} old jobs.", 'info' );

		/**
		 * Fires after old jobs are cleaned up
		 *
		 * @param int $deleted Number of deleted jobs.
		 * @param int $retention_days Retention period in days.
		 */
		do_action( 'teknup_old_jobs_cleaned', $deleted, $retention_days );
	}

	/**
	 * Cleanup expired transients
	 */
	public function cleanup_transients() {
		global $wpdb;

		teknup_ai_mastering()->log( 'Cleaning up expired transients...', 'info' );

		// Delete expired Teknup transients
		$deleted = $wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_timeout_teknup_%'
			AND option_value < UNIX_TIMESTAMP()"
		);

		if ( $deleted ) {
			// Also delete the corresponding transient values
			$wpdb->query(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE '_transient_teknup_%'
				AND option_name NOT IN (
					SELECT CONCAT('_transient_', SUBSTRING(option_name, 20))
					FROM {$wpdb->options}
					WHERE option_name LIKE '_transient_timeout_teknup_%'
				)"
			);

			teknup_ai_mastering()->log( "Cleaned up {$deleted} expired transients.", 'info' );
		}

		/**
		 * Fires after transients are cleaned up
		 *
		 * @param int $deleted Number of deleted transients.
		 */
		do_action( 'teknup_transients_cleaned', $deleted );
	}
}
