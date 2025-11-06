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
	private $api_base_url = 'https://tonn.roexaudio.com';

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

		// Get job type from direct column (preferred) or fallback to settings JSON
		$job_type = ! empty( $job->job_type ) ? $job->job_type : 'mastering';

		// Debug: Log what we're reading from database
		teknup_ai_mastering()->log( "DEBUG submit_job({$job_id}): job_type from DB column = {$job_type}", 'info' );

		// Upload file to Tonn
		$uploaded_url = $this->upload_file_to_tonn( $job );

		if ( is_wp_error( $uploaded_url ) ) {
			return $uploaded_url;
		}

		teknup_ai_mastering()->log( "Submitting job {$job_id} to Tonn ({$job_type}) with audio: {$uploaded_url}", 'info' );

		// Submit based on job type
		if ( $job_type === 'stem_separation' ) {
			$response = $this->submit_mix_enhance_with_stems( $job_id, $uploaded_url, $job );
		} else {
			$response = $this->submit_mix_enhance( $job_id, $uploaded_url, $job );
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

		// Store Tonn task ID - Tonn returns 'mixrevive_task_id' with underscores
		$tonn_task_id = null;
		if ( isset( $response['mixrevive_task_id'] ) ) {
			$tonn_task_id = $response['mixrevive_task_id'];
		} elseif ( isset( $response['mixEnhanceTaskId'] ) ) {
			// Fallback to camelCase format just in case
			$tonn_task_id = $response['mixEnhanceTaskId'];
		} elseif ( isset( $response['mixReviveTaskId'] ) ) {
			$tonn_task_id = $response['mixReviveTaskId'];
		}

		if ( ! $tonn_task_id ) {
			teknup_ai_mastering()->log( 'No task ID in response: ' . json_encode( $response ), 'error' );
			return new \WP_Error( 'no_task_id', __( 'No task ID returned from Tonn.', 'teknup-ai-mastering' ) );
		}

		// Update job with Tonn task ID
		teknup_ai_mastering()->database->update_job(
			$job_id,
			array(
				'tonn_job_id' => $tonn_task_id,
				'status' => 'processing',
				'sent_to_tonn_at' => current_time( 'mysql' ),
			)
		);

		teknup_ai_mastering()->log( "Job {$job_id} submitted to Tonn with task ID: {$tonn_task_id}", 'info' );

		return true;
	}

	/**
	 * Upload file to Tonn storage
	 *
	 * @param object $job Job object.
	 * @return string|WP_Error Uploaded URL or error.
	 */
	private function upload_file_to_tonn( $job ) {
		// Get file path
		$file_path = $job->original_filepath;

		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'Audio file not found.', 'teknup-ai-mastering' ) );
		}

		// Get file info
		$filename = basename( $file_path );
		$file_ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		// Determine content type
		$content_types = array(
			'wav' => 'audio/wav',
			'mp3' => 'audio/mpeg',
			'flac' => 'audio/flac',
		);
		$content_type = isset( $content_types[ $file_ext ] ) ? $content_types[ $file_ext ] : 'audio/wav';

		// Step 1: Get upload URL
		$upload_data = $this->make_request(
			'POST',
			'/upload',
			array(
				'filename' => $filename,
				'contentType' => $content_type,
			)
		);

		if ( is_wp_error( $upload_data ) ) {
			return $upload_data;
		}

		if ( ! isset( $upload_data['signed_url'] ) || ! isset( $upload_data['readable_url'] ) ) {
			return new \WP_Error( 'invalid_upload_response', __( 'Invalid upload response from Tonn.', 'teknup-ai-mastering' ) );
		}

		// Step 2: Upload file to signed URL
		$file_contents = file_get_contents( $file_path );

		$upload_response = wp_remote_request(
			$upload_data['signed_url'],
			array(
				'method' => 'PUT',
				'headers' => array(
					'Content-Type' => $content_type,
				),
				'body' => $file_contents,
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $upload_response ) ) {
			return $upload_response;
		}

		$status_code = wp_remote_retrieve_response_code( $upload_response );

		if ( $status_code !== 200 ) {
			return new \WP_Error( 'upload_failed', __( 'Failed to upload file to Tonn.', 'teknup-ai-mastering' ) );
		}

		return $upload_data['readable_url'];
	}

	/**
	 * Map frontend genre to Tonn musicalStyle
	 * This plugin is specialized for techno music mastering.
	 *
	 * @param string $genre Genre from frontend.
	 * @return string Tonn musicalStyle.
	 */
	private function map_genre_to_musical_style( $genre ) {
		// All techno sub-genres map to ELECTRONIC for optimal processing
		$genre_map = array(
			'techno'             => 'ELECTRONIC',
			'minimal_techno'     => 'ELECTRONIC',
			'hard_techno'        => 'ELECTRONIC',
			'industrial_techno'  => 'ELECTRONIC',
			'melodic_techno'     => 'ELECTRONIC',
			'acid_techno'        => 'ELECTRONIC',
		);

		// Normalize genre to lowercase
		$normalized_genre = strtolower( trim( $genre ) );

		// Default to ELECTRONIC (techno) if genre is empty or not recognized
		return isset( $genre_map[ $normalized_genre ] ) ? $genre_map[ $normalized_genre ] : 'ELECTRONIC';
	}

	/**
	 * Map target LUFS to loudness preference
	 * Optimized for techno club music (-9 LUFS standard).
	 *
	 * @param float|null $target_lufs Target LUFS value.
	 * @return string Tonn loudnessPreference.
	 */
	private function map_lufs_to_loudness_preference( $target_lufs ) {
		// If no target specified, use club standard (CD_LOUDNESS) for techno
		if ( empty( $target_lufs ) ) {
			return 'CD_LOUDNESS';
		}

		// Convert LUFS to loudness preference
		// Club/Techno standard is around -9 LUFS (CD_LOUDNESS)
		// Streaming standard is around -14 LUFS (STREAMING_LOUDNESS)
		if ( $target_lufs >= -11 ) {
			return 'CD_LOUDNESS';
		} else {
			return 'STREAMING_LOUDNESS';
		}
	}

	/**
	 * Map intensity to mastering aggressiveness
	 * Optimized for techno club music - high intensity is recommended.
	 *
	 * @param string $intensity Intensity level (low, medium, high).
	 * @return array Array of fix settings based on intensity.
	 */
	private function map_intensity_to_settings( $intensity ) {
		$intensity = strtolower( trim( $intensity ) );

		switch ( $intensity ) {
			case 'low':
				// Minimal processing - only fix critical issues
				return array(
					'fixClippingIssues'      => true,
					'fixDRCIssues'           => false,
					'fixStereoWidthIssues'   => false,
					'fixTonalProfileIssues'  => false,
					'fixLoudnessIssues'      => true,
					'applyMastering'         => true,
				);
			case 'medium':
				// Balanced processing - fix most issues
				return array(
					'fixClippingIssues'      => true,
					'fixDRCIssues'           => true,
					'fixStereoWidthIssues'   => true,
					'fixTonalProfileIssues'  => true,
					'fixLoudnessIssues'      => true,
					'applyMastering'         => true,
				);
			case 'high':
			default:
				// Aggressive processing - fix everything (recommended for techno)
				return array(
					'fixClippingIssues'      => true,
					'fixDRCIssues'           => true,
					'fixStereoWidthIssues'   => true,
					'fixTonalProfileIssues'  => true,
					'fixLoudnessIssues'      => true,
					'applyMastering'         => true,
				);
		}
	}

	/**
	 * Submit mix revive job (mastering)
	 *
	 * @param int    $job_id Job ID.
	 * @param string $audio_url Uploaded audio URL.
	 * @param object $job Job data.
	 * @return array|WP_Error Response or error.
	 */
	private function submit_mix_enhance( $job_id, $audio_url, $job ) {
		$webhook_url = rest_url( 'teknup/v1/tonn/callback' );

		// Get user parameters from job
		$genre = ! empty( $job->genre ) ? $job->genre : '';
		$intensity = ! empty( $job->intensity ) ? $job->intensity : 'medium';
		$target_lufs = ! empty( $job->target_lufs ) ? (float) $job->target_lufs : null;

		// Map frontend parameters to Tonn API parameters
		$musical_style = $this->map_genre_to_musical_style( $genre );
		$loudness_preference = $this->map_lufs_to_loudness_preference( $target_lufs );
		$intensity_settings = $this->map_intensity_to_settings( $intensity );

		teknup_ai_mastering()->log( "Submitting PAID mastering job {$job_id} (no watermark, consumes credits) with user parameters: genre={$genre}, intensity={$intensity}, target_lufs={$target_lufs}", 'info' );
		teknup_ai_mastering()->log( "Mapped to Tonn parameters: musicalStyle={$musical_style}, loudnessPreference={$loudness_preference}", 'info' );
		teknup_ai_mastering()->log( "Webhook URL: {$webhook_url}", 'debug' );

		// Prepare mix revive parameters (mastering) for PAID mode
		$body = array(
			'mixReviveData' => array(
				'audioFileLocation'      => $audio_url,
				'musicalStyle'           => $musical_style,
				'isMaster'               => false, // Assume input is not mastered
				'fixClippingIssues'      => $intensity_settings['fixClippingIssues'],
				'fixDRCIssues'           => $intensity_settings['fixDRCIssues'],
				'fixStereoWidthIssues'   => $intensity_settings['fixStereoWidthIssues'],
				'fixTonalProfileIssues'  => $intensity_settings['fixTonalProfileIssues'],
				'fixLoudnessIssues'      => $intensity_settings['fixLoudnessIssues'],
				'applyMastering'         => $intensity_settings['applyMastering'],
				'loudnessPreference'     => $loudness_preference,
				'stemProcessing'         => false, // No stems for basic mastering
				'webhookURL'             => $webhook_url,
			),
		);

		return $this->make_request( 'POST', '/mixenhance', $body );
	}

	/**
	 * Submit mix revive with stem separation
	 *
	 * @param int    $job_id Job ID.
	 * @param string $audio_url Uploaded audio URL.
	 * @param object $job Job data.
	 * @return array|WP_Error Response or error.
	 */
	private function submit_mix_enhance_with_stems( $job_id, $audio_url, $job ) {
		$webhook_url = rest_url( 'teknup/v1/tonn/callback' );

		// Get user parameters from job
		$genre = ! empty( $job->genre ) ? $job->genre : '';
		$intensity = ! empty( $job->intensity ) ? $job->intensity : 'high';
		$target_lufs = ! empty( $job->target_lufs ) ? (float) $job->target_lufs : null;

		// Map frontend parameters to Tonn API parameters
		$musical_style = $this->map_genre_to_musical_style( $genre );
		$loudness_preference = $this->map_lufs_to_loudness_preference( $target_lufs );
		$intensity_settings = $this->map_intensity_to_settings( $intensity );

		teknup_ai_mastering()->log( "Submitting PAID stem separation job {$job_id} (consumes credits) with user parameters: genre={$genre}, intensity={$intensity}, target_lufs={$target_lufs}", 'info' );
		teknup_ai_mastering()->log( "Mapped to Tonn parameters: musicalStyle={$musical_style}, loudnessPreference={$loudness_preference}", 'info' );
		teknup_ai_mastering()->log( "Webhook URL: {$webhook_url}", 'debug' );

		// Mix Revive + Stem Processing (PAID mode - required for stem separation)
		$body = array(
			'mixReviveData' => array(
				'audioFileLocation'      => $audio_url,
				'musicalStyle'           => $musical_style,
				'isMaster'               => false, // Assume input is not mastered
				'fixClippingIssues'      => $intensity_settings['fixClippingIssues'],
				'fixDRCIssues'           => $intensity_settings['fixDRCIssues'],
				'fixStereoWidthIssues'   => $intensity_settings['fixStereoWidthIssues'],
				'fixTonalProfileIssues'  => $intensity_settings['fixTonalProfileIssues'],
				'fixLoudnessIssues'      => $intensity_settings['fixLoudnessIssues'],
				'applyMastering'         => $intensity_settings['applyMastering'],
				'loudnessPreference'     => $loudness_preference,
				'stemProcessing'         => true, // Enable stem separation
				'getProcessedStems'      => true, // Get processed stems
				'webhookURL'             => $webhook_url,
			),
		);

		return $this->make_request( 'POST', '/mixenhance', $body );
	}

	/**
	 * Check job status
	 *
	 * @param string $tonn_task_id Tonn task ID.
	 * @return array|WP_Error Status data or error.
	 */
	public function check_job_status( $tonn_task_id ) {
		// Retrieve mix enhance results
		return $this->make_request(
			'POST',
			'/retrieveenhancedtrack',
			array(
				'mixReviveData' => array(
					'mixReviveTaskId' => $tonn_task_id,
				),
			)
		);
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
				'X-API-Key' => $this->api_token,
				'Content-Type' => 'application/json',
			),
			'timeout' => 60,
		);

		if ( ! empty( $body ) && $method !== 'GET' ) {
			$args['body'] = wp_json_encode( $body );
		}

		teknup_ai_mastering()->log( "Tonn API {$method} {$endpoint}", 'debug' );

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			if ( $retry_count < $this->max_retries ) {
				teknup_ai_mastering()->log( "Retry {$retry_count}/{$this->max_retries} for {$endpoint}", 'info' );
				sleep( pow( 2, $retry_count ) );
				return $this->make_request( $method, $endpoint, $body, $retry_count + 1 );
			}

			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_data = wp_remote_retrieve_body( $response );
		$data = json_decode( $body_data, true );

		if ( $status_code < 200 || $status_code >= 300 ) {
			// Get error message - prioritize 'message' field over 'error' boolean
			$error_message = isset( $data['message'] ) ? $data['message'] : ( isset( $data['error'] ) && is_string( $data['error'] ) ? $data['error'] : 'Tonn API error' );

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

		teknup_ai_mastering()->log( 'Testing Tonn API connection with token: ' . substr( $this->api_token, 0, 8 ) . '...', 'debug' );

		// Test connection by attempting to get upload URL for a dummy file
		$response = $this->make_request(
			'POST',
			'/upload',
			array(
				'filename' => 'test.wav',
				'contentType' => 'audio/wav',
			)
		);

		if ( is_wp_error( $response ) ) {
			$error_code = $response->get_error_code();
			$error_message = $response->get_error_message();
			$error_data = $response->get_error_data();

			teknup_ai_mastering()->log(
				sprintf(
					'API connection test failed. Code: %s, Message: %s, Status: %s',
					$error_code,
					$error_message,
					isset( $error_data['status'] ) ? $error_data['status'] : 'N/A'
				),
				'error'
			);

			// Provide more specific error messages based on status code
			if ( isset( $error_data['status'] ) ) {
				$status = $error_data['status'];

				if ( $status === 401 ) {
					return new \WP_Error(
						'invalid_api_key',
						__( 'Invalid API key. Please check that your API key is correct and active.', 'teknup-ai-mastering' )
					);
				} elseif ( $status === 403 ) {
					return new \WP_Error(
						'forbidden',
						__( 'Access forbidden. Your API key may not have the required permissions.', 'teknup-ai-mastering' )
					);
				} elseif ( $status === 429 ) {
					return new \WP_Error(
						'rate_limit',
						__( 'Rate limit exceeded. Please try again later.', 'teknup-ai-mastering' )
					);
				} elseif ( $status >= 500 ) {
					return new \WP_Error(
						'server_error',
						__( 'Tonn API server error. Please try again later.', 'teknup-ai-mastering' )
					);
				}
			}

			return $response;
		}

		teknup_ai_mastering()->log( 'API connection test successful', 'debug' );
		return true;
	}

	/**
	 * Handle webhook callback
	 *
	 * @param array $data Webhook data.
	 * @return bool Success status.
	 */
	public function handle_webhook( $data ) {
		teknup_ai_mastering()->log( 'Processing Tonn webhook: ' . json_encode( $data ), 'debug' );
		teknup_ai_mastering()->log( '=== WEBHOOK v2.1.2 ACTIVE - handle_webhook() called ===', 'info' );

		// Tonn webhook format uses 'mixrevive_task_id' (with underscores) and 'state'
		$tonn_task_id = isset( $data['mixrevive_task_id'] ) ? $data['mixrevive_task_id'] : null;
		$state = isset( $data['state'] ) ? $data['state'] : null;

		teknup_ai_mastering()->log( "Task ID: {$tonn_task_id} | State: {$state}", 'info' );

		if ( ! $tonn_task_id || ! $state ) {
			teknup_ai_mastering()->log( 'Invalid webhook data from Tonn - missing required fields', 'error' );
			return false;
		}

		teknup_ai_mastering()->log( 'Validation passed, searching database for job...', 'debug' );

		// Find job by Tonn task ID
		global $wpdb;
		$table_name = $wpdb->prefix . 'teknup_jobs';

		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE tonn_job_id = %s",
				$tonn_task_id
			),
			ARRAY_A
		);

		if ( ! $job ) {
			// Job not found - this is OK during webhook testing by Tonn API
			// Return true (200 OK) so Tonn accepts the webhook URL
			teknup_ai_mastering()->log( "Job not found for Tonn task ID: {$tonn_task_id} (webhook test or timing issue)", 'debug' );
			return true;
		}

		$job_id = $job['id'];

		teknup_ai_mastering()->log( "Webhook received for job {$job_id}, state: {$state}", 'info' );

		// Check state and handle accordingly
		// States: MIXREVIVE_TASK_STARTED, MIXREVIVE_TASK_SEPARATION, MIXREVIVE_TASK_MIX_MASTER_ENHANCEMENT,
		//         MIXREVIVE_TASK_MIX_MASTER_CORRECTION, MIXREVIVE_TASK_PREVIEW_COMPLETED,
		//         MIXREVIVE_TASK_COMPLETED, MIXREVIVE_TASK_FAILED

		if ( $state === 'MIXREVIVE_TASK_COMPLETED' || $state === 'COMPLETED' ) {
			// Task completed - full download_url_revived should be available
			return $this->handle_success( $job_id, $data );
		} elseif ( $state === 'MIXREVIVE_TASK_PREVIEW_COMPLETED' ) {
			// PREVIEW_COMPLETED webhook received - now retrieve the FINAL paid version
			// For paid mode, we must call /retrieveenhancedtrack to get download_url_revived (no watermark)
			// This is separate from the preview (download_url_preview_revived)
			teknup_ai_mastering()->log( "Preview completed for job {$job_id}, retrieving FINAL paid version...", 'info' );

			$tonn_task_id = isset( $data['mixrevive_task_id'] ) ? $data['mixrevive_task_id'] : null;
			if ( ! $tonn_task_id ) {
				teknup_ai_mastering()->log( "No task ID in PREVIEW_COMPLETED webhook for job {$job_id}", 'error' );
				return true;
			}

			// Call /retrieveenhancedtrack to get the final paid version with stems
			$final_data = $this->make_request(
				'POST',
				'/retrieveenhancedtrack',
				array(
					'mixReviveData' => array(
						'mixReviveTaskId' => $tonn_task_id,
					),
				)
			);

			if ( is_wp_error( $final_data ) ) {
				teknup_ai_mastering()->log( "Failed to retrieve final version for job {$job_id}: " . $final_data->get_error_message(), 'error' );
				return true;
			}

			// Merge the results data into final_data for processing
			if ( isset( $final_data['revivedTrackTaskResults'] ) ) {
				$final_results = array_merge( $data, $final_data['revivedTrackTaskResults'] );
				$final_results['mixrevive_task_id'] = $tonn_task_id; // Ensure task ID is preserved
				return $this->handle_success( $job_id, $final_results );
			}

			teknup_ai_mastering()->log( "No revivedTrackTaskResults in final version response for job {$job_id}", 'error' );
			return true;

			// OLD CODE below (never executed) - kept for reference:
			// Get job details to check job type
			$job_obj = teknup_ai_mastering()->jobs->get_job( $job_id );
			$settings = ! empty( $job_obj->settings ) ? json_decode( $job_obj->settings, true ) : array();
			$job_type = isset( $settings['job_type'] ) ? $settings['job_type'] : 'mastering';

			// For stem_separation jobs, we need the COMPLETED state with stems, not preview
			if ( $job_type === 'stem_separation' ) {
				teknup_ai_mastering()->log( "Preview completed for stem separation job {$job_id}, waiting for COMPLETED state with stems...", 'info' );
				teknup_ai_mastering()->jobs->update_status( $job_id, 'processing', array() );
				return true;
			}

			// For mastering jobs, use preview file with watermark (free mode)
			teknup_ai_mastering()->log( "Preview completed for mastering job {$job_id}, using preview with watermark (free mode)", 'info' );

			// Check if preview download URL is available
			if ( isset( $data['download_url_preview_revived'] ) && ! empty( $data['download_url_preview_revived'] ) ) {
				teknup_ai_mastering()->log( "Preview track with watermark available for job {$job_id}", 'info' );
				return $this->handle_success( $job_id, $data );
			}

			// Preview URL not available yet
			teknup_ai_mastering()->log( "Preview URL not available yet for job {$job_id}", 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'processing', array() );
			return true;
		} elseif ( $state === 'MIXREVIVE_TASK_FAILED' || $state === 'FAILED' || $state === 'ERROR' ) {
			return $this->handle_failure( $job_id, $data );
		} elseif ( $state === 'MIXREVIVE_TASK_STARTED' ) {
			// Update job status to processing
			teknup_ai_mastering()->jobs->update_status( $job_id, 'processing', array() );
		}

		// For intermediate states (SEPARATION, ENHANCEMENT, CORRECTION), just log and continue
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
		// Get download URL - check for multiple possible field names:
		// - 'download_url_revived' for full /mixenhance jobs
		// - 'download_url_preview_revived' for preview jobs
		// - 'download_url_preview_revived_matched' for matched loudness
		$download_url = null;

		if ( isset( $data['download_url_revived'] ) && ! empty( $data['download_url_revived'] ) ) {
			$download_url = $data['download_url_revived'];
		} elseif ( isset( $data['download_url_preview_revived'] ) && ! empty( $data['download_url_preview_revived'] ) ) {
			$download_url = $data['download_url_preview_revived'];
		} elseif ( isset( $data['download_url_preview_revived_matched'] ) && ! empty( $data['download_url_preview_revived_matched'] ) ) {
			$download_url = $data['download_url_preview_revived_matched'];
		}

		if ( ! $download_url ) {
			teknup_ai_mastering()->log( "No download URL in webhook for job {$job_id}. Data: " . json_encode( $data ), 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', 'No download URL from Tonn' );
			return false;
		}

		$job = teknup_ai_mastering()->jobs->get_job( $job_id );
		$job_type = ! empty( $job->job_type ) ? $job->job_type : 'mastering';

		// Handle stems if they were requested (Tonn format with underscores)
		$stems = null;
		if ( isset( $data['stems_download_urls'] ) && ! empty( $data['stems_download_urls'] ) ) {
			$stems = $data['stems_download_urls'];
		}

		if ( $job_type === 'stem_separation' && $stems ) {
			return $this->save_stems( $job_id, $stems, $download_url );
		} else {
			// Save mastered file
			return $this->save_mastered_file( $job_id, $download_url );
		}
	}

	/**
	 * Save mastered file
	 *
	 * @param int    $job_id Job ID.
	 * @param string $download_url Download URL.
	 * @return bool Success status.
	 */
	private function save_mastered_file( $job_id, $download_url ) {
		if ( empty( $download_url ) ) {
			teknup_ai_mastering()->log( "Empty download URL for job {$job_id}", 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', array( 'error_message' => 'Empty download URL' ) );
			return false;
		}

		teknup_ai_mastering()->log( "Processing completed job {$job_id}, downloading file from {$download_url}", 'info' );

		// Download and save the mastered file
		$result = teknup_ai_mastering()->storage->save_from_url( $download_url, $job_id );

		if ( is_wp_error( $result ) ) {
			teknup_ai_mastering()->log( "Failed to save mastered file for job {$job_id}: " . $result->get_error_message(), 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', array( 'error_message' => $result->get_error_message() ) );
			return false;
		}

		teknup_ai_mastering()->log( "Updating job {$job_id} with filepath: {$result}", 'debug' );

		// Update job status with file path (mastered_filename field doesn't exist in DB schema)
		// Note: update_status() will trigger do_action('teknup_job_completed') automatically
		teknup_ai_mastering()->jobs->update_status(
			$job_id,
			'completed',
			array(
				'mastered_filepath' => $result,
			)
		);

		return true;
	}

	/**
	 * Save stems and full mix
	 *
	 * @param int    $job_id Job ID.
	 * @param array  $stems Stem URLs.
	 * @param string $full_mix_url Full mix URL.
	 * @return bool Success status.
	 */
	private function save_stems( $job_id, $stems, $full_mix_url ) {
		if ( empty( $stems ) || ! is_array( $stems ) ) {
			teknup_ai_mastering()->log( "Invalid stems data for job {$job_id}", 'error' );
			teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', 'Invalid stems data' );
			return false;
		}

		$saved_stems = array();

		// Save full mix first
		if ( ! empty( $full_mix_url ) ) {
			$result = teknup_ai_mastering()->storage->save_from_url( $full_mix_url, $job_id, 'full_mix' );
			if ( ! is_wp_error( $result ) ) {
				$saved_stems['full_mix'] = $result;
			}
		}

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
		$error_message = isset( $data['error'] ) ? $data['error'] : ( isset( $data['message'] ) ? $data['message'] : 'Unknown error from Tonn' );

		teknup_ai_mastering()->log( "Job {$job_id} failed: {$error_message}", 'error' );

		teknup_ai_mastering()->jobs->update_status( $job_id, 'failed', $error_message );

		return true;
	}
}
