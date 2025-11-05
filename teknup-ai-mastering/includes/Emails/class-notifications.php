<?php
/**
 * Notifications class - Handles email notifications
 *
 * @package Teknup\Emails
 */

namespace Teknup\Emails;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications class
 */
class Notifications {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'teknup_job_completed', array( $this, 'send_completion_email' ) );
		add_action( 'teknup_job_failed', array( $this, 'send_failure_email' ) );
		add_action( 'user_register', array( $this, 'send_welcome_email' ) );
	}

	/**
	 * Send job completion email
	 *
	 * @param int $job_id Job ID.
	 */
	public function send_completion_email( $job_id ) {
		if ( ! teknup_ai_mastering()->get_setting( 'enable_notifications', true ) ) {
			return;
		}

		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job ) {
			return;
		}

		$user = get_userdata( $job->user_id );

		if ( ! $user ) {
			return;
		}

		// Generate download URL
		$download_url = teknup_ai_mastering()->storage->generate_download_url( $job_id, 'mastered' );

		// Prepare email data
		$data = array(
			'user' => $user,
			'job' => $job,
			'download_url' => $download_url,
		);

		$subject = sprintf(
			__( '[Teknup] Your master for "%s" is ready!', 'teknup-ai-mastering' ),
			$job->original_filename
		);

		$message = $this->get_email_template( 'job-completed', $data );

		$this->send_email( $user->user_email, $subject, $message );

		// Update notified timestamp
		teknup_ai_mastering()->jobs->update_status(
			$job_id,
			'completed',
			array( 'notified_at' => current_time( 'mysql' ) )
		);

		teknup_ai_mastering()->log( "Completion email sent for job {$job_id} to {$user->user_email}", 'info' );
	}

	/**
	 * Send job failure email
	 *
	 * @param int $job_id Job ID.
	 */
	public function send_failure_email( $job_id ) {
		if ( ! teknup_ai_mastering()->get_setting( 'enable_notifications', true ) ) {
			return;
		}

		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job ) {
			return;
		}

		$user = get_userdata( $job->user_id );

		if ( ! $user ) {
			return;
		}

		// Prepare email data
		$data = array(
			'user' => $user,
			'job' => $job,
			'support_email' => get_option( 'admin_email' ),
		);

		$subject = sprintf(
			__( '[Teknup] Issue with mastering "%s"', 'teknup-ai-mastering' ),
			$job->original_filename
		);

		$message = $this->get_email_template( 'job-failed', $data );

		$this->send_email( $user->user_email, $subject, $message );

		teknup_ai_mastering()->log( "Failure email sent for job {$job_id} to {$user->user_email}", 'info' );
	}

	/**
	 * Send welcome email to new users
	 *
	 * @param int $user_id User ID.
	 */
	public function send_welcome_email( $user_id ) {
		if ( ! teknup_ai_mastering()->get_setting( 'enable_notifications', true ) ) {
			return;
		}

		$user = get_userdata( $user_id );

		if ( ! $user ) {
			return;
		}

		// Prepare email data
		$data = array(
			'user' => $user,
			'upload_url' => wc_get_account_endpoint_url( 'teknup-upload' ),
		);

		$subject = __( 'Welcome to Teknup AI Mastering!', 'teknup-ai-mastering' );

		$message = $this->get_email_template( 'welcome', $data );

		$this->send_email( $user->user_email, $subject, $message );

		teknup_ai_mastering()->log( "Welcome email sent to user {$user_id}: {$user->user_email}", 'info' );
	}

	/**
	 * Get email template
	 *
	 * @param string $template Template name.
	 * @param array  $data Template data.
	 * @return string Email HTML.
	 */
	private function get_email_template( $template, $data ) {
		$template_file = TEKNUP_PLUGIN_DIR . "templates/emails/{$template}.php";

		if ( ! file_exists( $template_file ) ) {
			return '';
		}

		ob_start();
		extract( $data );
		include $template_file;
		return ob_get_clean();
	}

	/**
	 * Send email
	 *
	 * @param string $to Recipient email.
	 * @param string $subject Email subject.
	 * @param string $message Email message.
	 * @return bool True on success, false on failure.
	 */
	private function send_email( $to, $subject, $message ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Teknup <' . get_option( 'admin_email' ) . '>',
		);

		return wp_mail( $to, $subject, $message, $headers );
	}
}
