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
			background-color: #f4f4f4;
			margin: 0;
			padding: 0;
		}
		.email-container {
			max-width: 600px;
			margin: 40px auto;
			background: #ffffff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
		}
		.email-header {
			background: #ffffff;
			padding: 40px 30px 30px;
			text-align: center;
			border-bottom: 3px solid #9d2c20;
		}
		.email-header img {
			max-width: 180px;
			height: auto;
		}
		.email-body {
			padding: 40px 30px;
			color: #333;
		}
		.email-body h2 {
			color: #000;
			margin-top: 0;
			font-size: 24px;
			font-weight: 700;
		}
		.email-body p {
			color: #333;
			font-size: 16px;
			line-height: 1.6;
		}
		.download-button {
			display: inline-block;
			padding: 16px 40px;
			background: linear-gradient(135deg, #9d2c20 0%, #ad1831 100%);
			color: #ffffff !important;
			text-decoration: none;
			border-radius: 6px;
			margin: 25px 0;
			font-weight: 700;
			text-transform: uppercase;
			letter-spacing: 0.5px;
			font-size: 14px;
			box-shadow: 0 4px 12px rgba(157, 44, 32, 0.3);
		}
		.file-info {
			background: #f8f9fa;
			border-left: 4px solid #9d2c20;
			padding: 20px;
			border-radius: 4px;
			margin: 25px 0;
		}
		.file-info strong {
			color: #000;
		}
		.email-footer {
			background: #f8f9fa;
			padding: 25px;
			text-align: center;
			font-size: 13px;
			color: #666;
			border-top: 1px solid #e0e0e0;
		}
		.note {
			background: #fff9e6;
			border-left: 4px solid #f59e0b;
			padding: 15px;
			margin: 20px 0;
			border-radius: 4px;
			font-size: 14px;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<img src="<?php echo esc_url( home_url( '/wp-content/uploads/2025/11/teknup-logo-noir.png' ) ); ?>" alt="Teknup">
		</div>
		<div class="email-body">
			<h2><?php esc_html_e( 'Your Master is Ready! 🎵', 'teknup-ai-mastering' ); ?></h2>

			<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'teknup-ai-mastering' ), $user->display_name ) ); ?></p>

			<p><?php esc_html_e( 'Great news! Your mastering job has been completed successfully and your track is ready to download.', 'teknup-ai-mastering' ); ?></p>

			<div class="file-info">
				<strong><?php esc_html_e( 'File:', 'teknup-ai-mastering' ); ?></strong> <?php echo esc_html( $job->original_filename ); ?><br>
				<strong><?php esc_html_e( 'Completed:', 'teknup-ai-mastering' ); ?></strong> <?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $job->completed_at ) ) ); ?>
			</div>

			<p style="text-align: center;">
				<a href="<?php echo esc_url( $download_url ); ?>" class="download-button">
					⬇ <?php esc_html_e( 'Download Your Master', 'teknup-ai-mastering' ); ?>
				</a>
			</p>

			<div class="note">
				⏰ <?php esc_html_e( 'Your file will be available for download for 30 days.', 'teknup-ai-mastering' ); ?>
			</div>

			<p><?php esc_html_e( 'Ready to master another track? Upload it now and get professional results in minutes!', 'teknup-ai-mastering' ); ?></p>

			<p style="margin-top: 30px; color: #666;">
				<?php esc_html_e( 'Keep creating amazing music!', 'teknup-ai-mastering' ); ?><br>
				<strong style="color: #000;">The Teknup Team</strong>
			</p>
		</div>
		<div class="email-footer">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Teknup AI Mastering. <?php esc_html_e( 'All rights reserved.', 'teknup-ai-mastering' ); ?></p>
		</div>
	</div>
</body>
</html>
