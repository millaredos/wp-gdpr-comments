<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MGC_Audit_Log {
	private const DEFAULT_PREVIEW_LENGTH = 120;

	public static function write( array $event ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'mgc_audit_log';

		$comment_id  = isset( $event['comment_id'] ) ? absint( $event['comment_id'] ) : 0;
		$user_id     = isset( $event['user_id'] ) ? absint( $event['user_id'] ) : get_current_user_id();
		$timestamp   = ! empty( $event['occurred_at'] ) ? sanitize_text_field( $event['occurred_at'] ) : current_time( 'mysql' );
		$change_type = isset( $event['change_type'] ) ? sanitize_key( $event['change_type'] ) : '';
		$old_hash    = isset( $event['old_hash'] ) ? sanitize_text_field( $event['old_hash'] ) : '';

		$old_value = isset( $event['old_value'] ) ? (string) $event['old_value'] : '';
		$new_value = isset( $event['new_value'] ) ? (string) $event['new_value'] : '';

		$min = self::minimize_values( $change_type, $old_value, $new_value );

		$wpdb->insert(
			$table_name,
			array(
				'comment_id'  => (int) $comment_id,
				'user_id'     => (int) $user_id,
				'modified_at' => $timestamp,
				'change_type' => $change_type,
				'old_value'   => $min['old_value'],
				'new_value'   => $min['new_value'],
				'old_hash'    => $old_hash,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

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

	public static function query( array $filters, $page, $per_page ) {
		global $wpdb;

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, absint( $per_page ) );
		$offset   = ( $page - 1 ) * $per_page;

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

	public static function get_distinct_change_types() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mgc_audit_log';

		return $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT change_type FROM %i ORDER BY change_type ASC", $table_name ) );
	}

	public static function get_distinct_months() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'mgc_audit_log';

		return $wpdb->get_results( $wpdb->prepare( "SELECT DISTINCT YEAR(modified_at) as year, MONTH(modified_at) as month FROM %i ORDER BY modified_at DESC", $table_name ) );
	}


	private static function minimize_values( $event_type, $old_value, $new_value ) {
		$event_type = sanitize_key( $event_type );

		if ( 'consent_given' === $event_type ) {
			return array(
				'old_value' => '',
				'new_value' => wp_json_encode(
					array(
						'message' => __( 'Consent given for Privacy Policy.', 'millaredos-gdpr-comments' ),
					)
				),
			);
		}

		if ( in_array( $event_type, array( 'email', 'access_request' ), true ) ) {
			return array(
				'old_value' => wp_json_encode(
					array(
						'hash_sha256' => self::hash_sha256( $old_value ),
						'mask'        => self::mask_email( $old_value ),
					)
				),
				'new_value' => wp_json_encode(
					array(
						'hash_sha256' => self::hash_sha256( $new_value ),
						'mask'        => self::mask_email( $new_value ),
					)
				),
			);
		}

		if ( 'author' === $event_type ) {
			return array(
				'old_value' => wp_json_encode(
					array(
						'hash_sha256' => self::hash_sha256( $old_value ),
						'preview'     => self::preview_text( $old_value, self::DEFAULT_PREVIEW_LENGTH ),
					)
				),
				'new_value' => wp_json_encode(
					array(
						'hash_sha256' => self::hash_sha256( $new_value ),
						'preview'     => self::preview_text( $new_value, self::DEFAULT_PREVIEW_LENGTH ),
					)
				),
			);
		}

		if ( 'content' === $event_type ) {
			$old_plain = wp_strip_all_tags( (string) $old_value );
			$new_plain = wp_strip_all_tags( (string) $new_value );

			return array(
				'old_value' => wp_json_encode(
					array(
						'hash_sha256' => self::hash_sha256( $old_plain ),
						'len'         => strlen( $old_plain ),
						'preview'     => self::preview_text( $old_plain, self::DEFAULT_PREVIEW_LENGTH ),
					)
				),
				'new_value' => wp_json_encode(
					array(
						'hash_sha256' => self::hash_sha256( $new_plain ),
						'len'         => strlen( $new_plain ),
						'preview'     => self::preview_text( $new_plain, self::DEFAULT_PREVIEW_LENGTH ),
					)
				),
			);
		}

		return array(
			'old_value' => self::preview_text( $old_value, self::DEFAULT_PREVIEW_LENGTH ),
			'new_value' => self::preview_text( $new_value, self::DEFAULT_PREVIEW_LENGTH ),
		);
	}

	private static function hash_sha256( $value ) {
		return hash( 'sha256', (string) $value );
	}

	private static function mask_email( $email ) {
		$email = (string) $email;
		$at    = strpos( $email, '@' );
		if ( false === $at ) {
			return '';
		}

		$local  = substr( $email, 0, $at );
		$domain = substr( $email, $at + 1 );

		$local_mask  = '' === $local ? '' : substr( $local, 0, 1 ) . '***';
		$domain_mask = '';

		if ( '' !== $domain ) {
			$dot = strrpos( $domain, '.' );
			if ( false !== $dot ) {
				$domain_name = substr( $domain, 0, $dot );
				$tld         = substr( $domain, $dot + 1 );
				$domain_mask = ( '' === $domain_name ? '' : substr( $domain_name, 0, 1 ) . '***' ) . '.' . ( '' === $tld ? '' : substr( $tld, 0, 1 ) . '**' );
			} else {
				$domain_mask = substr( $domain, 0, 1 ) . '***';
			}
		}

		return $local_mask . '@' . $domain_mask;
	}

	private static function preview_text( $value, $max_len ) {
		$value   = wp_strip_all_tags( (string) $value );
		$max_len = max( 0, (int) $max_len );
		if ( 0 === $max_len ) {
			return '';
		}

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			if ( mb_strlen( $value ) <= $max_len ) {
				return $value;
			}
			return mb_substr( $value, 0, $max_len ) . '…';
		}

		if ( strlen( $value ) <= $max_len ) {
			return $value;
		}
		return substr( $value, 0, $max_len ) . '...';
	}
}

