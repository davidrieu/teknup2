<?php
/**
 * Subscriptions class - Handles credit-based system
 *
 * @package Teknup\Core
 */

namespace Teknup\Core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Subscriptions class - Now manages credits instead of monthly subscriptions
 */
class Subscriptions {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Hook to give free trial credit to new users
		add_action( 'user_register', array( $this, 'give_free_trial_credit' ) );
	}

	/**
	 * Give free trial credit to new user
	 *
	 * @param int $user_id User ID.
	 */
	public function give_free_trial_credit( $user_id ) {
		// Give 1 free master credit
		update_user_meta( $user_id, 'teknup_credits', 1 );
		update_user_meta( $user_id, 'teknup_trial_given', true );

		if ( function_exists( 'teknup_ai_mastering' ) ) {
			teknup_ai_mastering()->log( "Free trial credit given to new user {$user_id}", 'info' );
		}
	}

	/**
	 * Get user's credits
	 *
	 * @param int $user_id User ID.
	 * @return int Number of credits.
	 */
	public function get_user_credits( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$credits = (int) get_user_meta( $user_id, 'teknup_credits', true );

		return max( 0, $credits );
	}

	/**
	 * Check if user has used their free trial
	 *
	 * @param int $user_id User ID.
	 * @return bool True if trial was used.
	 */
	public function has_used_trial( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Check if trial was given
		$trial_given = get_user_meta( $user_id, 'teknup_trial_given', true );

		// Check if any master was downloaded (indicates trial was used)
		$first_download = get_user_meta( $user_id, 'teknup_first_download', true );

		return $trial_given && $first_download;
	}

	/**
	 * Check if user has downloaded their first master
	 *
	 * @param int $user_id User ID.
	 * @return bool True if first master was downloaded.
	 */
	public function has_downloaded_first_master( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		return (bool) get_user_meta( $user_id, 'teknup_first_download', true );
	}

	/**
	 * Mark first master as downloaded
	 *
	 * @param int $user_id User ID.
	 */
	public function mark_first_download( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$already_downloaded = get_user_meta( $user_id, 'teknup_first_download', true );

		if ( ! $already_downloaded ) {
			update_user_meta( $user_id, 'teknup_first_download', current_time( 'mysql' ) );

			if ( function_exists( 'teknup_ai_mastering' ) ) {
				teknup_ai_mastering()->log( "User {$user_id} downloaded their first master", 'info' );
			}
		}
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

		$credits = $this->get_user_credits( $user_id );
		$has_used_trial = $this->has_used_trial( $user_id );

		// If no credits and trial was already used
		if ( $credits <= 0 && $has_used_trial ) {
			return new \WP_Error(
				'no_credits',
				__( 'You have no master credits left. Please purchase a credit pack to continue.', 'teknup-ai-mastering' )
			);
		}

		// If no credits and trial not given yet (shouldn't happen with auto-give on register, but safety check)
		if ( $credits <= 0 ) {
			// Give free trial credit
			$this->give_free_trial_credit( $user_id );
			$credits = 1;
		}

		return true;
	}

	/**
	 * Use one credit (decrement)
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function use_credit( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$credits = $this->get_user_credits( $user_id );

		if ( $credits > 0 ) {
			$new_credits = $credits - 1;
			update_user_meta( $user_id, 'teknup_credits', $new_credits );

			if ( function_exists( 'teknup_ai_mastering' ) ) {
				teknup_ai_mastering()->log( "User {$user_id} used 1 credit. Remaining: {$new_credits}", 'info' );
			}

			return true;
		}

		return false;
	}

	/**
	 * Add credits to user
	 *
	 * @param int $user_id User ID.
	 * @param int $amount Amount of credits to add.
	 * @return int New total credits.
	 */
	public function add_credits( $user_id, $amount ) {
		$current = $this->get_user_credits( $user_id );
		$new_total = $current + $amount;

		update_user_meta( $user_id, 'teknup_credits', $new_total );

		if ( function_exists( 'teknup_ai_mastering' ) ) {
			teknup_ai_mastering()->log( "Added {$amount} credits to user {$user_id}. New total: {$new_total}", 'info' );
		}

		return $new_total;
	}

	/**
	 * Get user's quota information
	 *
	 * @param int $user_id User ID.
	 * @return array Quota information.
	 */
	public function get_user_quota( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$credits = $this->get_user_credits( $user_id );
		$has_used_trial = $this->has_used_trial( $user_id );
		$has_downloaded_first = $this->has_downloaded_first_master( $user_id );

		$status = 'active';
		if ( $credits <= 0 && $has_used_trial ) {
			$status = 'no_credits';
		} elseif ( $credits === 1 && ! $has_downloaded_first ) {
			$status = 'trial';
		}

		return array(
			'credits' => $credits,
			'has_used_trial' => $has_used_trial,
			'has_downloaded_first' => $has_downloaded_first,
			'status' => $status,
			'can_upload' => $credits > 0,
		);
	}

	/**
	 * Check if user has an active subscription (has credits)
	 *
	 * @param int $user_id User ID.
	 * @return bool True if user has credits.
	 */
	public function has_active_subscription( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$credits = $this->get_user_credits( $user_id );

		return $credits > 0;
	}

	/**
	 * Increment usage - now just uses a credit
	 * Kept for backward compatibility
	 *
	 * @param int $user_id User ID.
	 * @return bool True on success.
	 */
	public function increment_usage( $user_id = null ) {
		return $this->use_credit( $user_id );
	}

	/**
	 * Get available credit packs
	 *
	 * @return array Credit packs with product info.
	 */
	public function get_credit_packs() {
		$products = get_option( 'teknup_credit_products', array() );
		$packs = array();

		foreach ( $products as $slug => $product_id ) {
			$product = wc_get_product( $product_id );

			if ( ! $product ) {
				continue;
			}

			$credits = $product->get_meta( '_teknup_credits', true );

			$packs[] = array(
				'id' => $product_id,
				'slug' => $slug,
				'name' => $product->get_name(),
				'price' => $product->get_price(),
				'credits' => (int) $credits,
				'description' => $product->get_description(),
				'features' => $this->parse_features( $product->get_short_description() ),
				'url' => $product->get_permalink(),
			);
		}

		return $packs;
	}

	/**
	 * Parse features from HTML list
	 *
	 * @param string $html HTML content.
	 * @return array Features.
	 */
	private function parse_features( $html ) {
		$features = array();

		if ( preg_match_all( '/<li>(.*?)<\/li>/s', $html, $matches ) ) {
			$features = array_map( 'wp_strip_all_tags', $matches[1] );
		}

		return $features;
	}
}
