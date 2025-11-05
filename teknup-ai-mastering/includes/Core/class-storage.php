<?php
/**
 * Storage class - Handles file storage operations
 *
 * @package Teknup\Core
 */

namespace Teknup\Core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage class
 */
class Storage {

	/**
	 * Allowed file types
	 *
	 * @var array
	 */
	private $allowed_types = array(
		'audio/wav',
		'audio/x-wav',
		'audio/wave',
		'audio/mpeg',
		'audio/mp3',
		'audio/flac',
		'audio/x-flac',
		'audio/aiff',
		'audio/x-aiff',
	);

	/**
	 * Allowed file extensions
	 *
	 * @var array
	 */
	private $allowed_extensions = array( 'wav', 'mp3', 'flac', 'aiff', 'aif' );

	/**
	 * Validate uploaded file
	 *
	 * @param array $file File array from $_FILES.
	 * @return array|WP_Error Array with success message or WP_Error on failure.
	 */
	public function validate_file( $file ) {
		// Check for upload errors
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return new \WP_Error( 'invalid_file', __( 'Invalid file upload.', 'teknup-ai-mastering' ) );
		}

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'upload_error', $this->get_upload_error_message( $file['error'] ) );
		}

		// Check file size
		$max_size = teknup_ai_mastering()->get_setting( 'max_file_size', 500 ) * 1024 * 1024; // MB to bytes
		if ( $file['size'] > $max_size ) {
			return new \WP_Error(
				'file_too_large',
				sprintf(
					__( 'File size exceeds maximum allowed size of %s MB.', 'teknup-ai-mastering' ),
					teknup_ai_mastering()->get_setting( 'max_file_size', 500 )
				)
			);
		}

		// Check file extension
		$filename = $file['name'];
		$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, $this->allowed_extensions, true ) ) {
			return new \WP_Error(
				'invalid_extension',
				sprintf(
					__( 'Invalid file extension. Allowed types: %s', 'teknup-ai-mastering' ),
					implode( ', ', $this->allowed_extensions )
				)
			);
		}

		// Check MIME type
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime_type, $this->allowed_types, true ) ) {
			return new \WP_Error(
				'invalid_mime_type',
				__( 'Invalid file type. Please upload a valid audio file.', 'teknup-ai-mastering' )
			);
		}

		return array( 'success' => true );
	}

	/**
	 * Save uploaded file
	 *
	 * @param array $file File array from $_FILES.
	 * @param int   $job_id Job ID.
	 * @return array|WP_Error Array with file info or WP_Error on failure.
	 */
	public function save_upload( $file, $job_id ) {
		// Validate file
		$validation = $this->validate_file( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Generate unique filename
		$ext = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		$filename = sprintf(
			'%d-%s-%s.%s',
			get_current_user_id(),
			$job_id,
			wp_generate_password( 12, false ),
			$ext
		);

		$filepath = TEKNUP_ORIGINAL_DIR . '/' . $filename;

		// Move uploaded file
		if ( ! move_uploaded_file( $file['tmp_name'], $filepath ) ) {
			return new \WP_Error( 'save_failed', __( 'Failed to save uploaded file.', 'teknup-ai-mastering' ) );
		}

		// Set proper permissions
		chmod( $filepath, 0644 );

		return array(
			'filename' => $filename,
			'filepath' => $filepath,
			'size' => filesize( $filepath ),
			'original_name' => sanitize_file_name( $file['name'] ),
		);
	}

	/**
	 * Generate temporary public URL for Tonn API to access the file
	 *
	 * @param int $job_id Job ID.
	 * @return string|WP_Error Public URL or WP_Error on failure.
	 */
	public function generate_temp_url( $job_id ) {
		// Generate unique token
		$token = wp_generate_password( 32, false );

		// Store token in transient (expires in 2 hours)
		set_transient( 'teknup_temp_token_' . $token, $job_id, 2 * HOUR_IN_SECONDS );

		// Generate URL
		$url = add_query_arg(
			array(
				'teknup_download' => $token,
				'type' => 'original',
			),
			home_url( '/' )
		);

		return $url;
	}

	/**
	 * Generate secure download URL for user
	 *
	 * @param int    $job_id Job ID.
	 * @param string $type File type (original or mastered).
	 * @return string|WP_Error Download URL or WP_Error on failure.
	 */
	public function generate_download_url( $job_id, $type = 'mastered' ) {
		// Generate unique token
		$token = wp_generate_password( 32, false );

		// Store token in transient (expires in 24 hours)
		set_transient(
			'teknup_download_token_' . $token,
			array(
				'job_id' => $job_id,
				'type' => $type,
				'user_id' => get_current_user_id(),
			),
			24 * HOUR_IN_SECONDS
		);

		// Generate URL
		$url = add_query_arg(
			array(
				'teknup_download' => $token,
				'type' => $type,
			),
			home_url( '/' )
		);

		return $url;
	}

	/**
	 * Handle file download
	 *
	 * @param string $token Download token.
	 * @param string $type File type.
	 */
	public function handle_download( $token, $type ) {
		// Try temporary token first (for Tonn API access)
		$data = get_transient( 'teknup_temp_token_' . $token );
		$is_temp = true;

		// If not found, try download token (for user downloads)
		if ( ! $data ) {
			$data = get_transient( 'teknup_download_token_' . $token );
			$is_temp = false;
		}

		if ( ! $data ) {
			wp_die( __( 'Invalid or expired download link.', 'teknup-ai-mastering' ) );
		}

		// Check if user matches (for user downloads only, not temp URLs)
		if ( ! $is_temp && isset( $data['user_id'] ) && $data['user_id'] !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'You do not have permission to download this file.', 'teknup-ai-mastering' ) );
		}

		// Get job ID (temp tokens store job_id directly, download tokens store array)
		$job_id = $is_temp ? $data : ( isset( $data['job_id'] ) ? $data['job_id'] : $data );
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job ) {
			wp_die( __( 'Job not found.', 'teknup-ai-mastering' ) );
		}

		// Get file path
		if ( $type === 'original' ) {
			$filepath = $job->original_filepath;
			$filename = $job->original_filename;
		} else {
			$filepath = $job->mastered_filepath;
			$ext = pathinfo( $job->original_filename, PATHINFO_EXTENSION );
			$filename = str_replace( '.' . $ext, '_mastered.' . $ext, $job->original_filename );
		}

		if ( ! file_exists( $filepath ) ) {
			wp_die( __( 'File not found.', 'teknup-ai-mastering' ) );
		}

		// Stream file
		$this->stream_file( $filepath, $filename );
	}

	/**
	 * Stream file to browser
	 *
	 * @param string $filepath File path.
	 * @param string $filename Filename for download.
	 */
	private function stream_file( $filepath, $filename ) {
		// Clear output buffer
		if ( ob_get_level() ) {
			ob_end_clean();
		}

		// Get file info
		$filesize = filesize( $filepath );
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime_type = finfo_file( $finfo, $filepath );
		finfo_close( $finfo );

		// Set headers
		header( 'Content-Type: ' . $mime_type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . $filesize );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Expires: 0' );

		// Stream file
		readfile( $filepath );
		exit;
	}

	/**
	 * Save mastered file from URL
	 *
	 * @param string $url File URL.
	 * @param int    $job_id Job ID.
	 * @return string|WP_Error File path or WP_Error on failure.
	 */
	public function save_from_url( $url, $job_id ) {
		// Get job to get original filename
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );
		if ( ! $job ) {
			return new \WP_Error( 'job_not_found', __( 'Job not found.', 'teknup-ai-mastering' ) );
		}

		// Generate filename
		$ext = pathinfo( $job->original_filename, PATHINFO_EXTENSION );
		$filename = sprintf(
			'%d-%s-%s-mastered.%s',
			$job->user_id,
			$job_id,
			wp_generate_password( 12, false ),
			$ext
		);

		$filepath = TEKNUP_MASTERED_DIR . '/' . $filename;

		// Download file
		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 300, // 5 minutes
				'stream' => true,
				'filename' => $filepath,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return new \WP_Error(
				'download_failed',
				__( 'Failed to download mastered file from Tonn.', 'teknup-ai-mastering' )
			);
		}

		// Set proper permissions
		chmod( $filepath, 0644 );

		return $filepath;
	}

	/**
	 * Delete file
	 *
	 * @param string $filepath File path.
	 * @return bool True on success, false on failure.
	 */
	public function delete_file( $filepath ) {
		if ( file_exists( $filepath ) ) {
			return unlink( $filepath );
		}
		return false;
	}

	/**
	 * Clean old files
	 *
	 * @param int $days Number of days.
	 * @return int Number of deleted files.
	 */
	public function clean_old_files( $days = 30 ) {
		$deleted = 0;
		$cutoff_time = strtotime( "-{$days} days" );

		$directories = array( TEKNUP_ORIGINAL_DIR, TEKNUP_MASTERED_DIR );

		foreach ( $directories as $dir ) {
			$files = glob( $dir . '/*' );

			foreach ( $files as $file ) {
				if ( is_file( $file ) && filemtime( $file ) < $cutoff_time ) {
					if ( unlink( $file ) ) {
						$deleted++;
					}
				}
			}
		}

		return $deleted;
	}

	/**
	 * Get upload error message
	 *
	 * @param int $error_code Error code.
	 * @return string Error message.
	 */
	private function get_upload_error_message( $error_code ) {
		$errors = array(
			UPLOAD_ERR_INI_SIZE => __( 'File exceeds upload_max_filesize directive in php.ini.', 'teknup-ai-mastering' ),
			UPLOAD_ERR_FORM_SIZE => __( 'File exceeds MAX_FILE_SIZE directive in HTML form.', 'teknup-ai-mastering' ),
			UPLOAD_ERR_PARTIAL => __( 'File was only partially uploaded.', 'teknup-ai-mastering' ),
			UPLOAD_ERR_NO_FILE => __( 'No file was uploaded.', 'teknup-ai-mastering' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Missing temporary folder.', 'teknup-ai-mastering' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Failed to write file to disk.', 'teknup-ai-mastering' ),
			UPLOAD_ERR_EXTENSION => __( 'File upload stopped by extension.', 'teknup-ai-mastering' ),
		);

		return isset( $errors[ $error_code ] ) ? $errors[ $error_code ] : __( 'Unknown upload error.', 'teknup-ai-mastering' );
	}
}
