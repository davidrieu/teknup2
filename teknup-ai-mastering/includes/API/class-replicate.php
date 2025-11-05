<?php
/**
 * Replicate API class - Handles Replicate.com API integration for mastering and stem separation
 *
 * @package Teknup\API
 */

namespace Teknup\API;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Replicate API class
 */
class Replicate {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base_url = 'https://api.replicate.com/v1';

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
	 * Mastering model
	 *
	 * @var string
	 */
	private $mastering_model = 'resemble-ai/resemble-enhance';

	/**
	 * Stem separation model
	 *
	 * @var string
	 */
	private $stem_model = 'cjwbw/demucs';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->api_token = teknup_ai_mastering()->get_setting( 'replicate_api_token', '' );
	}

	/**
	 * Submit a job to Replicate
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
			return new \WP_Error( 'no_api_token', __( 'Replicate API token is not configured.', 'teknup-ai-mastering' ) );
		}

		// Generate temporary URL for Replicate to download the file
		$input_url = teknup_ai_mastering()->storage->generate_temp_url( $job_id );

		if ( is_wp_error( $input_url ) ) {
			return $input_url;
		}

		// Determine job type from settings
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();
		$job_type = isset( $settings['job_type'] ) ? $settings['job_type'] : 'mastering';

		teknup_ai_mastering()->log( "Submitting job {$job_id} to Replicate ({$job_type}) with audio: {$input_url}", 'info' );

		// Submit based on job type
		if ( $job_type === 'stem_separation' ) {
			$response = $this->submit_stem_separation( $job_id, $input_url, $job );
		} else {
			$response = $this->submit_mastering( $job_id, $input_url, $job );
		}

		if ( is_wp_error( $response ) ) {
			teknup_ai_mastering()->log( "Failed to submit job {$job_id} to Replicate: " . $response->get_error_message(), 'error' );

			// Update job status
			teknup_ai_mastering()->jobs->update_status(
				$job_id,
				'failed',
				$response->get_error_message()
			);

			return $response;
		}

		// Store prediction ID
		$prediction_id = isset( $response['id'] ) ? $response['id'] : null;

		if ( ! $prediction_id ) {
			return new \WP_Error( 'no_prediction_id', __( 'No prediction ID returned from Replicate.', 'teknup-ai-mastering' ) );
		}

		// Update job with prediction ID
		teknup_ai_mastering()->database->update_job(
			$job_id,
			array(
				'replicate_prediction_id' => $prediction_id,
				'status' => 'processing',
			)
		);

		teknup_ai_mastering()->log( "Job {$job_id} submitted to Replicate with prediction ID: {$prediction_id}", 'info' );

		return true;
	}

	/**
	 * Submit mastering job
	 *
	 * @param int    $job_id Job ID.
	 * @param string $input_url Input audio URL.
	 * @param array  $job Job data.
	 * @return array|WP_Error Response or error.
	 */
	private function submit_mastering( $job_id, $input_url, $job ) {
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();

		// Get model version
		$version = $this->get_model_version( $this->mastering_model );
		if ( ! $version ) {
			return new \WP_Error(
				'model_version_not_found',
				__( 'Could not retrieve model version from Replicate. Please check your API token and try again.', 'teknup-ai-mastering' )
			);
		}

		// Prepare resemble-enhance parameters
		$input = array(
			'audio' => $input_url,
			'solver' => isset( $settings['solver'] ) ? $settings['solver'] : 'Midpoint',
			'nfe' => isset( $settings['nfe'] ) ? (int) $settings['nfe'] : 64,
			'tau' => isset( $settings['tau'] ) ? (float) $settings['tau'] : 0.5,
			'denoising' => isset( $settings['denoising'] ) ? (bool) $settings['denoising'] : true,
			'chunk_seconds' => isset( $settings['chunk_seconds'] ) ? (int) $settings['chunk_seconds'] : 10,
			'chunks_overlap' => isset( $settings['chunks_overlap'] ) ? (int) $settings['chunks_overlap'] : 1,
		);

		$webhook_url = rest_url( 'teknup/v1/replicate/callback' );

		teknup_ai_mastering()->log( "Webhook URL: {$webhook_url}", 'debug' );

		$body = array(
			'version' => $version,
			'input' => $input,
			'webhook' => $webhook_url,
			'webhook_events_filter' => array( 'completed' ),
		);

		// Use correct Replicate predictions endpoint
		return $this->make_request( 'POST', '/predictions', $body );
	}

	/**
	 * Submit stem separation job
	 *
	 * @param int    $job_id Job ID.
	 * @param string $input_url Input audio URL.
	 * @param array  $job Job data.
	 * @return array|WP_Error Response or error.
	 */
	private function submit_stem_separation( $job_id, $input_url, $job ) {
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();

		// Get model version
		$version = $this->get_model_version( $this->stem_model );
		if ( ! $version ) {
			return new \WP_Error(
				'model_version_not_found',
				__( 'Could not retrieve stem separation model version from Replicate. Please check your API token and try again.', 'teknup-ai-mastering' )
			);
		}

		// Prepare stem separation parameters
		$input = array(
			'audio' => $input_url,
		);

		// Optional: model name (htdemucs, htdemucs_ft, htdemucs_6s, hdemucs_mmi)
		if ( isset( $settings['stem_model'] ) ) {
			$input['model_name'] = $settings['stem_model'];
		}

		// Optional: shifts for equivariant stabilization
		if ( isset( $settings['shifts'] ) ) {
			$input['shifts'] = (int) $settings['shifts'];
		}

		// Optional: mp3 bitrate
		if ( isset( $settings['mp3_bitrate'] ) ) {
			$input['mp3_bitrate'] = (int) $settings['mp3_bitrate'];
		}

		$webhook_url = rest_url( 'teknup/v1/replicate/callback' );

		$body = array(
			'version' => $version,
			'input' => $input,
			'webhook' => $webhook_url,
			'webhook_events_filter' => array( 'completed' ),
		);

		// Use correct Replicate predictions endpoint
		return $this->make_request( 'POST', '/predictions', $body );
	}

	/**
	 * Check job status
	 *
	 * @param string $prediction_id Prediction ID.
	 * @return array|WP_Error Status data or error.
	 */
	public function check_job_status( $prediction_id ) {
		return $this->make_request( 'GET', "/predictions/{$prediction_id}" );
	}

	/**
	 * Get model version
	 *
	 * @param string $model_name Model name.
	 * @return string|null Model version hash.
	 */
	private function get_model_version( $model_name ) {
		// Get latest version from Replicate API
		teknup_ai_mastering()->log( "Fetching model version for: {$model_name}", 'debug' );

		$response = $this->make_request( 'GET', "/models/{$model_name}" );

		if ( is_wp_error( $response ) ) {
			teknup_ai_mastering()->log(
				"Failed to get model version for {$model_name}: " . $response->get_error_message(),
				'error'
			);
			return null;
		}

		$version = isset( $response['latest_version']['id'] ) ? $response['latest_version']['id'] : null;

		if ( $version ) {
			teknup_ai_mastering()->log( "Model {$model_name} version: {$version}", 'debug' );
		} else {
			teknup_ai_mastering()->log( "No version found for model {$model_name}", 'error' );
		}

		return $version;
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
			'timeout' => 30,
		);

		if ( ! empty( $body ) && $method !== 'GET' ) {
			$args['body'] = wp_json_encode( $body );
		}

		teknup_ai_mastering()->log( "Replicate API {$method} {$endpoint}", 'debug' );

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
			$error_message = isset( $data['detail'] ) ? $data['detail'] : 'Replicate API error';

			teknup_ai_mastering()->log(
				"Replicate API error {$status_code}: {$error_message} | Response: " . $body_data,
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
			return new \WP_Error( 'no_api_token', __( 'Replicate API token is not configured.', 'teknup-ai-mastering' ) );
		}

		// Try to get model info to verify API token
		$response = $this->make_request( 'GET', '/models/' . $this->mastering_model );

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
			teknup_ai_mastering()->log( 'Invalid webhook data from Replicate', 'error' );
			return false;
		}

		$prediction_id = $data['id'];
		$status = $data['status'];

		// Find job by prediction ID
		global $wpdb;
		$table_name = $wpdb->prefix . TEKNUP_TABLE_NAME;

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE replicate_prediction_id = %s",
				$prediction_id
			),
			ARRAY_A
		);

		if ( ! $job ) {
			teknup_ai_mastering()->log( "Job not found for prediction ID: {$prediction_id}", 'error' );
			return false;
		}

		$job_id = $job['id'];

		teknup_ai_mastering()->log( "Webhook received for job {$job_id}, status: {$status}", 'info' );

		if ( $status === 'succeeded' ) {
			return $this->handle_success( $job_id, $data );
		} elseif ( $status === 'failed' ) {
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
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', 'No output from Replicate' );
			return false;
		}

		$job = teknup_ai_mastering()->jobs->get_job( $job_id );
		$settings = ! empty( $job->settings ) ? json_decode( $job->settings, true ) : array();
		$job_type = isset( $settings['job_type'] ) ? $settings['job_type'] : 'mastering';

		// Handle different output types
		if ( $job_type === 'stem_separation' ) {
			// Output is an array of stem URLs: {drums, bass, vocals, other}
			return $this->save_stems( $job_id, $output );
		} else {
			// Output is a single mastered file URL
			$output_url = is_array( $output ) ? ( $output[0] ?? null ) : $output;
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
		foreach ( $stems as $stem_name => $stem_url ) {
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
		$error_message = isset( $data['error'] ) ? $data['error'] : 'Unknown error from Replicate';

		teknup_ai_mastering()->log( "Job {$job_id} failed: {$error_message}", 'error' );

		teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', $error_message );

		return true;
	}
}
