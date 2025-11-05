<?php
/**
 * Dashboard class - Handles admin dashboard
 *
 * @package Teknup\Admin
 */

namespace Teknup\Admin;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard class
 */
class Dashboard {

	/**
	 * Render dashboard page
	 */
	public function render() {
		include TEKNUP_PLUGIN_DIR . 'templates/admin/dashboard.php';
	}

	/**
	 * Get dashboard data
	 *
	 * @return array Dashboard data.
	 */
	public function get_dashboard_data() {
		$stats = teknup_ai_mastering()->jobs->get_statistics();

		// Get recent jobs
		$recent_jobs = teknup_ai_mastering()->jobs->get_all_jobs(
			array(
				'limit' => 10,
				'orderby' => 'created_at',
				'order' => 'DESC',
			)
		);

		// Get subscription stats
		$subscription_stats = $this->get_subscription_stats();

		// Get daily usage for the last 30 days
		$daily_usage = $this->get_daily_usage();

		return array(
			'stats' => $stats,
			'recent_jobs' => $recent_jobs,
			'subscription_stats' => $subscription_stats,
			'daily_usage' => $daily_usage,
		);
	}

	/**
	 * Get subscription statistics
	 *
	 * @return array Subscription stats.
	 */
	private function get_subscription_stats() {
		$stats = array(
			'total' => 0,
			'starter' => 0,
			'pro' => 0,
			'label' => 0,
		);

		if ( ! function_exists( 'wcs_get_subscriptions' ) ) {
			return $stats;
		}

		$subscriptions = wcs_get_subscriptions(
			array(
				'status' => 'active',
				'subscriptions_per_page' => -1,
			)
		);

		$stats['total'] = count( $subscriptions );

		foreach ( $subscriptions as $subscription ) {
			foreach ( $subscription->get_items() as $item ) {
				$product_name = strtolower( $item->get_name() );

				if ( strpos( $product_name, 'label' ) !== false ) {
					$stats['label']++;
				} elseif ( strpos( $product_name, 'pro' ) !== false ) {
					$stats['pro']++;
				} elseif ( strpos( $product_name, 'starter' ) !== false ) {
					$stats['starter']++;
				}
			}
		}

		return $stats;
	}

	/**
	 * Get daily usage for the last 30 days
	 *
	 * @return array Daily usage data.
	 */
	private function get_daily_usage() {
		global $wpdb;

		$table_name = $wpdb->prefix . TEKNUP_TABLE_JOBS;

		$results = $wpdb->get_results(
			"SELECT DATE(created_at) as date, COUNT(*) as count
			FROM {$table_name}
			WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
			GROUP BY DATE(created_at)
			ORDER BY date ASC",
			ARRAY_A
		);

		// Fill in missing days with zero
		$usage = array();
		for ( $i = 29; $i >= 0; $i-- ) {
			$date = date( 'Y-m-d', strtotime( "-{$i} days" ) );
			$usage[ $date ] = 0;
		}

		foreach ( $results as $row ) {
			$usage[ $row['date'] ] = (int) $row['count'];
		}

		return $usage;
	}
}
