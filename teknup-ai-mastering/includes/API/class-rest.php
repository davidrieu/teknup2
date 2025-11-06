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
				'permission_callback' => array( $this, 'check_subscription_permission' ),
			)
		);

		// Upload file and create job
		register_rest_route(
			$this->namespace,
			'/upload',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'upload_file' ),
				'permission_callback' => array( $this, 'check_subscription_permission' ),
			)
		);

		// Get job status
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_job' ),
				'permission_callback' => array( $this, 'check_subscription_permission' ),
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
				'permission_callback' => array( $this, 'check_subscription_permission' ),
			)
		);

		// Delete job
		register_rest_route(
			$this->namespace,
			'/jobs/(?P<id>\d+)',
			array(
				'methods' => 'DELETE',
				'callback' => array( $this, 'delete_job' ),
				'permission_callback' => array( $this, 'check_subscription_permission' ),
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
				'permission_callback' => array( $this, 'check_subscription_permission' ),
				'args' => array(
					'id' => array(
						'required' => true,
						'type' => 'integer',
					),
				),
			)
		);

		// Tonn webhook callback
		register_rest_route(
			$this->namespace,
			'/tonn/callback',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'tonn_callback' ),
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

		// Auth: Register new user
		register_rest_route(
			$this->namespace,
			'/auth/register',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'register_user' ),
				'permission_callback' => '__return_true', // Public endpoint
			)
		);

		// Auth: Login user
		register_rest_route(
			$this->namespace,
			'/auth/login',
			array(
				'methods' => 'POST',
				'callback' => array( $this, 'login_user' ),
				'permission_callback' => '__return_true', // Public endpoint
			)
		);

		// Get subscription plans
		register_rest_route(
			$this->namespace,
			'/subscription-plans',
			array(
				'methods' => 'GET',
				'callback' => array( $this, 'get_subscription_plans' ),
				'permission_callback' => '__return_true', // Public endpoint
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
	 * Check subscription permission
	 *
	 * @return bool|WP_Error True if user has active subscription, error otherwise.
	 */
	public function check_subscription_permission() {
		if ( ! is_user_logged_in() ) {
			return new \WP_Error(
				'not_logged_in',
				__( 'You must be logged in to access this resource', 'teknup-ai-mastering' ),
				array( 'status' => 401 )
			);
		}

		$has_subscription = teknup_ai_mastering()->subscriptions->has_active_subscription();

		if ( ! $has_subscription ) {
			return new \WP_Error(
				'no_active_subscription',
				__( 'You need an active subscription to use this feature', 'teknup-ai-mastering' ),
				array( 'status' => 403 )
			);
		}

		return true;
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

		// Get parameters with techno-optimized defaults
		$intensity = sanitize_text_field( $request->get_param( 'intensity' ) ?: 'high' );
		$genre = sanitize_text_field( $request->get_param( 'genre' ) ?: 'techno' );
		$target_lufs = $request->get_param( 'target_lufs' ) ? (float) $request->get_param( 'target_lufs' ) : -9.0;

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

		// Submit to Tonn
		$tonn = new Tonn();
		$result = $tonn->submit_job( $job_id );

		if ( is_wp_error( $result ) ) {
			teknup_ai_mastering()->log(
				'Failed to submit job to Tonn: ' . $result->get_error_message() . ' | Code: ' . $result->get_error_code(),
				'error'
			);

			return new \WP_REST_Response(
				array(
					'error' => $result->get_error_message(),
					'job_id' => $job_id,
				),
				500
			);
		}

		teknup_ai_mastering()->log( "Job {$job_id} submitted successfully to Tonn, retrieving job data...", 'debug' );

		// Get updated job
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job ) {
			teknup_ai_mastering()->log( "ERROR: Could not retrieve job {$job_id} after submission", 'error' );
			return new \WP_REST_Response(
				array( 'error' => 'Job created but could not be retrieved' ),
				500
			);
		}

		teknup_ai_mastering()->log( "Job {$job_id} retrieved, formatting for API response...", 'debug' );

		$formatted_job = teknup_ai_mastering()->jobs->format_job_for_api( $job );

		teknup_ai_mastering()->log( "Job {$job_id} formatted successfully, returning 201 response", 'debug' );

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
	 * Tonn webhook callback endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function tonn_callback( $request ) {
		// CRITICAL: Wrap everything in try/catch to capture ALL errors
		try {
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] ========== WEBHOOK CALLED ==========', 'info' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Request method: ' . $request->get_method(), 'debug' );

			// Log raw request data
			$raw_body = $request->get_body();
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Raw body: ' . $raw_body, 'debug' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Content-Type: ' . $request->get_header( 'content-type' ), 'debug' );

			$body = $request->get_json_params();

			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Parsed JSON: ' . json_encode( $body ), 'info' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Body type: ' . gettype( $body ), 'debug' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Body is_array: ' . (is_array( $body ) ? 'yes' : 'no'), 'debug' );

			// Check if body is null or empty
			if ( $body === null || $body === false ) {
				teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Body is NULL/FALSE - possibly malformed JSON or empty request', 'error' );
				teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Returning 200 OK for empty/test webhook', 'info' );
				return new \WP_REST_Response(
					array( 'success' => true, 'message' => 'Empty webhook received - test successful' ),
					200
				);
			}

			// Tonn webhook format uses 'state' and 'mixrevive_task_id' (with underscores)
			if ( ! isset( $body['mixrevive_task_id'] ) || ! isset( $body['state'] ) ) {
				teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Missing required fields (mixrevive_task_id or state)', 'error' );
				teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Available keys: ' . implode( ', ', array_keys( $body ) ), 'debug' );
				teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Returning 200 OK anyway to pass Tonn test', 'info' );
				// Return 200 OK even if validation fails - this might be a test ping from Tonn
				return new \WP_REST_Response(
					array( 'success' => true, 'message' => 'Webhook test received' ),
					200
				);
			}

			// Handle webhook with Tonn API
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Validation passed, calling handle_webhook()', 'info' );
			$tonn = new \Teknup\API\Tonn();
			$result = $tonn->handle_webhook( $body );

			if ( ! $result ) {
				teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] handle_webhook() returned false', 'error' );
				return new \WP_REST_Response(
					array( 'error' => 'Failed to process webhook' ),
					500
				);
			}

			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Success! Returning 200 OK', 'info' );
			return new \WP_REST_Response(
				array( 'success' => true ),
				200
			);

		} catch ( \Exception $e ) {
			// Catch ANY error and log it
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] !!!!! EXCEPTION CAUGHT !!!!!', 'error' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Exception: ' . $e->getMessage(), 'error' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] File: ' . $e->getFile() . ':' . $e->getLine(), 'error' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Stack trace: ' . $e->getTraceAsString(), 'error' );

			// STILL return 200 OK so Tonn doesn't reject the webhook
			return new \WP_REST_Response(
				array( 'success' => true, 'message' => 'Error caught but returning 200 OK', 'error' => $e->getMessage() ),
				200
			);
		} catch ( \Error $e ) {
			// Catch PHP 7+ Fatal Errors
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] !!!!! FATAL ERROR CAUGHT !!!!!', 'error' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] Error: ' . $e->getMessage(), 'error' );
			teknup_ai_mastering()->log( '[WEBHOOK v2.1.5] File: ' . $e->getFile() . ':' . $e->getLine(), 'error' );

			// STILL return 200 OK so Tonn doesn't reject the webhook
			return new \WP_REST_Response(
				array( 'success' => true, 'message' => 'Fatal error caught but returning 200 OK', 'error' => $e->getMessage() ),
				200
			);
		}
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
	 * Register new user endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function register_user( $request ) {
		$username = sanitize_user( $request->get_param( 'username' ) );
		$email    = sanitize_email( $request->get_param( 'email' ) );
		$password = $request->get_param( 'password' );

		// Validation
		if ( empty( $username ) || empty( $email ) || empty( $password ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'All fields are required', 'teknup-ai-mastering' ) ),
				400
			);
		}

		if ( ! is_email( $email ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Invalid email address', 'teknup-ai-mastering' ) ),
				400
			);
		}

		if ( username_exists( $username ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Username already exists', 'teknup-ai-mastering' ) ),
				400
			);
		}

		if ( email_exists( $email ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Email already registered', 'teknup-ai-mastering' ) ),
				400
			);
		}

		// Create user
		$user_id = wp_create_user( $username, $password, $email );

		if ( is_wp_error( $user_id ) ) {
			return new \WP_REST_Response(
				array( 'message' => $user_id->get_error_message() ),
				500
			);
		}

		// Log user in automatically
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		return new \WP_REST_Response(
			array(
				'message' => __( 'Account created successfully', 'teknup-ai-mastering' ),
				'user_id' => $user_id,
			),
			201
		);
	}

	/**
	 * Login user endpoint
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function login_user( $request ) {
		$username = sanitize_text_field( $request->get_param( 'username' ) );
		$password = $request->get_param( 'password' );

		// Validation
		if ( empty( $username ) || empty( $password ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Username and password are required', 'teknup-ai-mastering' ) ),
				400
			);
		}

		// Authenticate
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'Invalid username or password', 'teknup-ai-mastering' ) ),
				401
			);
		}

		// Set authentication
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		return new \WP_REST_Response(
			array(
				'message' => __( 'Logged in successfully', 'teknup-ai-mastering' ),
				'user_id' => $user->ID,
			),
			200
		);
	}

	/**
	 * Get subscription plans endpoint
	 *
	 * @return WP_REST_Response Response object.
	 */
	public function get_subscription_plans() {
		if ( ! class_exists( 'WC_Subscriptions' ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'WooCommerce Subscriptions is not active', 'teknup-ai-mastering' ) ),
				500
			);
		}

		$product_ids = get_option( 'teknup_subscription_products', array() );

		if ( empty( $product_ids ) ) {
			return new \WP_REST_Response(
				array( 'message' => __( 'No subscription plans available', 'teknup-ai-mastering' ) ),
				404
			);
		}

		$plans = array();

		// Plan limits mapping
		$plan_limits = array(
			'Free Trial'   => 3,
			'Starter Plan' => 20,
			'Pro Plan'     => 'unlimited',
			'Label Plan'   => 'unlimited',
		);

		foreach ( $product_ids as $name => $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$price = $product->get_price();
			$period = '';

			if ( is_a( $product, 'WC_Product_Subscription' ) ) {
				$period = $product->get_meta( '_subscription_period' );
			}

			$plans[] = array(
				'id'       => $product_id,
				'name'     => $name,
				'price'    => $price,
				'currency' => get_woocommerce_currency_symbol(),
				'period'   => $period ?: 'month',
				'limit'    => isset( $plan_limits[ $name ] ) ? $plan_limits[ $name ] : 'unlimited',
				'featured' => $name === 'Pro Plan', // Mark Pro Plan as featured
			);
		}

		return new \WP_REST_Response(
			array( 'plans' => $plans ),
			200
		);
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
