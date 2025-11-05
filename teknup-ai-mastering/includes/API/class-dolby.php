<?php
/**
 * Dolby API class - Handles Dolby.io Media Enhancement API integration
 *
 * @package Teknup\API
 */

namespace Teknup\API;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dolby API class
 */
class Dolby {

	/**
	 * API base URL
	 *
	 * @var string
	 */
	private $api_base_url = 'https://api.dolby.io/media/enhance';

	/**
	 * API key
	 *
	 * @var string
	 */
	private $api_key;

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
		$this->api_key = teknup_ai_mastering()->get_setting( 'dolby_api_key', '' );
	}

	/**
	 * Submit a mastering job to Dolby
	 *
	 * @param int $job_id Job ID.
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 */
	public function submit_job( $job_id ) {
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job ) {
			return new \WP_Error( 'job_not_found', __( 'Job not found.', 'teknup-ai-mastering' ) );
		}

		// Check API key
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'Dolby API key is not configured.', 'teknup-ai-mastering' ) );
		}

		// Generate temporary URL for Dolby to download the file
		$input_url = teknup_ai_mastering()->storage->generate_temp_url( $job_id );

		if ( is_wp_error( $input_url ) ) {
			return $input_url;
		}

		// Prepare output URL (webhook callback)
		$output_url = rest_url( 'teknup/v1/dolby/callback' );

		// Prepare mastering parameters
		$params = $this->prepare_mastering_params( $job );

		// Build request body
		$body = array(
			'input' => $input_url,
			'output' => $output_url,
			'content' => array(
				'type' => 'music',
			),
			'audio' => $params,
		);

		teknup_ai_mastering()->log( "Submitting job {$job_id} to Dolby with params: " . json_encode( $body ), 'info' );

		// Make API request with retry logic
		$response = $this->make_request( 'POST', '/music/master', $body );

		if ( is_wp_error( $response ) ) {
			teknup_ai_mastering()->log( "Failed to submit job {$job_id} to Dolby: " . $response->get_error_message(), 'error' );

			// Update job status
			teknup_ai_mastering()->jobs->update_status(
				$job_id,
				'failed',
				array( 'error_message' => $response->get_error_message() )
			);

			return $response;
		}

		// Extract Dolby job ID
		$dolby_job_id = isset( $response['job_id'] ) ? $response['job_id'] : null;

		if ( ! $dolby_job_id ) {
			teknup_ai_mastering()->log( "No job ID returned from Dolby for job {$job_id}", 'error' );

			teknup_ai_mastering()->jobs->update_status(
				$job_id,
				'failed',
				array( 'error_message' => __( 'No job ID returned from Dolby.', 'teknup-ai-mastering' ) )
			);

			return new \WP_Error( 'no_dolby_job_id', __( 'No job ID returned from Dolby.', 'teknup-ai-mastering' ) );
		}

		// Update job with Dolby job ID and status
		teknup_ai_mastering()->jobs->update_status(
			$job_id,
			'sent_to_dolby',
			array(
				'dolby_job_id' => $dolby_job_id,
				'dolby_response' => json_encode( $response ),
			)
		);

		teknup_ai_mastering()->log( "Job {$job_id} submitted to Dolby successfully. Dolby job ID: {$dolby_job_id}", 'info' );

		return true;
	}

	/**
	 * Prepare mastering parameters
	 *
	 * @param object $job Job object.
	 * @return array Mastering parameters.
	 */
	private function prepare_mastering_params( $job ) {
		$params = array(
			'mastering' => array(
				'enable' => true,
			),
			'loudness' => array(
				'enable' => true,
			),
			'dynamics' => array(
				'range_control' => array(
					'enable' => true,
				),
			),
			'noise' => array(
				'reduction' => array(
					'enable' => true,
					'amount' => 'auto',
				),
			),
		);

		// Set intensity
		if ( ! empty( $job->intensity ) ) {
			$intensity_map = array(
				'light' => 'low',
				'medium' => 'medium',
				'heavy' => 'high',
			);
			$params['mastering']['preset'] = isset( $intensity_map[ $job->intensity ] )
				? $intensity_map[ $job->intensity ]
				: 'medium';
		}

		// Set target LUFS if specified
		if ( ! empty( $job->target_lufs ) ) {
			$params['loudness']['target_level'] = (float) $job->target_lufs;
		} else {
			// Default LUFS
			$params['loudness']['target_level'] = (float) teknup_ai_mastering()->get_setting( 'default_lufs', -14.0 );
		}

		// Add genre-specific settings if available
		if ( ! empty( $job->genre ) ) {
			$params['mastering']['style'] = $this->get_genre_style( $job->genre );
		}

		return $params;
	}

	/**
	 * Get genre style for mastering
	 *
	 * @param string $genre Genre name.
	 * @return string Style parameter.
	 */
	private function get_genre_style( $genre ) {
		$genre_map = array(
			'techno' => 'electronic',
			'house' => 'electronic',
			'trance' => 'electronic',
			'dubstep' => 'electronic',
			'drum_and_bass' => 'electronic',
			'ambient' => 'electronic',
			'rock' => 'rock',
			'pop' => 'pop',
			'hip_hop' => 'hip_hop',
			'jazz' => 'jazz',
			'classical' => 'classical',
		);

		return isset( $genre_map[ $genre ] ) ? $genre_map[ $genre ] : 'electronic';
	}

	/**
	 * Check job status with Dolby
	 *
	 * @param string $dolby_job_id Dolby job ID.
	 * @return array|WP_Error Job status or WP_Error on failure.
	 */
	public function check_job_status( $dolby_job_id ) {
		$response = $this->make_request( 'GET', '/music/master/' . $dolby_job_id );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $response;
	}

	/**
	 * Handle job status update from Dolby
	 *
	 * @param int   $job_id Job ID.
	 * @param array $dolby_status Dolby status response.
	 */
	public function handle_job_status_update( $job_id, $dolby_status ) {
		if ( ! isset( $dolby_status['status'] ) ) {
			return;
		}

		$status = $dolby_status['status'];

		switch ( $status ) {
			case 'Pending':
			case 'Running':
				teknup_ai_mastering()->jobs->update_status( $job_id, 'processing' );
				break;

			case 'Success':
				$this->handle_job_success( $job_id, $dolby_status );
				break;

			case 'Failed':
			case 'InternalError':
				$error_message = isset( $dolby_status['error'] )
					? $dolby_status['error']
					: __( 'Unknown error from Dolby.', 'teknup-ai-mastering' );

				teknup_ai_mastering()->jobs->update_status(
					$job_id,
					'failed',
					array( 'error_message' => $error_message )
				);
				break;
		}
	}

	/**
	 * Handle successful job completion
	 *
	 * @param int   $job_id Job ID.
	 * @param array $dolby_status Dolby status response.
	 */
	private function handle_job_success( $job_id, $dolby_status ) {
		// Get output URL
		$output_url = isset( $dolby_status['output'] ) ? $dolby_status['output'] : null;

		if ( ! $output_url ) {
			teknup_ai_mastering()->jobs->update_status(
				$job_id,
				'failed',
				array( 'error_message' => __( 'No output URL from Dolby.', 'teknup-ai-mastering' ) )
			);
			return;
		}

		// Download the mastered file
		$filepath = teknup_ai_mastering()->storage->save_from_url( $output_url, $job_id );

		if ( is_wp_error( $filepath ) ) {
			teknup_ai_mastering()->jobs->update_status(
				$job_id,
				'failed',
				array( 'error_message' => $filepath->get_error_message() )
			);
			return;
		}

		// Update job as completed
		teknup_ai_mastering()->jobs->update_status(
			$job_id,
			'completed',
			array(
				'mastered_filepath' => $filepath,
				'dolby_response' => json_encode( $dolby_status ),
			)
		);

		// Increment user usage counter
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );
		if ( $job ) {
			teknup_ai_mastering()->subscriptions->increment_usage( $job->user_id );
		}

		teknup_ai_mastering()->log( "Job {$job_id} completed successfully", 'info' );
	}

	/**
	 * Make API request to Dolby
	 *
	 * @param string $method HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $body Request body.
	 * @param int    $retry_count Current retry attempt.
	 * @return array|WP_Error Response or WP_Error on failure.
	 */
	private function make_request( $method, $endpoint, $body = null, $retry_count = 0 ) {
		$url = $this->api_base_url . $endpoint;

		$args = array(
			'method' => $method,
			'headers' => array(
				'x-api-key' => $this->api_key,
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
			),
			'timeout' => 30,
		);

		if ( $body ) {
			$args['body'] = json_encode( $body );
		}

		teknup_ai_mastering()->log( "Making {$method} request to {$url}", 'info' );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			// Retry logic
			if ( $retry_count < $this->max_retries ) {
				teknup_ai_mastering()->log( "Request failed, retrying... (attempt " . ( $retry_count + 1 ) . "/{$this->max_retries})", 'warning' );
				sleep( pow( 2, $retry_count ) ); // Exponential backoff
				return $this->make_request( $method, $endpoint, $body, $retry_count + 1 );
			}

			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		teknup_ai_mastering()->log( "Response status: {$status_code}, body: {$response_body}", 'info' );

		if ( $status_code >= 400 ) {
			$error_message = $this->parse_error_response( $response_body, $status_code );

			// Retry on server errors
			if ( $status_code >= 500 && $retry_count < $this->max_retries ) {
				teknup_ai_mastering()->log( "Server error, retrying... (attempt " . ( $retry_count + 1 ) . "/{$this->max_retries})", 'warning' );
				sleep( pow( 2, $retry_count ) );
				return $this->make_request( $method, $endpoint, $body, $retry_count + 1 );
			}

			return new \WP_Error( 'api_error', $error_message );
		}

		$data = json_decode( $response_body, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error( 'json_error', __( 'Failed to parse API response.', 'teknup-ai-mastering' ) );
		}

		return $data;
	}

	/**
	 * Parse error response
	 *
	 * @param string $response_body Response body.
	 * @param int    $status_code Status code.
	 * @return string Error message.
	 */
	private function parse_error_response( $response_body, $status_code ) {
		$data = json_decode( $response_body, true );

		if ( isset( $data['error'] ) ) {
			return $data['error'];
		}

		if ( isset( $data['message'] ) ) {
			return $data['message'];
		}

		return sprintf( __( 'API request failed with status code %d', 'teknup-ai-mastering' ), $status_code );
	}

	/**
	 * Test API connection
	 *
	 * @return bool|WP_Error True on success or WP_Error on failure.
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error( 'no_api_key', __( 'API key is required.', 'teknup-ai-mastering' ) );
		}

		// Make a simple request to test the connection
		$response = $this->make_request( 'GET', '/music/master' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return true;
	}
}
