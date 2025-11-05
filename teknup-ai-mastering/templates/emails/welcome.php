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
		.upload-button {
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
		.features-list {
			list-style: none;
			padding: 0;
			margin: 20px 0;
		}
		.features-list li {
			padding: 10px 0;
			padding-left: 30px;
			position: relative;
		}
		.features-list li:before {
			content: "✓";
			position: absolute;
			left: 0;
			color: #dc143c;
			font-weight: bold;
			font-size: 18px;
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
			<h2><?php esc_html_e( 'Welcome to Teknup!', 'teknup-ai-mastering' ); ?></h2>

			<p><?php echo esc_html( sprintf( __( 'Hi %s,', 'teknup-ai-mastering' ), $user->display_name ) ); ?></p>

			<p><?php esc_html_e( 'Welcome to Teknup AI Mastering! We\'re excited to help you take your music to the next level with professional-quality mastering powered by artificial intelligence.', 'teknup-ai-mastering' ); ?></p>

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

			<p style="margin-top: 30px;">
				<?php esc_html_e( 'Let\'s make some great music together!', 'teknup-ai-mastering' ); ?><br>
				<strong>The Teknup Team</strong>
			</p>
		</div>
		<div class="email-footer">
			<p>&copy; <?php echo esc_html( date( 'Y' ) ); ?> Teknup AI Mastering. <?php esc_html_e( 'All rights reserved.', 'teknup-ai-mastering' ); ?></p>
		</div>
	</div>
</body>
</html>
