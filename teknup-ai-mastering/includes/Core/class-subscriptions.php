<?php
/**
 * Subscriptions class - Handles WooCommerce subscription integration
 *
 * @package Teknup\Core
 */

namespace Teknup\Core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscriptions class
 */
class Subscriptions {

	/**
	 * Plan limits
	 *
	 * @var array
	 */
	private $plan_limits = array(
		'free_trial' => array(
			'name' => 'Free Trial',
			'monthly_limit' => 1, // Changed from 3 to 1
			'features' => array( 'basic' ),
		),
		'starter' => array(
			'name' => 'Starter',
			'monthly_limit' => 3,
			'features' => array( 'basic', 'advanced_controls', 'presets', 'unlimited_revisions' ),
		),
		'pro' => array(
			'name' => 'Pro',
			'monthly_limit' => 6,
			'features' => array( 'basic', 'advanced_controls', 'presets', 'unlimited_revisions', 'priority_support' ),
		),
		'premium' => array(
			'name' => 'Premium',
			'monthly_limit' => 12,
			'features' => array( 'basic', 'advanced_controls', 'presets', 'unlimited_revisions', 'priority_support', 'extended_storage' ),
		),
	);

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook into subscription renewal to reset counters
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'reset_monthly_counter' ) );
		add_action( 'woocommerce_scheduled_subscription_payment', array( $this, 'reset_monthly_counter' ) );

		// Hook into job completion to increment usage counter
		add_action( 'teknup_job_completed', array( $this, 'on_job_completed' ) );
	}

	/**
	 * Get user's active subscription
	 *
	 * @param int $user_id User ID.
	 * @return object|null Subscription object or null.
	 */
	public function get_user_subscription( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! function_exists( 'wcs_get_users_subscriptions' ) ) {
			return null;
		}

		$subscriptions = wcs_get_users_subscriptions( $user_id );

		foreach ( $subscriptions as $subscription ) {
			if ( $subscription->has_status( 'active' ) ) {
				return $subscription;
			}
		}

		return null;
	}

	/**
	 * Get user's plan
	 *
	 * @param int $user_id User ID.
	 * @return string Plan slug.
	 */
	public function get_user_plan( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Check for active subscription
		$subscription = $this->get_user_subscription( $user_id );

		if ( $subscription ) {
			// Get plan from subscription product meta
			foreach ( $subscription->get_items() as $item ) {
				$product_id = $item->get_product_id();
				$plan_slug = get_post_meta( $product_id, '_teknup_plan_slug', true );

				if ( $plan_slug && isset( $this->plan_limits[ $plan_slug ] ) ) {
					return $plan_slug;
				}
			}

			// Fallback: try to detect from product name
			foreach ( $subscription->get_items() as $item ) {
				$product_name = strtolower( $item->get_name() );

				if ( strpos( $product_name, 'premium' ) !== false ) {
					return 'premium';
				} elseif ( strpos( $product_name, 'pro' ) !== false ) {
					return 'pro';
				} elseif ( strpos( $product_name, 'starter' ) !== false ) {
					return 'starter';
				}
			}
		}

		// Check for free trial
		$trial_used = get_user_meta( $user_id, 'teknup_trial_used', true );
		$trial_count = get_user_meta( $user_id, 'teknup_trial_count', true );

		// Free trial now allows only 1 master (changed from 3)
		if ( ! $trial_used || (int) $trial_count < 1 ) {
			return 'free_trial';
		}

		return 'none';
	}

	/**
	 * Get plan limits
	 *
	 * @param string $plan Plan slug.
	 * @return array|null Plan limits or null.
	 */
	public function get_plan_limits( $plan ) {
		return isset( $this->plan_limits[ $plan ] ) ? $this->plan_limits[ $plan ] : null;
	}

	/**
	 * Get user's monthly limit
	 *
	 * @param int $user_id User ID.
	 * @return int Monthly limit (-1 for unlimited).
	 */
	public function get_user_monthly_limit( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$plan = $this->get_user_plan( $user_id );
		$limits = $this->get_plan_limits( $plan );

		return $limits ? $limits['monthly_limit'] : 0;
	}

	/**
	 * Get user's monthly usage
	 *
	 * @param int $user_id User ID.
	 * @return int Monthly usage count.
	 */
	public function get_user_monthly_usage( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$plan = $this->get_user_plan( $user_id );

		// For free trial, use user meta
		if ( $plan === 'free_trial' ) {
			$trial_count = get_user_meta( $user_id, 'teknup_trial_count', true );
			return (int) $trial_count;
		}

		// For paid plans, count completed jobs this month
		return teknup_ai_mastering()->jobs->get_user_monthly_jobs_count( $user_id );
	}

	/**
	 * Check if user can upload
	 *
	 * @param int $user_id User ID.
	 * @return bool|WP_Error True if can upload or WP_Error.
	 */
	public function can_user_upload( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return new \WP_Error( 'not_logged_in', __( 'You must be logged in to upload files.', 'teknup-ai-mastering' ) );
		}

		$plan = $this->get_user_plan( $user_id );

		if ( $plan === 'none' ) {
			return new \WP_Error(
				'no_subscription',
				__( 'You need an active subscription to upload files. Please subscribe to continue.', 'teknup-ai-mastering' )
			);
		}

		$limit = $this->get_user_monthly_limit( $user_id );
		$usage = $this->get_user_monthly_usage( $user_id );

		// Unlimited
		if ( $limit === -1 ) {
			return true;
		}

		// Check limit
		if ( $usage >= $limit ) {
			return new \WP_Error(
				'limit_reached',
				sprintf(
					__( 'You have reached your monthly limit of %d masters. Please upgrade your plan to continue.', 'teknup-ai-mastering' ),
					$limit
				)
			);
		}

		return true;
	}

	/**
	 * Increment usage counter
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function increment_usage( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$plan = $this->get_user_plan( $user_id );

		// For free trial, increment user meta
		if ( $plan === 'free_trial' ) {
			$trial_count = get_user_meta( $user_id, 'teknup_trial_count', true );
			$new_count = (int) $trial_count + 1;
			update_user_meta( $user_id, 'teknup_trial_count', $new_count );

			// Mark trial as used if limit reached (1 master)
			if ( $new_count >= 1 ) {
				update_user_meta( $user_id, 'teknup_trial_used', true );
			}
		}

		// For paid plans, usage is counted from completed jobs automatically

		return true;
	}

	/**
	 * Reset monthly counter on subscription renewal
	 *
	 * @param object $subscription Subscription object.
	 */
	public function reset_monthly_counter( $subscription ) {
		$user_id = $subscription->get_user_id();

		// Reset trial counter if applicable
		update_user_meta( $user_id, 'teknup_trial_count', 0 );
		update_user_meta( $user_id, 'teknup_trial_used', false );

		teknup_ai_mastering()->log( "Monthly counter reset for user {$user_id}", 'info' );

		/**
		 * Fires after monthly counter is reset
		 *
		 * @param int    $user_id User ID.
		 * @param object $subscription Subscription object.
		 */
		do_action( 'teknup_monthly_counter_reset', $user_id, $subscription );
	}

	/**
	 * Handle job completion to increment usage counter
	 *
	 * @param int $job_id Job ID.
	 */
	public function on_job_completed( $job_id ) {
		// Get job details to find user
		$job = teknup_ai_mastering()->jobs->get_job( $job_id );

		if ( ! $job || is_wp_error( $job ) ) {
			return;
		}

		// Increment usage for the user
		$this->increment_usage( $job->user_id );

		teknup_ai_mastering()->log( "Usage incremented for user {$job->user_id} after job {$job_id} completed", 'info' );
	}

	/**
	 * Check if user has feature
	 *
	 * @param string $feature Feature slug.
	 * @param int    $user_id User ID.
	 * @return bool True if user has feature.
	 */
	public function user_has_feature( $feature, $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$plan = $this->get_user_plan( $user_id );
		$limits = $this->get_plan_limits( $plan );

		if ( ! $limits ) {
			return false;
		}

		return in_array( $feature, $limits['features'], true );
	}

	/**
	 * Get user's remaining quota
	 *
	 * @param int $user_id User ID.
	 * @return array Quota information.
	 */
	public function get_user_quota( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$plan = $this->get_user_plan( $user_id );
		$limit = $this->get_user_monthly_limit( $user_id );
		$usage = $this->get_user_monthly_usage( $user_id );
		$limits = $this->get_plan_limits( $plan );

		return array(
			'plan' => $plan,
			'plan_name' => $limits ? $limits['name'] : 'None',
			'limit' => $limit,
			'usage' => $usage,
			'remaining' => $limit === -1 ? -1 : max( 0, $limit - $usage ),
			'unlimited' => $limit === -1,
			'percentage' => $limit === -1 ? 0 : min( 100, round( ( $usage / $limit ) * 100, 2 ) ),
		);
	}

	/**
	 * Check if user has an active subscription (paid or free trial)
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user has active subscription or free trial.
	 */
	public function has_active_subscription( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Check for active paid subscription
		$subscription = $this->get_user_subscription( $user_id );
		if ( $subscription ) {
			return true;
		}

		// Check for free trial (1 master now instead of 3)
		$trial_used = get_user_meta( $user_id, 'teknup_trial_used', true );
		$trial_count = get_user_meta( $user_id, 'teknup_trial_count', true );

		// User has free trial if they haven't used it or haven't reached the limit (1 master)
		if ( ! $trial_used || (int) $trial_count < 1 ) {
			return true;
		}

		// No active subscription or trial
		return false;
	}
}
