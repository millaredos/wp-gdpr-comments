<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the audit logging system.
 */
class MGC_Audit_Log {

	/**
	 * Write an event to the audit log.
	 *
	 * @param array $event {
	 *     @type int    $comment_id  Optional. The comment ID.
	 *     @type int    $user_id     Optional. The user ID.
	 *     @type string $change_type The type of change.
	 *     @type string $old_value   The old value.
	 *     @type string $new_value   The new value.
	 *     @type string $old_hash    The previous integrity hash.
	 *     @type string $occurred_at Optional. The timestamp.
	 * }
	 */
	public static function write( array $event ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'mgc_audit_log';

		$comment_id  = isset( $event['comment_id'] ) ? absint( $event['comment_id'] ) : 0;
		$user_id     = isset( $event['user_id'] ) ? absint( $event['user_id'] ) : get_current_user_id();
		$timestamp   = ! empty( $event['occurred_at'] ) ? sanitize_text_field( $event['occurred_at'] ) : current_time( 'mysql' );
		$change_type = isset( $event['change_type'] ) ? sanitize_key( $event['change_type'] ) : '';
		$old_hash    = isset( $event['old_hash'] ) ? sanitize_text_field( $event['old_hash'] ) : '';

		$old_value = isset( $event['old_value'] ) ? $event['old_value'] : '';
		$new_value = isset( $event['new_value'] ) ? $event['new_value'] : '';

		// Handle non-scalar values for complex settings
		if ( ! is_scalar( $old_value ) ) {
			$old_value = wp_json_encode( $old_value );
		}
		if ( ! is_scalar( $new_value ) ) {
			$new_value = wp_json_encode( $new_value );
		}

		$wpdb->insert(
			$table_name,
			array(
				'comment_id'     => (int) $comment_id,
				'user_id'        => (int) $user_id,
				'modified_at'    => $timestamp,
				'change_type'    => $change_type,
				'old_value'      => $old_value,
				'new_value'      => $new_value,
				'integrity_hash' => $old_hash, // Corrected column name
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Simplified write method.
	 */
	public static function write_simple( $comment_id, $change_type, $old_value = '', $new_value = '' ) {
		$old_hash = get_comment_meta( $comment_id, '_mgc_consent_proof_hash', true );

		self::write(
			array(
				'comment_id'  => $comment_id,
				'user_id'     => get_current_user_id(),
				'occurred_at' => current_time( 'mysql' ),
				'change_type' => $change_type,
				'old_value'   => $old_value,
				'new_value'   => $new_value,
				'old_hash'    => $old_hash,
			)
		);
	}

	/**
	 * Query audit logs with filters.
	 */
	public static function query( array $filters, $page, $per_page ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'mgc_audit_log';
		$page       = max( 1, absint( $page ) );
		$per_page   = max( 1, absint( $per_page ) );
		$offset     = ( $page - 1 ) * $per_page;

		$where = 'WHERE 1=1';
		$args  = array();

		$m_date      = isset( $filters['m'] ) ? sanitize_text_field( $filters['m'] ) : '';
		$change_type = isset( $filters['change_type'] ) ? sanitize_key( $filters['change_type'] ) : '';

		if ( ! empty( $m_date ) ) {
			$year    = (int) substr( $m_date, 0, 4 );
			$month   = (int) substr( $m_date, 4, 2 );
			$where  .= ' AND YEAR(modified_at) = %d AND MONTH(modified_at) = %d';
			$args[]  = $year;
			$args[]  = $month;
		}

		if ( ! empty( $change_type ) ) {
			$where  .= ' AND change_type = %s';
			$args[]  = $change_type;
		}

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i $where", $table_name, ...$args ) );

		$data_args = array_merge( array( $table_name ), $args, array( $per_page, $offset ) );
		$items     = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i $where ORDER BY modified_at DESC LIMIT %d OFFSET %d", ...$data_args ) );

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

	/**
	 * Get distinct change types for filtering.
	 */
	public static function get_distinct_change_types() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mgc_audit_log';

		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT change_type FROM %i ORDER BY change_type ASC", $table_name ) );
	}

	/**
	 * Get distinct months for filtering.
	 */
	public static function get_distinct_months() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mgc_audit_log';

		return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT YEAR(modified_at) as year, MONTH(modified_at) as month FROM %i ORDER BY modified_at DESC", $table_name ) );
	}

	/**
	 * Helper: Irreversible SHA-256 hash.
	 */
	public static function hash_sha256( $value ) {
		return hash( 'sha256', (string) $value );
	}
}
