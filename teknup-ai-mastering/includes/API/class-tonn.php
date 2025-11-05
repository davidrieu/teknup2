<?php
/**
 * Tonn API class - Handles Tonn ROEX API integration for professional audio mastering and mixing
 *
 * @package Teknup\API
 */

namespace Teknup\API;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tonn API class
 */
class Tonn {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base_url = 'https://api.roexaudio.com/v1';

	/**
	 * API token
	 *
	 * @var string
	 */
	private $api_token;

	/**
	 * Max retry attempts
	 *
	 * @var int
	 */
	private $max_retries = 3;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_token = teknup_ai_mastering()->get_setting( 'tonn_api_token', '' );
	}

	/**
	 * Submit a job to Tonn
	 *
	 * @param int $job_id Job ID.
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 */
	public function submit_job( $job_id ) {
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job ) {
			return new \WP_Error( 'job_not_found', __( 'Job not found.', 'teknup-ai-mastering' ) );
		}

		// Check API token
		if ( empty( $this->api_token ) ) {
			return new \WP_Error( 'no_api_token', __( 'Tonn API token is not configured.', 'teknup-ai-mastering' ) );
		}

		// Generate temporary URL for Tonn to download the file
		$input_url = teknup_ai_mastering()->storage->generate_temp_url( $job_id );

		if ( is_wp_error( $input_url ) ) {
			return $input_url;
		}

		// Determine job type from settings
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();
		$job_type = isset( $settings['job_type'] ) ? $settings['job_type'] : 'mastering';

		teknup_ai_mastering()->log( "Submitting job {$job_id} to Tonn ({$job_type}) with audio: {$input_url}", 'info' );

		// Submit based on job type
		if ( $job_type === 'stem_separation' ) {
			$response = $this->submit_stem_separation( $job_id, $input_url, $job );
		} else {
			$response = $this->submit_mastering( $job_id, $input_url, $job );
		}

		if ( is_wp_error( $response ) ) {
			teknup_ai_mastering()->log( "Failed to submit job {$job_id} to Tonn: " . $response->get_error_message(), 'error' );

			// Update job status
			teknup_ai_mastering()->jobs->update_status(
				$job_id,
				'failed',
				$response->get_error_message()
			);

			return $response;
		}

		// Store Tonn job ID
		$tonn_job_id = isset( $response['id'] ) ? $response['id'] : null;

		if ( ! $tonn_job_id ) {
			return new \WP_Error( 'no_job_id', __( 'No job ID returned from Tonn.', 'teknup-ai-mastering' ) );
		}

		// Update job with Tonn job ID
		teknup_ai_mastering()->database->update_job(
			$job_id,
			array(
				'tonn_job_id' => $tonn_job_id,
				'status' => 'processing',
			)
		);

		teknup_ai_mastering()->log( "Job {$job_id} submitted to Tonn with job ID: {$tonn_job_id}", 'info' );

		return true;
	}

	/**
	 * Submit mastering job
	 *
	 * @param int    $job_id Job ID.
	 * @param string $input_url Input audio URL.
	 * @param object $job Job data.
	 * @return array|WP_Error Response or error.
	 */
	private function submit_mastering( $job_id, $input_url, $job ) {
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();

		$webhook_url = rest_url( 'teknup/v1/tonn/callback' );

		teknup_ai_mastering()->log( "Webhook URL: {$webhook_url}", 'debug' );

		// Prepare mastering parameters
		$body = array(
			'input_url' => $input_url,
			'webhook_url' => $webhook_url,
			'job_type' => 'mastering',
			'parameters' => array(
				// Master level (loudness target in LUFS)
				'target_loudness' => isset( $settings['target_loudness'] ) ? (float) $settings['target_loudness'] : -14.0,

				// Mastering style
				'style' => isset( $settings['mastering_style'] ) ? $settings['mastering_style'] : 'balanced',

				// EQ adjustments
				'enhance_bass' => isset( $settings['enhance_bass'] ) ? (bool) $settings['enhance_bass'] : false,
				'enhance_treble' => isset( $settings['enhance_treble'] ) ? (bool) $settings['enhance_treble'] : false,

				// Compression
				'compression_amount' => isset( $settings['compression_amount'] ) ? $settings['compression_amount'] : 'medium',

				// Output format
				'output_format' => isset( $settings['output_format'] ) ? $settings['output_format'] : 'wav',
			),
		);

		return $this->make_request( 'POST', '/jobs', $body );
	}

	/**
	 * Submit stem separation job
	 *
	 * @param int    $job_id Job ID.
	 * @param string $input_url Input audio URL.
	 * @param object $job Job data.
	 * @return array|WP_Error Response or error.
	 */
	private function submit_stem_separation( $job_id, $input_url, $job ) {
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();

		$webhook_url = rest_url( 'teknup/v1/tonn/callback' );

		// Prepare stem separation parameters
		$body = array(
			'input_url' => $input_url,
			'webhook_url' => $webhook_url,
			'job_type' => 'stem_separation',
			'parameters' => array(
				// Number of stems (2-stem, 4-stem, 5-stem)
				'num_stems' => isset( $settings['num_stems'] ) ? (int) $settings['num_stems'] : 4,

				// Stem types: vocals, drums, bass, other, etc.
				'stem_types' => isset( $settings['stem_types'] ) ? $settings['stem_types'] : array( 'vocals', 'drums', 'bass', 'other' ),

				// Quality level
				'quality' => isset( $settings['quality'] ) ? $settings['quality'] : 'high',

				// Output format
				'output_format' => isset( $settings['output_format'] ) ? $settings['output_format'] : 'wav',
			),
		);

		return $this->make_request( 'POST', '/jobs', $body );
	}

	/**
	 * Check job status
	 *
	 * @param string $tonn_job_id Tonn job ID.
	 * @return array|WP_Error Status data or error.
	 */
	public function check_job_status( $tonn_job_id ) {
		return $this->make_request( 'GET', "/jobs/{$tonn_job_id}" );
	}

	/**
	 * Make API request
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body (optional).
	 * @param int    $retry_count Current retry count.
	 * @return array|WP_Error Response or error.
	 */
	private function make_request( $method, $endpoint, $body = array(), $retry_count = 0 ) {
		$url = $this->api_base_url . $endpoint;

		$args = array(
			'method' => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->api_token,
				'Content-Type' => 'application/json',
			),
			'timeout' => 60, // Longer timeout for audio processing
		);

		if ( ! empty( $body ) && $method !== 'GET' ) {
			$args['body'] = wp_json_encode( $body );
		}

		teknup_ai_mastering()->log( "Tonn API {$method} {$endpoint}", 'debug' );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( $retry_count < $this->max_retries ) {
				teknup_ai_mastering()->log( "Retry {$retry_count}/{$this->max_retries} for {$endpoint}", 'info' );
				sleep( pow( 2, $retry_count ) ); // Exponential backoff
				return $this->make_request( $method, $endpoint, $body, $retry_count + 1 );
			}

			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_data = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_data, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			$error_message = isset( $data['error'] ) ? $data['error'] : ( isset( $data['message'] ) ? $data['message'] : 'Tonn API error' );

			teknup_ai_mastering()->log(
				"Tonn API error {$status_code}: {$error_message} | Response: " . $body_data,
				'error'
			);

			// Retry on server errors
			if ( $status_code >= 500 && $retry_count < $this->max_retries ) {
				teknup_ai_mastering()->log( "Server error, retry {$retry_count}/{$this->max_retries}", 'info' );
				sleep( pow( 2, $retry_count ) );
				return $this->make_request( $method, $endpoint, $body, $retry_count + 1 );
			}

			return new \WP_Error( 'api_error', $error_message, array( 'status' => $status_code ) );
		}

		return $data;
	}

	/**
	 * Test API connection
	 *
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 */
	public function test_connection() {
		if ( empty( $this->api_token ) ) {
			return new \WP_Error( 'no_api_token', __( 'Tonn API token is not configured.', 'teknup-ai-mastering' ) );
		}

		// Test connection by checking account info or a simple endpoint
		$response = $this->make_request( 'GET', '/account' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}

	/**
	 * Handle webhook callback
	 *
	 * @param array $data Webhook data.
	 * @return bool Success status.
	 */
	public function handle_webhook( $data ) {
		if ( ! isset( $data['id'] ) || ! isset( $data['status'] ) ) {
			teknup_ai_mastering()->log( 'Invalid webhook data from Tonn', 'error' );
			return false;
		}

		$tonn_job_id = $data['id'];
		$status = $data['status'];

		// Find job by Tonn job ID
		global $wpdb;
		$table_name = $wpdb->prefix . TEKNUP_TABLE_NAME;

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE tonn_job_id = %s",
				$tonn_job_id
			),
			ARRAY_A
		);

		if ( ! $job ) {
			teknup_ai_mastering()->log( "Job not found for Tonn job ID: {$tonn_job_id}", 'error' );
			return false;
		}

		$job_id = $job['id'];

		teknup_ai_mastering()->log( "Webhook received for job {$job_id}, status: {$status}", 'info' );

		if ( $status === 'completed' || $status === 'success' ) {
			return $this->handle_success( $job_id, $data );
		} elseif ( $status === 'failed' || $status === 'error' ) {
			return $this->handle_failure( $job_id, $data );
		}

		return true;
	}

	/**
	 * Handle successful job
	 *
	 * @param int   $job_id Job ID.
	 * @param array $data Webhook data.
	 * @return bool Success status.
	 */
	private function handle_success( $job_id, $data ) {
		$output = isset( $data['output'] ) ? $data['output'] : null;

		if ( ! $output ) {
			teknup_ai_mastering()->log( "No output in webhook for job {$job_id}", 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', 'No output from Tonn' );
			return false;
		}

		$job = teknup_ai_mastering()->jobs->get_job( $job_id );
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();
		$job_type = isset( $settings['job_type'] ) ? $settings['job_type'] : 'mastering';

		// Handle different output types
		if ( $job_type === 'stem_separation' ) {
			// Output is an array of stem URLs
			return $this->save_stems( $job_id, $output );
		} else {
			// Output is a single mastered file URL or object with URL
			$output_url = is_array( $output ) ? ( isset( $output['url'] ) ? $output['url'] : ( $output[0] ?? null ) ) : $output;
			return $this->save_mastered_file( $job_id, $output_url );
		}
	}

	/**
	 * Save mastered file
	 *
	 * @param int    $job_id Job ID.
	 * @param string $output_url Output file URL.
	 * @return bool Success status.
	 */
	private function save_mastered_file( $job_id, $output_url ) {
		if ( empty( $output_url ) ) {
			teknup_ai_mastering()->log( "Empty output URL for job {$job_id}", 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', 'Empty output URL' );
			return false;
		}

		// Download and save the mastered file
		$result = teknup_ai_mastering()->storage->save_from_url( $output_url, $job_id, 'mastered' );

		if ( is_wp_error( $result ) ) {
			teknup_ai_mastering()->log( "Failed to save mastered file for job {$job_id}: " . $result->get_error_message(), 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', $result->get_error_message() );
			return false;
		}

		// Update job status
		teknup_ai_mastering()->jobs->update_status( $job_id, 'completed' );

		// Send notification email
		do_action( 'teknup_job_completed', $job_id );

		return true;
	}

	/**
	 * Save stems
	 *
	 * @param int   $job_id Job ID.
	 * @param array $stems Stem URLs.
	 * @return bool Success status.
	 */
	private function save_stems( $job_id, $stems ) {
		if ( empty( $stems ) || ! is_array( $stems ) ) {
			teknup_ai_mastering()->log( "Invalid stems data for job {$job_id}", 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', 'Invalid stems data' );
			return false;
		}

		$saved_stems = array();

		// Save each stem
		foreach ( $stems as $stem_name => $stem_data ) {
			// Handle different stem data formats
			$stem_url = is_array( $stem_data ) ? ( isset( $stem_data['url'] ) ? $stem_data['url'] : null ) : $stem_data;

			if ( empty( $stem_url ) ) {
				continue;
			}

			$result = teknup_ai_mastering()->storage->save_from_url( $stem_url, $job_id, $stem_name );

			if ( is_wp_error( $result ) ) {
				teknup_ai_mastering()->log( "Failed to save {$stem_name} stem for job {$job_id}: " . $result->get_error_message(), 'error' );
				continue;
			}

			$saved_stems[ $stem_name ] = $result;
		}

		if ( empty( $saved_stems ) ) {
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', 'Failed to save any stems' );
			return false;
		}

		// Update job with stems data
		teknup_ai_mastering()->database->update_job(
			$job_id,
			array(
				'status' => 'completed',
				'stems_data' => wp_json_encode( $saved_stems ),
			)
		);

		// Send notification email
		do_action( 'teknup_job_completed', $job_id );

		return true;
	}

	/**
	 * Handle failed job
	 *
	 * @param int   $job_id Job ID.
	 * @param array $data Webhook data.
	 * @return bool Success status.
	 */
	private function handle_failure( $job_id, $data ) {
		$error_message = isset( $data['error'] ) ? $data['error'] : ( isset( $data['message'] ) ? $data['message'] : 'Unknown error from Tonn' );

		teknup_ai_mastering()->log( "Job {$job_id} failed: {$error_message}", 'error' );

		teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', $error_message );

		return true;
	}
}
