<?php
/**
 * Welcome Email Template
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
		.email-body h3 {
			color: #000;
			font-size: 18px;
			font-weight: 600;
			margin-top: 30px;
			margin-bottom: 15px;
		}
		.email-body p {
			color: #333;
			font-size: 16px;
			line-height: 1.6;
		}
		.upload-button {
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
		.features-list {
			list-style: none;
			padding: 0;
			margin: 20px 0;
		}
		.features-list li {
			padding: 12px 0 12px 35px;
			position: relative;
			font-size: 15px;
			color: #333;
		}
		.features-list li:before {
			content: "✓";
			position: absolute;
			left: 0;
			color: #9d2c20;
			font-weight: bold;
			font-size: 20px;
			width: 25px;
			height: 25px;
			display: flex;
			align-items: center;
			justify-content: center;
		}
		.welcome-box {
			background: #f8f9fa;
			border-left: 4px solid #9d2c20;
			padding: 20px;
			border-radius: 4px;
			margin: 25px 0;
		}
		.email-footer {
			background: #f8f9fa;
			padding: 25px;
			text-align: center;
			font-size: 13px;
			color: #666;
			border-top: 1px solid #e0e0e0;
		}
	</style>
</head>
<body>
	<div class="email-container">
		<div class="email-header">
			<img src="<?php echo esc_url( home_url( '/wp-content/uploads/2025/11/teknup-logo-noir.png' ) ); ?>" alt="Teknup">
		</div>
		<div class="email-body">
			<h2><?php esc_html_e( 'Welcome to Teknup!', 'teknup-ai-mastering' ); ?></h2>

			<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'teknup-ai-mastering' ), $user->display_name ) ); ?></p>

			<div class="welcome-box">
				<strong><?php esc_html_e( 'Welcome to Teknup AI Mastering!', 'teknup-ai-mastering' ); ?></strong><br>
				<?php esc_html_e( 'We\'re excited to help you take your music to the next level with professional-quality mastering powered by artificial intelligence.', 'teknup-ai-mastering' ); ?>
			</div>

			<h3><?php esc_html_e( 'What You Can Do:', 'teknup-ai-mastering' ); ?></h3>

			<ul class="features-list">
				<li><?php esc_html_e( 'Upload your tracks in WAV, MP3, FLAC, or AIFF format', 'teknup-ai-mastering' ); ?></li>
				<li><?php esc_html_e( 'Get professional mastering in less than 60 seconds', 'teknup-ai-mastering' ); ?></li>
				<li><?php esc_html_e( 'Customize intensity and target LUFS for your genre', 'teknup-ai-mastering' ); ?></li>
				<li><?php esc_html_e( 'Download your mastered tracks instantly', 'teknup-ai-mastering' ); ?></li>
				<li><?php esc_html_e( 'Unlimited revisions on all plans', 'teknup-ai-mastering' ); ?></li>
			</ul>

			<p style="text-align: center;">
				<a href="<?php echo esc_url( $upload_url ); ?>" class="upload-button">
					<?php esc_html_e( 'Upload Your First Track', 'teknup-ai-mastering' ); ?>
				</a>
			</p>

			<p><?php esc_html_e( 'If you have any questions, our support team is always here to help. Just reply to this email!', 'teknup-ai-mastering' ); ?></p>

			<p style="margin-top: 30px; color: #666;">
				<?php esc_html_e( 'Let\'s make some great music together!', 'teknup-ai-mastering' ); ?><br>
				<strong style="color: #000;">The Teknup Team</strong>
			</p>
		</div>
		<div class="email-footer">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Teknup AI Mastering. <?php esc_html_e( 'All rights reserved.', 'teknup-ai-mastering' ); ?></p>
		</div>
	</div>
</body>
</html>
