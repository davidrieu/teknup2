<?php
/**
 * Jobs class - Handles job operations
 *
 * @package Teknup\Core
 */

namespace Teknup\Core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Jobs class
 */
class Jobs {

	/**
	 * Database instance
	 *
	 * @var Database
	 */
	private $db;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->db = new Database();
	}

	/**
	 * Create a new job
	 *
	 * @param array $data Job data.
	 * @return int|WP_Error Job ID or WP_Error on failure.
	 */
	public function create_job( $data ) {
		$job_id = $this->db->insert_job( $data );

		if ( ! $job_id ) {
			return new \WP_Error( 'create_failed', __( 'Failed to create job.', 'teknup-ai-mastering' ) );
		}

		teknup_ai_mastering()->log( "Job {$job_id} created for user {$data['user_id']}", 'info' );

		/**
		 * Fires after a job is created
		 *
		 * @param int   $job_id Job ID.
		 * @param array $data Job data.
		 */
		do_action( 'teknup_job_created', $job_id, $data );

		return $job_id;
	}

	/**
	 * Update job status
	 *
	 * @param int    $job_id Job ID.
	 * @param string $status New status.
	 * @param array  $additional_data Additional data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_status( $job_id, $status, $additional_data = array() ) {
		$data = array_merge(
			array( 'status' => $status ),
			$additional_data
		);

		// Add timestamp for specific statuses
		$timestamp_fields = array(
			'uploaded' => 'uploaded_at',
			'sent_to_dolby' => 'sent_to_dolby_at',
			'processing' => 'processing_started_at',
			'completed' => 'completed_at',
			'failed' => 'failed_at',
		);

		if ( isset( $timestamp_fields[ $status ] ) && ! isset( $data[ $timestamp_fields[ $status ] ] ) ) {
			$data[ $timestamp_fields[ $status ] ] = current_time( 'mysql' );
		}

		$result = $this->db->update_job( $job_id, $data );

		if ( $result ) {
			teknup_ai_mastering()->log( "Job {$job_id} status updated to {$status}", 'info' );

			/**
			 * Fires after a job status is updated
			 *
			 * @param int    $job_id Job ID.
			 * @param string $status New status.
			 * @param array  $data Updated data.
			 */
			do_action( 'teknup_job_status_updated', $job_id, $status, $data );

			// Handle specific statuses
			if ( $status === 'completed' ) {
				do_action( 'teknup_job_completed', $job_id );
			} elseif ( $status === 'failed' ) {
				do_action( 'teknup_job_failed', $job_id );
			}
		}

		return $result;
	}

	/**
	 * Get a job
	 *
	 * @param int $job_id Job ID.
	 * @return object|null Job object or null if not found.
	 */
	public function get_job( $job_id ) {
		return $this->db->get_job( $job_id );
	}

	/**
	 * Get a job by Dolby job ID
	 *
	 * @param string $dolby_job_id Dolby job ID.
	 * @return object|null Job object or null if not found.
	 */
	public function get_job_by_dolby_id( $dolby_job_id ) {
		return $this->db->get_job_by_dolby_id( $dolby_job_id );
	}

	/**
	 * Get user jobs
	 *
	 * @param int   $user_id User ID.
	 * @param array $args Query arguments.
	 * @return array Array of job objects.
	 */
	public function get_user_jobs( $user_id, $args = array() ) {
		return $this->db->get_user_jobs( $user_id, $args );
	}

	/**
	 * Get user jobs count
	 *
	 * @param int    $user_id User ID.
	 * @param string $status Optional status filter.
	 * @return int Jobs count.
	 */
	public function get_user_jobs_count( $user_id, $status = null ) {
		return $this->db->get_user_jobs_count( $user_id, $status );
	}

	/**
	 * Get user monthly jobs count
	 *
	 * @param int $user_id User ID.
	 * @return int Jobs count.
	 */
	public function get_user_monthly_jobs_count( $user_id ) {
		return $this->db->get_user_monthly_jobs_count( $user_id );
	}

	/**
	 * Get all jobs (admin)
	 *
	 * @param array $args Query arguments.
	 * @return array Array of job objects.
	 */
	public function get_all_jobs( $args = array() ) {
		return $this->db->get_all_jobs( $args );
	}

	/**
	 * Get all jobs count (admin)
	 *
	 * @param array $args Query arguments.
	 * @return int Jobs count.
	 */
	public function get_all_jobs_count( $args = array() ) {
		return $this->db->get_all_jobs_count( $args );
	}

	/**
	 * Delete a job
	 *
	 * @param int $job_id Job ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_job( $job_id ) {
		// Get job to delete files
		$job = $this->get_job( $job_id );

		if ( $job ) {
			// Delete files
			if ( ! empty( $job->original_filepath ) ) {
				teknup_ai_mastering()->storage->delete_file( $job->original_filepath );
			}

			if ( ! empty( $job->mastered_filepath ) ) {
				teknup_ai_mastering()->storage->delete_file( $job->mastered_filepath );
			}
		}

		$result = $this->db->delete_job( $job_id );

		if ( $result ) {
			teknup_ai_mastering()->log( "Job {$job_id} deleted", 'info' );

			/**
			 * Fires after a job is deleted
			 *
			 * @param int $job_id Job ID.
			 */
			do_action( 'teknup_job_deleted', $job_id );
		}

		return $result;
	}

	/**
	 * Check if user owns job
	 *
	 * @param int $job_id Job ID.
	 * @param int $user_id User ID.
	 * @return bool True if user owns job, false otherwise.
	 */
	public function user_owns_job( $job_id, $user_id ) {
		$job = $this->get_job( $job_id );

		if ( ! $job ) {
			return false;
		}

		return (int) $job->user_id === (int) $user_id;
	}

	/**
	 * Get job formatted for API response
	 *
	 * @param object $job Job object.
	 * @return array Formatted job data.
	 */
	public function format_job_for_api( $job ) {
		if ( ! $job ) {
			return null;
		}

		return array(
			'id' => (int) $job->id,
			'status' => $job->status,
			'original_filename' => $job->original_filename,
			'file_size' => (int) $job->file_size,
			'intensity' => $job->intensity,
			'genre' => $job->genre,
			'target_lufs' => $job->target_lufs ? (float) $job->target_lufs : null,
			'error_message' => $job->error_message,
			'created_at' => $job->created_at,
			'completed_at' => $job->completed_at,
			'download_url' => $job->status === 'completed' && $job->mastered_filepath
				? teknup_ai_mastering()->storage->generate_download_url( $job->id, 'mastered' )
				: null,
			'processing_time' => $this->calculate_processing_time( $job ),
		);
	}

	/**
	 * Calculate processing time
	 *
	 * @param object $job Job object.
	 * @return int|null Processing time in seconds or null.
	 */
	private function calculate_processing_time( $job ) {
		if ( empty( $job->sent_to_dolby_at ) || empty( $job->completed_at ) ) {
			return null;
		}

		$start = strtotime( $job->sent_to_dolby_at );
		$end = strtotime( $job->completed_at );

		return $end - $start;
	}

	/**
	 * Get statistics
	 *
	 * @return array Statistics.
	 */
	public function get_statistics() {
		return $this->db->get_statistics();
	}

	/**
	 * Retry a failed job
	 *
	 * @param int $job_id Job ID.
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 */
	public function retry_job( $job_id ) {
		$job = $this->get_job( $job_id );

		if ( ! $job ) {
			return new \WP_Error( 'job_not_found', __( 'Job not found.', 'teknup-ai-mastering' ) );
		}

		if ( $job->status !== 'failed' ) {
			return new \WP_Error( 'invalid_status', __( 'Only failed jobs can be retried.', 'teknup-ai-mastering' ) );
		}

		// Reset job status
		$this->update_status(
			$job_id,
			'pending',
			array(
				'error_message' => null,
				'failed_at' => null,
				'replicate_prediction_id' => null,
			)
		);

		// Send to Replicate
		$replicate = new \Teknup\API\Replicate();
		$result = $replicate->submit_job( $job_id );

		return $result;
	}
}
