<?php
/**
 * Public Controller class - Handles frontend functionality
 *
 * @package Teknup\Public
 */

namespace Teknup\Public;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Public Controller class
 */
class Public_Controller {

	/**
	 * Upload instance
	 *
	 * @var Upload
	 */
	private $upload;

	/**
	 * Account instance
	 *
	 * @var Account
	 */
	private $account;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->upload = new Upload();
		$this->account = new Account();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_shortcode( 'teknup_mastering', array( $this, 'render_mastering_shortcode' ) );
		add_shortcode( 'teknup_debug', array( $this, 'render_debug_shortcode' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 */
	public function enqueue_scripts() {
		// Only load on specific pages
		if ( ! $this->should_load_assets() ) {
			return;
		}

		wp_enqueue_style(
			'teknup-public',
			TEKNUP_PLUGIN_URL . 'assets/dist/teknup-styles.css',
			array(),
			TEKNUP_VERSION
		);

		wp_enqueue_script(
			'teknup-upload',
			TEKNUP_PLUGIN_URL . 'assets/dist/upload.js',
			array(),
			TEKNUP_VERSION,
			true
		);

		wp_enqueue_script(
			'teknup-dashboard',
			TEKNUP_PLUGIN_URL . 'assets/dist/dashboard.js',
			array(),
			TEKNUP_VERSION,
			true
		);

		wp_enqueue_script(
			'teknup-main',
			TEKNUP_PLUGIN_URL . 'assets/dist/main.js',
			array(),
			TEKNUP_VERSION,
			true
		);

		$has_subscription = false;
		if ( is_user_logged_in() ) {
			$has_subscription = teknup_ai_mastering()->subscriptions->has_active_subscription();
		}

		wp_localize_script(
			'teknup-main',
			'teknupData',
			array(
				'restUrl' => rest_url( 'teknup/v1/' ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
				'maxFileSize' => teknup_ai_mastering()->get_setting( 'max_file_size', 500 ) * 1024 * 1024,
				'allowedTypes' => array( 'audio/wav', 'audio/mpeg', 'audio/mp3', 'audio/flac', 'audio/aiff' ),
				'allowedExtensions' => array( 'wav', 'mp3', 'flac', 'aiff', 'aif' ),
				'isLoggedIn' => is_user_logged_in(),
				'hasActiveSubscription' => $has_subscription,
				'siteUrl' => home_url(),
			)
		);
	}

	/**
	 * Check if assets should be loaded
	 *
	 * @return bool True if assets should be loaded.
	 */
	private function should_load_assets() {
		// Load on WooCommerce My Account pages
		if ( function_exists( 'is_account_page' ) && is_account_page() ) {
			return true;
		}

		// Load on pages with shortcodes
		global $post;
		if ( $post && ( has_shortcode( $post->post_content, 'teknup_upload' ) || has_shortcode( $post->post_content, 'teknup_dashboard' ) || has_shortcode( $post->post_content, 'teknup_mastering' ) || has_shortcode( $post->post_content, 'teknup_debug' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Render main mastering shortcode
	 *
	 * @return string Mastering app HTML.
	 */
	public function render_mastering_shortcode() {
		// React handles authentication state display
		return '<div id="teknup-mastering-app"></div>';
	}

	/**
	 * Render debug shortcode
	 *
	 * @return string Debug information HTML.
	 */
	public function render_debug_shortcode() {
		global $post;

		ob_start();
		?>
		<div class="teknup-debug" style="background: #f5f5f5; padding: 20px; border: 2px solid #dc143c; border-radius: 8px; font-family: monospace; font-size: 14px;">
			<h2 style="color: #dc143c; margin-top: 0;">🔧 Teknup Debug Information</h2>

			<!-- User Info -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #4CAF50;">
				<h3 style="margin-top: 0; color: #4CAF50;">👤 User Information</h3>
				<p><strong>Logged In:</strong> <?php echo is_user_logged_in() ? '✅ Yes' : '❌ No'; ?></p>
				<?php if ( is_user_logged_in() ) : ?>
					<p><strong>User ID:</strong> <?php echo get_current_user_id(); ?></p>
					<p><strong>Username:</strong> <?php echo wp_get_current_user()->user_login; ?></p>
					<p><strong>Email:</strong> <?php echo wp_get_current_user()->user_email; ?></p>
				<?php endif; ?>
			</div>

			<!-- Plugin Info -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #2196F3;">
				<h3 style="margin-top: 0; color: #2196F3;">🔌 Plugin Information</h3>
				<p><strong>Plugin Version:</strong> <?php echo TEKNUP_VERSION; ?></p>
				<p><strong>Plugin Dir:</strong> <?php echo TEKNUP_PLUGIN_DIR; ?></p>
				<p><strong>Plugin URL:</strong> <?php echo TEKNUP_PLUGIN_URL; ?></p>
			</div>

			<!-- Assets Check -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #FF9800;">
				<h3 style="margin-top: 0; color: #FF9800;">📦 Assets Check</h3>
				<p><strong>Should Load Assets:</strong> <?php echo $this->should_load_assets() ? '✅ Yes' : '❌ No'; ?></p>

				<?php
				$main_js = TEKNUP_PLUGIN_DIR . 'assets/dist/main.js';
				$upload_js = TEKNUP_PLUGIN_DIR . 'assets/dist/upload.js';
				$dashboard_js = TEKNUP_PLUGIN_DIR . 'assets/dist/dashboard.js';
				$css_file = TEKNUP_PLUGIN_DIR . 'assets/dist/teknup-styles.css';
				?>

				<p><strong>main.js exists:</strong> <?php echo file_exists( $main_js ) ? '✅ Yes (' . size_format( filesize( $main_js ) ) . ')' : '❌ No'; ?></p>
				<p><strong>upload.js exists:</strong> <?php echo file_exists( $upload_js ) ? '✅ Yes (' . size_format( filesize( $upload_js ) ) . ')' : '❌ No'; ?></p>
				<p><strong>dashboard.js exists:</strong> <?php echo file_exists( $dashboard_js ) ? '✅ Yes (' . size_format( filesize( $dashboard_js ) ) . ')' : '❌ No'; ?></p>
				<p><strong>teknup-styles.css exists:</strong> <?php echo file_exists( $css_file ) ? '✅ Yes (' . size_format( filesize( $css_file ) ) . ')' : '❌ No'; ?></p>
			</div>

			<!-- Enqueued Scripts -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #9C27B0;">
				<h3 style="margin-top: 0; color: #9C27B0;">📜 Enqueued Scripts</h3>
				<?php
				global $wp_scripts;
				$teknup_scripts = array();
				if ( ! empty( $wp_scripts->registered ) ) {
					foreach ( $wp_scripts->registered as $handle => $script ) {
						if ( strpos( $handle, 'teknup' ) !== false || strpos( $script->src, 'teknup' ) !== false ) {
							$teknup_scripts[] = $handle;
						}
					}
				}
				?>
				<?php if ( ! empty( $teknup_scripts ) ) : ?>
					<ul style="margin: 5px 0; padding-left: 20px;">
						<?php foreach ( $teknup_scripts as $handle ) : ?>
							<li>
								<strong><?php echo esc_html( $handle ); ?></strong>
								<?php if ( wp_script_is( $handle, 'enqueued' ) ) : ?>
									<span style="color: green;">✓ Enqueued</span>
								<?php elseif ( wp_script_is( $handle, 'registered' ) ) : ?>
									<span style="color: orange;">⚠ Registered only</span>
								<?php endif; ?>
								<br>
								<small><?php echo esc_html( $wp_scripts->registered[ $handle ]->src ); ?></small>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php else : ?>
					<p style="color: red;">❌ No Teknup scripts found!</p>
				<?php endif; ?>
			</div>

			<!-- WP React Dependencies -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #00BCD4;">
				<h3 style="margin-top: 0; color: #00BCD4;">⚛️ React Dependencies</h3>
				<p><strong>wp-element:</strong> <?php echo wp_script_is( 'wp-element', 'registered' ) ? '✅ Registered' : '❌ Not registered'; ?></p>
				<p><strong>wp-i18n:</strong> <?php echo wp_script_is( 'wp-i18n', 'registered' ) ? '✅ Registered' : '❌ Not registered'; ?></p>
				<p><strong>react:</strong> <?php echo wp_script_is( 'react', 'registered' ) ? '✅ Registered' : '❌ Not registered'; ?></p>
				<p><strong>react-dom:</strong> <?php echo wp_script_is( 'react-dom', 'registered' ) ? '✅ Registered' : '❌ Not registered'; ?></p>
			</div>

			<!-- Shortcodes Check -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #E91E63;">
				<h3 style="margin-top: 0; color: #E91E63;">📋 Shortcodes Check</h3>
				<?php if ( $post ) : ?>
					<p><strong>Current Post ID:</strong> <?php echo $post->ID; ?></p>
					<p><strong>Post Type:</strong> <?php echo $post->post_type; ?></p>
					<p><strong>Has [teknup_mastering]:</strong> <?php echo has_shortcode( $post->post_content, 'teknup_mastering' ) ? '✅ Yes' : '❌ No'; ?></p>
					<p><strong>Has [teknup_upload]:</strong> <?php echo has_shortcode( $post->post_content, 'teknup_upload' ) ? '✅ Yes' : '❌ No'; ?></p>
					<p><strong>Has [teknup_dashboard]:</strong> <?php echo has_shortcode( $post->post_content, 'teknup_dashboard' ) ? '✅ Yes' : '❌ No'; ?></p>
					<p><strong>Has [teknup_debug]:</strong> <?php echo has_shortcode( $post->post_content, 'teknup_debug' ) ? '✅ Yes' : '❌ No'; ?></p>
				<?php else : ?>
					<p style="color: red;">❌ No post found!</p>
				<?php endif; ?>
			</div>

			<!-- WooCommerce Check -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #673AB7;">
				<h3 style="margin-top: 0; color: #673AB7;">🛒 WooCommerce Status</h3>
				<p><strong>WooCommerce Active:</strong> <?php echo class_exists( 'WooCommerce' ) ? '✅ Yes' : '❌ No'; ?></p>
				<p><strong>WooCommerce Subscriptions Active:</strong> <?php echo class_exists( 'WC_Subscriptions' ) ? '✅ Yes' : '❌ No'; ?></p>
				<?php if ( is_user_logged_in() && class_exists( 'WooCommerce' ) ) : ?>
					<?php
					$plan = teknup_ai_mastering()->subscriptions->get_user_plan();
					$quota = teknup_ai_mastering()->subscriptions->get_user_quota();
					?>
					<p><strong>Current Plan:</strong> <?php echo esc_html( $quota['plan_name'] ); ?></p>
					<p><strong>Monthly Limit:</strong> <?php echo $quota['unlimited'] ? 'Unlimited' : $quota['limit']; ?></p>
					<p><strong>Usage:</strong> <?php echo $quota['usage']; ?> / <?php echo $quota['unlimited'] ? '∞' : $quota['limit']; ?></p>
				<?php endif; ?>
			</div>

			<!-- teknupData JavaScript Object -->
			<div style="background: white; padding: 15px; margin-bottom: 15px; border-left: 4px solid #795548;">
				<h3 style="margin-top: 0; color: #795548;">🔧 JavaScript Data (teknupData)</h3>
				<pre style="background: #f9f9f9; padding: 10px; overflow-x: auto; border-radius: 4px; max-height: 300px;"><?php
				echo json_encode( array(
					'restUrl' => rest_url( 'teknup/v1/' ),
					'nonce' => wp_create_nonce( 'wp_rest' ),
					'maxFileSize' => teknup_ai_mastering()->get_setting( 'max_file_size', 500 ) * 1024 * 1024,
					'allowedTypes' => array( 'audio/wav', 'audio/mpeg', 'audio/mp3', 'audio/flac', 'audio/aiff' ),
					'allowedExtensions' => array( 'wav', 'mp3', 'flac', 'aiff', 'aif' ),
				), JSON_PRETTY_PRINT );
				?></pre>
			</div>

			<!-- Recommendations -->
			<div style="background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">
				<h3 style="margin-top: 0; color: #856404;">💡 Recommendations</h3>
				<?php if ( ! file_exists( $main_js ) || ! file_exists( $upload_js ) || ! file_exists( $dashboard_js ) ) : ?>
					<p style="color: #856404;">⚠️ <strong>Missing JavaScript files!</strong> Run <code>npm install && npm run build</code> in the plugin directory.</p>
				<?php endif; ?>
				<?php if ( ! is_user_logged_in() ) : ?>
					<p style="color: #856404;">⚠️ <strong>Not logged in!</strong> Log in to see the mastering interface.</p>
				<?php endif; ?>
				<?php if ( ! $this->should_load_assets() ) : ?>
					<p style="color: #856404;">⚠️ <strong>Assets not loading!</strong> Make sure the shortcode is present on this page.</p>
				<?php endif; ?>
				<?php if ( ! class_exists( 'WC_Subscriptions' ) ) : ?>
					<p style="color: #856404;">⚠️ <strong>WooCommerce Subscriptions not active!</strong> Install and activate it for full functionality.</p>
				<?php endif; ?>
			</div>

			<!-- Live DOM Check -->
			<div style="background: white; padding: 15px; border-left: 4px solid #607D8B;">
				<h3 style="margin-top: 0; color: #607D8B;">🎯 Live DOM Check</h3>
				<p><strong>Target DIV ID:</strong> teknup-mastering-app</p>
				<p id="teknup-dom-check"><em>Checking...</em></p>
			</div>
		</div>

		<script>
		// Check if the target div exists
		document.addEventListener('DOMContentLoaded', function() {
			var checkDiv = document.getElementById('teknup-mastering-app');
			var resultDiv = document.getElementById('teknup-dom-check');

			if (checkDiv) {
				resultDiv.innerHTML = '<span style="color: green;">✅ DIV #teknup-mastering-app found in DOM</span>';

				// Check if React loaded
				setTimeout(function() {
					if (checkDiv.innerHTML.trim() === '') {
						resultDiv.innerHTML += '<br><span style="color: orange;">⚠️ DIV is empty - React may not have rendered</span>';
					} else {
						resultDiv.innerHTML += '<br><span style="color: green;">✅ DIV has content - React rendered successfully!</span>';
					}
				}, 1000);
			} else {
				resultDiv.innerHTML = '<span style="color: red;">❌ DIV #teknup-mastering-app NOT found in DOM</span>';
			}

			// Check for JavaScript errors
			if (window.console && window.console.error) {
				resultDiv.innerHTML += '<br><strong>Console Errors:</strong> Check browser console (F12) for JavaScript errors';
			}

			// Check if teknupData exists
			if (typeof teknupData !== 'undefined') {
				resultDiv.innerHTML += '<br><span style="color: green;">✅ teknupData object loaded</span>';
			} else {
				resultDiv.innerHTML += '<br><span style="color: red;">❌ teknupData object NOT loaded</span>';
			}

			// Check if React is available
			if (typeof React !== 'undefined') {
				resultDiv.innerHTML += '<br><span style="color: green;">✅ React loaded</span>';
			} else {
				resultDiv.innerHTML += '<br><span style="color: red;">❌ React NOT loaded</span>';
			}

			// Check if ReactDOM is available
			if (typeof ReactDOM !== 'undefined') {
				resultDiv.innerHTML += '<br><span style="color: green;">✅ ReactDOM loaded</span>';
			} else {
				resultDiv.innerHTML += '<br><span style="color: red;">❌ ReactDOM NOT loaded</span>';
			}
		});
		</script>
		<?php
		return ob_get_clean();
	}
}
