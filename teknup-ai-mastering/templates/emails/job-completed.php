<?php
/**
 * Job Completed Email Template
 *
 * @package Teknup
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body {
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
			line-height: 1.6;
			color: #333;
			background-color: #0a0a0a;
			margin: 0;
			padding: 0;
		}
		.email-container {
			max-width: 600px;
			margin: 40px auto;
			background: rgba(255, 255, 255, 0.05);
			border: 1px solid rgba(220, 20, 60, 0.3);
			border-radius: 12px;
			overflow: hidden;
			backdrop-filter: blur(10px);
		}
		.email-header {
			background: linear-gradient(135deg, #dc143c 0%, #8b0000 100%);
			color: white;
			padding: 30px;
			text-align: center;
		}
		.email-header h1 {
			margin: 0;
			font-size: 28px;
			text-transform: uppercase;
			letter-spacing: 2px;
		}
		.email-body {
			padding: 40px 30px;
			color: #f5f5f5;
		}
		.email-body h2 {
			color: #dc143c;
			margin-top: 0;
			text-transform: uppercase;
			letter-spacing: 1px;
		}
		.download-button {
			display: inline-block;
			padding: 15px 40px;
			background: linear-gradient(135deg, #dc143c 0%, #8b0000 100%);
			color: white;
			text-decoration: none;
			border-radius: 8px;
			margin: 20px 0;
			font-weight: bold;
			text-transform: uppercase;
			letter-spacing: 1px;
			box-shadow: 0 4px 15px rgba(220, 20, 60, 0.3);
		}
		.file-info {
			background: rgba(255, 255, 255, 0.03);
			border: 1px solid rgba(220, 20, 60, 0.2);
			padding: 15px;
			border-radius: 8px;
			margin: 20px 0;
		}
		.email-footer {
			background: rgba(0, 0, 0, 0.3);
			padding: 20px;
			text-align: center;
			font-size: 12px;
			color: #999;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<h1>🎵 TEKNUP AI MASTERING</h1>
		</div>
		<div class="email-body">
			<h2><?php esc_html_e( 'Your Master is Ready!', 'teknup-ai-mastering' ); ?></h2>

			<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'teknup-ai-mastering' ), $user->display_name ) ); ?></p>

			<p><?php esc_html_e( 'Great news! Your mastering job has been completed successfully and your track is ready to download.', 'teknup-ai-mastering' ); ?></p>

			<div class="file-info">
				<strong><?php esc_html_e( 'File:', 'teknup-ai-mastering' ); ?></strong> <?php echo esc_html( $job->original_filename ); ?><br>
				<strong><?php esc_html_e( 'Processed:', 'teknup-ai-mastering' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job->completed_at ) ) ); ?>
			</div>

			<p style="text-align: center;">
				<a href="<?php echo esc_url( $download_url ); ?>" class="download-button">
					<?php esc_html_e( 'Download Your Master', 'teknup-ai-mastering' ); ?>
				</a>
			</p>

			<p><?php esc_html_e( 'Your file will be available for download for 30 days.', 'teknup-ai-mastering' ); ?></p>

			<p><?php esc_html_e( 'Ready to master another track? Upload it now and get professional results in less than a minute!', 'teknup-ai-mastering' ); ?></p>

			<p style="margin-top: 30px;">
				<?php esc_html_e( 'Keep creating amazing music!', 'teknup-ai-mastering' ); ?><br>
				<strong>The Teknup Team</strong>
			</p>
		</div>
		<div class="email-footer">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Teknup AI Mastering. <?php esc_html_e( 'All rights reserved.', 'teknup-ai-mastering' ); ?></p>
		</div>
	</div>
</body>
</html>
