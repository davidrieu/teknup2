<?php
/**
 * Database class - Handles database operations
 *
 * @package Teknup\Core
 */

namespace Teknup\Core;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database class
 */
class Database {

	/**
	 * Get table name
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . TEKNUP_TABLE_JOBS;
	}

	/**
	 * Insert a new job
	 *
	 * @param array $data Job data.
	 * @return int|false Job ID on success, false on failure.
	 */
	public function insert_job( $data ) {
		global $wpdb;

		$defaults = array(
			'user_id' => get_current_user_id(),
			'status' => 'pending',
			'created_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$this->get_table_name(),
			$data,
			array(
				'%d', // user_id
				'%s', // dolby_job_id
				'%s', // original_filename
				'%s', // original_filepath
				'%s', // mastered_filepath
				'%d', // file_size
				'%s', // status
				'%s', // intensity
				'%s', // genre
				'%f', // target_lufs
				'%s', // error_message
				'%s', // dolby_response
				'%s', // created_at
				'%s', // uploaded_at
				'%s', // sent_to_dolby_at
				'%s', // processing_started_at
				'%s', // completed_at
				'%s', // failed_at
				'%s', // notified_at
			)
		);

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a job
	 *
	 * @param int   $job_id Job ID.
	 * @param array $data Job data to update.
	 * @return bool True on success, false on failure.
	 */
	public function update_job( $job_id, $data ) {
		global $wpdb;

		return (bool) $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => $job_id ),
			null,
			array( '%d' )
		);
	}

	/**
	 * Get a job by ID
	 *
	 * @param int $job_id Job ID.
	 * @return object|null Job object or null if not found.
	 */
	public function get_job( $job_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE id = %d",
				$job_id
			)
		);
	}

	/**
	 * Get a job by Dolby job ID
	 *
	 * @param string $dolby_job_id Dolby job ID.
	 * @return object|null Job object or null if not found.
	 */
	public function get_job_by_dolby_id( $dolby_job_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE dolby_job_id = %s",
				$dolby_job_id
			)
		);
	}

	/**
	 * Get jobs by user ID
	 *
	 * @param int   $user_id User ID.
	 * @param array $args Query arguments.
	 * @return array Array of job objects.
	 */
	public function get_user_jobs( $user_id, $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status' => null,
			'orderby' => 'created_at',
			'order' => 'DESC',
			'limit' => 20,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		$order_by = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM {$this->get_table_name()}
				  {$where}
				  ORDER BY {$order_by}
				  LIMIT {$limit} OFFSET {$offset}";

		return $wpdb->get_results( $query );
	}

	/**
	 * Get total jobs count for user
	 *
	 * @param int    $user_id User ID.
	 * @param string $status Optional status filter.
	 * @return int Jobs count.
	 */
	public function get_user_jobs_count( $user_id, $status = null ) {
		global $wpdb;

		$where = $wpdb->prepare( 'WHERE user_id = %d', $user_id );

		if ( ! empty( $status ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->get_table_name()} {$where}"
		);
	}

	/**
	 * Get jobs count for user in current month
	 *
	 * @param int $user_id User ID.
	 * @return int Jobs count.
	 */
	public function get_user_monthly_jobs_count( $user_id ) {
		global $wpdb;

		$start_of_month = date( 'Y-m-01 00:00:00' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->get_table_name()}
				WHERE user_id = %d
				AND status = 'completed'
				AND completed_at >= %s",
				$user_id,
				$start_of_month
			)
		);
	}

	/**
	 * Get pending jobs
	 *
	 * @param int $limit Number of jobs to retrieve.
	 * @return array Array of job objects.
	 */
	public function get_pending_jobs( $limit = 50 ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()}
				WHERE status IN ('processing', 'sent_to_dolby')
				ORDER BY created_at ASC
				LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get all jobs (admin)
	 *
	 * @param array $args Query arguments.
	 * @return array Array of job objects.
	 */
	public function get_all_jobs( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'user_id' => null,
			'status' => null,
			'orderby' => 'created_at',
			'order' => 'DESC',
			'limit' => 20,
			'offset' => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = 'WHERE 1=1';

		if ( ! empty( $args['user_id'] ) ) {
			$where .= $wpdb->prepare( ' AND user_id = %d', $args['user_id'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		$order_by = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM {$this->get_table_name()}
				  {$where}
				  ORDER BY {$order_by}
				  LIMIT {$limit} OFFSET {$offset}";

		return $wpdb->get_results( $query );
	}

	/**
	 * Get total jobs count (admin)
	 *
	 * @param array $args Query arguments.
	 * @return int Jobs count.
	 */
	public function get_all_jobs_count( $args = array() ) {
		global $wpdb;

		$where = 'WHERE 1=1';

		if ( ! empty( $args['user_id'] ) ) {
			$where .= $wpdb->prepare( ' AND user_id = %d', $args['user_id'] );
		}

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->get_table_name()} {$where}"
		);
	}

	/**
	 * Delete a job
	 *
	 * @param int $job_id Job ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_job( $job_id ) {
		global $wpdb;

		return (bool) $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => $job_id ),
			array( '%d' )
		);
	}

	/**
	 * Delete old jobs
	 *
	 * @param int $days Number of days.
	 * @return int Number of deleted jobs.
	 */
	public function delete_old_jobs( $days = 30 ) {
		global $wpdb;

		$date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->get_table_name()} WHERE created_at < %s",
				$date
			)
		);
	}

	/**
	 * Get statistics
	 *
	 * @return array Statistics array.
	 */
	public function get_statistics() {
		global $wpdb;

		$stats = array();

		// Total jobs
		$stats['total_jobs'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->get_table_name()}"
		);

		// Jobs this month
		$start_of_month = date( 'Y-m-01 00:00:00' );
		$stats['jobs_this_month'] = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE created_at >= %s",
				$start_of_month
			)
		);

		// Completed jobs
		$stats['completed_jobs'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE status = 'completed'"
		);

		// Failed jobs
		$stats['failed_jobs'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE status = 'failed'"
		);

		// Average processing time (in seconds)
		$stats['avg_processing_time'] = (int) $wpdb->get_var(
			"SELECT AVG(TIMESTAMPDIFF(SECOND, sent_to_dolby_at, completed_at))
			FROM {$this->get_table_name()}
			WHERE status = 'completed'
			AND sent_to_dolby_at IS NOT NULL
			AND completed_at IS NOT NULL"
		);

		// Success rate
		if ( $stats['total_jobs'] > 0 ) {
			$stats['success_rate'] = round( ( $stats['completed_jobs'] / $stats['total_jobs'] ) * 100, 2 );
		} else {
			$stats['success_rate'] = 0;
		}

		return $stats;
	}
}
