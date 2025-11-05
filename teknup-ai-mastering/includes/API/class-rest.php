<?php
/**
 * REST API class - Handles REST API endpoints
 *
 * @package Teknup\API
 */

namespace Teknup\API;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API class
 */
class REST {

	/**
	 * API namespace
	 *
	 * @var string
	 */
	private $namespace = 'teknup/v1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'init', array( $this, 'handle_file_download' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		// Get user quota
		register_rest_route(
			$this->namespace,
			'/quota',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_quota' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		// Upload file and create job
		register_rest_route(
			$this->namespace,
			'/upload',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'upload_file' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		// Get job status
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_job' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
				'args' => array(
					'id' => array(
						'required' => true,
						'type' => 'integer',
					),
				),
			)
		);

		// Get user jobs list
		register_rest_route(
			$this->namespace,
			'/jobs',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_jobs' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
			)
		);

		// Delete job
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)',
			array(
				'methods' => 'DELETE',
				'callback' => array( $this, 'delete_job' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
				'args' => array(
					'id' => array(
						'required' => true,
						'type' => 'integer',
					),
				),
			)
		);

		// Retry failed job
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)/retry',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'retry_job' ),
				'permission_callback' => array( $this, 'check_user_permission' ),
				'args' => array(
					'id' => array(
						'required' => true,
						'type' => 'integer',
					),
				),
			)
		);

		// Dolby webhook callback
		register_rest_route(
			$this->namespace,
			'/dolby/callback',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'dolby_callback' ),
				'permission_callback' => '__return_true', // Public endpoint
			)
		);

		// Admin: Get statistics
		register_rest_route(
			$this->namespace,
			'/admin/stats',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_statistics' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Admin: Get all jobs
		register_rest_route(
			$this->namespace,
			'/admin/jobs',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_all_jobs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);

		// Admin: Get logs
		register_rest_route(
			$this->namespace,
			'/admin/logs',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'check_admin_permission' ),
			)
		);
	}

	/**
	 * Check user permission
	 *
	 * @return bool True if user is logged in.
	 */
	public function check_user_permission() {
		return is_user_logged_in();
	}

	/**
	 * Check admin permission
	 *
	 * @return bool True if user can manage options.
	 */
	public function check_admin_permission() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get user quota endpoint
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_quota() {
		$quota = teknup_ai_mastering()->subscriptions->get_user_quota();

		return new \WP_REST_Response( $quota, 200 );
	}

	/**
	 * Upload file endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function upload_file( $request ) {
		// Check if user can upload
		$can_upload = teknup_ai_mastering()->subscriptions->can_user_upload();

		if ( is_wp_error( $can_upload ) ) {
			return new \WP_REST_Response(
				array( 'error' => $can_upload->get_error_message() ),
				403
			);
		}

		// Get uploaded file
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'No file uploaded.', 'teknup-ai-mastering' ) ),
				400
			);
		}

		$file = $files['file'];

		// Get parameters
		$intensity = sanitize_text_field( $request->get_param( 'intensity' ) ?: 'medium' );
		$genre = sanitize_text_field( $request->get_param( 'genre' ) ?: null );
		$target_lufs = $request->get_param( 'target_lufs' ) ? (float) $request->get_param( 'target_lufs' ) : null;

		// Create job first (to get job ID)
		$job_id = teknup_ai_mastering()->jobs->create_job(
			array(
				'user_id' => get_current_user_id(),
				'original_filename' => sanitize_file_name( $file['name'] ),
				'original_filepath' => '', // Will be updated after upload
				'file_size' => 0, // Will be updated after upload
				'status' => 'pending',
				'intensity' => $intensity,
				'genre' => $genre,
				'target_lufs' => $target_lufs,
			)
		);

		if ( is_wp_error( $job_id ) ) {
			return new \WP_REST_Response(
				array( 'error' => $job_id->get_error_message() ),
				500
			);
		}

		// Save uploaded file
		$file_info = teknup_ai_mastering()->storage->save_upload( $file, $job_id );

		if ( is_wp_error( $file_info ) ) {
			// Delete the job if upload fails
			teknup_ai_mastering()->jobs->delete_job( $job_id );

			return new \WP_REST_Response(
				array( 'error' => $file_info->get_error_message() ),
				400
			);
		}

		// Update job with file info
		teknup_ai_mastering()->jobs->update_status(
			$job_id,
			'uploaded',
			array(
				'original_filepath' => $file_info['filepath'],
				'file_size' => $file_info['size'],
			)
		);

		// Submit to Dolby
		$dolby = new Dolby();
		$result = $dolby->submit_job( $job_id );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array(
					'error' => $result->get_error_message(),
					'job_id' => $job_id,
				),
				500
			);
		}

		// Get updated job
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );
		$formatted_job = teknup_ai_mastering()->jobs->format_job_for_api( $job );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'job' => $formatted_job,
			),
			201
		);
	}

	/**
	 * Get job endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_job( $request ) {
		$job_id = (int) $request->get_param( 'id' );
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'Job not found.', 'teknup-ai-mastering' ) ),
				404
			);
		}

		// Check if user owns this job
		if ( ! teknup_ai_mastering()->jobs->user_owns_job( $job_id, get_current_user_id() ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'You do not have permission to access this job.', 'teknup-ai-mastering' ) ),
				403
			);
		}

		$formatted_job = teknup_ai_mastering()->jobs->format_job_for_api( $job );

		return new \WP_REST_Response( $formatted_job, 200 );
	}

	/**
	 * Get jobs list endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_jobs( $request ) {
		$page = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$status = sanitize_text_field( $request->get_param( 'status' ) ?: null );

		$args = array(
			'status' => $status,
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);

		$jobs = teknup_ai_mastering()->jobs->get_user_jobs( get_current_user_id(), $args );
		$total = teknup_ai_mastering()->jobs->get_user_jobs_count( get_current_user_id(), $status );

		$formatted_jobs = array();
		foreach ( $jobs as $job ) {
			$formatted_jobs[] = teknup_ai_mastering()->jobs->format_job_for_api( $job );
		}

		return new \WP_REST_Response(
			array(
				'jobs' => $formatted_jobs,
				'total' => $total,
				'page' => $page,
				'per_page' => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			),
			200
		);
	}

	/**
	 * Delete job endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function delete_job( $request ) {
		$job_id = (int) $request->get_param( 'id' );

		// Check if user owns this job
		if ( ! teknup_ai_mastering()->jobs->user_owns_job( $job_id, get_current_user_id() ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'You do not have permission to delete this job.', 'teknup-ai-mastering' ) ),
				403
			);
		}

		$result = teknup_ai_mastering()->jobs->delete_job( $job_id );

		if ( ! $result ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'Failed to delete job.', 'teknup-ai-mastering' ) ),
				500
			);
		}

		return new \WP_REST_Response(
			array( 'success' => true ),
			200
		);
	}

	/**
	 * Retry job endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function retry_job( $request ) {
		$job_id = (int) $request->get_param( 'id' );

		// Check if user owns this job
		if ( ! teknup_ai_mastering()->jobs->user_owns_job( $job_id, get_current_user_id() ) && ! current_user_can( 'manage_options' ) ) {
			return new \WP_REST_Response(
				array( 'error' => __( 'You do not have permission to retry this job.', 'teknup-ai-mastering' ) ),
				403
			);
		}

		$result = teknup_ai_mastering()->jobs->retry_job( $job_id );

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response(
				array( 'error' => $result->get_error_message() ),
				400
			);
		}

		$job = teknup_ai_mastering()->jobs->get_job( $job_id );
		$formatted_job = teknup_ai_mastering()->jobs->format_job_for_api( $job );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'job' => $formatted_job,
			),
			200
		);
	}

	/**
	 * Dolby webhook callback endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function dolby_callback( $request ) {
		$body = $request->get_json_params();

		teknup_ai_mastering()->log( 'Received Dolby webhook: ' . json_encode( $body ), 'info' );

		if ( ! isset( $body['job_id'] ) ) {
			return new \WP_REST_Response(
				array( 'error' => 'Missing job_id' ),
				400
			);
		}

		$dolby_job_id = sanitize_text_field( $body['job_id'] );

		// Find job by Dolby job ID
		$job = teknup_ai_mastering()->jobs->get_job_by_dolby_id( $dolby_job_id );

		if ( ! $job ) {
			teknup_ai_mastering()->log( "Job not found for Dolby job ID: {$dolby_job_id}", 'error' );
			return new \WP_REST_Response(
				array( 'error' => 'Job not found' ),
				404
			);
		}

		// Handle status update
		$dolby = new Dolby();
		$dolby->handle_job_status_update( $job->id, $body );

		return new \WP_REST_Response(
			array( 'success' => true ),
			200
		);
	}

	/**
	 * Get statistics endpoint (admin)
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_statistics() {
		$stats = teknup_ai_mastering()->jobs->get_statistics();

		// Add subscription stats
		if ( function_exists( 'wcs_get_subscriptions' ) ) {
			$subscriptions = wcs_get_subscriptions(
				array(
					'status' => 'active',
					'subscriptions_per_page' => -1,
				)
			);

			$stats['active_subscriptions'] = count( $subscriptions );

			// Count by plan
			$stats['subscriptions_by_plan'] = array(
				'starter' => 0,
				'pro' => 0,
				'label' => 0,
			);

			foreach ( $subscriptions as $subscription ) {
				foreach ( $subscription->get_items() as $item ) {
					$product_name = strtolower( $item->get_name() );

					if ( strpos( $product_name, 'label' ) !== false ) {
						$stats['subscriptions_by_plan']['label']++;
					} elseif ( strpos( $product_name, 'pro' ) !== false ) {
						$stats['subscriptions_by_plan']['pro']++;
					} elseif ( strpos( $product_name, 'starter' ) !== false ) {
						$stats['subscriptions_by_plan']['starter']++;
					}
				}
			}
		} else {
			$stats['active_subscriptions'] = 0;
			$stats['subscriptions_by_plan'] = array(
				'starter' => 0,
				'pro' => 0,
				'label' => 0,
			);
		}

		// Add user count
		$stats['total_users'] = count_users();
		$stats['total_users'] = $stats['total_users']['total_users'];

		return new \WP_REST_Response( $stats, 200 );
	}

	/**
	 * Get all jobs endpoint (admin)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_all_jobs( $request ) {
		$page = max( 1, (int) $request->get_param( 'page' ) ?: 1 );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ?: 20 ) );
		$status = sanitize_text_field( $request->get_param( 'status' ) ?: null );
		$user_id = (int) $request->get_param( 'user_id' ) ?: null;

		$args = array(
			'status' => $status,
			'user_id' => $user_id,
			'limit' => $per_page,
			'offset' => ( $page - 1 ) * $per_page,
		);

		$jobs = teknup_ai_mastering()->jobs->get_all_jobs( $args );
		$total = teknup_ai_mastering()->jobs->get_all_jobs_count( $args );

		$formatted_jobs = array();
		foreach ( $jobs as $job ) {
			$formatted_job = teknup_ai_mastering()->jobs->format_job_for_api( $job );
			$formatted_job['user'] = get_userdata( $job->user_id );
			$formatted_jobs[] = $formatted_job;
		}

		return new \WP_REST_Response(
			array(
				'jobs' => $formatted_jobs,
				'total' => $total,
				'page' => $page,
				'per_page' => $per_page,
				'total_pages' => ceil( $total / $per_page ),
			),
			200
		);
	}

	/**
	 * Get logs endpoint (admin)
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_logs( $request ) {
		$logs = get_option( 'teknup_logs', array() );
		$level = sanitize_text_field( $request->get_param( 'level' ) ?: null );

		if ( $level ) {
			$logs = array_filter(
				$logs,
				function( $log ) use ( $level ) {
					return $log['level'] === $level;
				}
			);
		}

		// Reverse to show newest first
		$logs = array_reverse( $logs );

		// Limit to 500 entries
		$logs = array_slice( $logs, 0, 500 );

		return new \WP_REST_Response( $logs, 200 );
	}

	/**
	 * Handle file download via query parameter
	 */
	public function handle_file_download() {
		if ( ! isset( $_GET['teknup_download'] ) ) {
			return;
		}

		$token = sanitize_text_field( $_GET['teknup_download'] );
		$type = isset( $_GET['type'] ) ? sanitize_text_field( $_GET['type'] ) : 'mastered';

		teknup_ai_mastering()->storage->handle_download( $token, $type );
	}
}
