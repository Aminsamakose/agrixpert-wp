<?php
/**
 * Data access for the admin verification queues (farmers, buyers,
 * suppliers, cooperatives) and enquiries. Mirrors the decide()/
 * bulkDecide() logic in the live Next.js admin dashboard: every status
 * change is written to status_history and activity_log for audit parity,
 * and a `agrixpert_verification_decided` action fires so Phase 6
 * (notifications) can send the verified/rejected email without this
 * class needing to know anything about email.
 *
 * Every public method here assumes the caller has already checked
 * current_user_can( AgriXpert_Roles::CAP_VERIFY ) — this class does not
 * check capabilities itself, so it must never be called directly from
 * anything reachable without that check (see class-admin-menu.php and
 * class-verification-list-table.php, which are the only callers).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Verification {

	/** Entity types with a verification_status column. */
	const VERIFIABLE_TYPES = array( 'farmer', 'buyer', 'supplier', 'cooperative' );

	public static function table_for( $entity_type ) {
		$map = array(
			'farmer'      => 'farmers',
			'buyer'       => 'buyers',
			'supplier'    => 'suppliers',
			'cooperative' => 'cooperatives',
			'enquiry'     => 'enquiries',
		);
		if ( ! isset( $map[ $entity_type ] ) ) {
			return null;
		}
		return AgriXpert_Schema::table( $map[ $entity_type ] );
	}

	/** Display name column(s) for a row, entity-type aware — matches the
	 *  differing name fields across farmers/buyers/suppliers/cooperatives
	 *  that the Next.js admin dashboard already has to account for. */
	public static function display_name( $entity_type, $row ) {
		switch ( $entity_type ) {
			case 'farmer':
				return $row->full_name;
			case 'buyer':
			case 'supplier':
				return $row->company_name . ' (' . $row->contact_name . ')';
			case 'cooperative':
				return $row->name . ' (' . $row->lead_contact_name . ')';
			case 'enquiry':
				return $row->name;
			default:
				return '';
		}
	}

	public static function contact_email( $entity_type, $row ) {
		return isset( $row->email ) ? $row->email : null;
	}

	/**
	 * @param array $args { search, status_filter, orderby, order, paged, per_page }
	 * @return array{items: object[], total: int}
	 */
	public static function get_entities( $entity_type, array $args = array() ) {
		global $wpdb;
		$table = self::table_for( $entity_type );
		if ( ! $table ) {
			return array( 'items' => array(), 'total' => 0 );
		}

		$is_verifiable = in_array( $entity_type, self::VERIFIABLE_TYPES, true );
		$status_column = $is_verifiable ? 'verification_status' : 'status';

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status_filter'] ) && $args['status_filter'] !== 'all' ) {
			$where[]  = "{$status_column} = %s";
			$params[] = $args['status_filter'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$search_cols = self::searchable_columns( $entity_type );
			$clauses  = array();
			foreach ( $search_cols as $col ) {
				$clauses[] = "{$col} LIKE %s";
				$params[]  = $like;
			}
			$where[] = '(' . implode( ' OR ', $clauses ) . ')';
		}

		$where_sql = implode( ' AND ', $where );

		$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$paged    = isset( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$orderby = in_array( $args['orderby'] ?? '', array( 'created_at', $status_column ), true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ?? 'DESC' ) === 'ASC' ? 'ASC' : 'DESC';

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) $wpdb->get_var( $params ? $wpdb->prepare( $count_sql, $params ) : $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$list_sql = "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$items    = $wpdb->get_results( $wpdb->prepare( $list_sql, array_merge( $params, array( $per_page, $offset ) ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return array( 'items' => $items ?: array(), 'total' => $total );
	}

	private static function searchable_columns( $entity_type ) {
		switch ( $entity_type ) {
			case 'farmer':
				return array( 'full_name', 'phone', 'email', 'location' );
			case 'buyer':
			case 'supplier':
				return array( 'company_name', 'contact_name', 'email', 'phone' );
			case 'cooperative':
				return array( 'name', 'lead_contact_name', 'email', 'phone' );
			case 'enquiry':
				return array( 'name', 'email', 'message' );
			default:
				return array();
		}
	}

	public static function get_entity( $entity_type, $id ) {
		global $wpdb;
		$table = self::table_for( $entity_type );
		if ( ! $table ) {
			return null;
		}
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %s", $id ) );
	}

	/**
	 * Verify/reject/reset a single registration. $decision is one of
	 * 'verified' | 'rejected' | 'pending' (reset), matching the Next.js
	 * admin's decide() values exactly.
	 */
	public static function decide( $entity_type, $id, $decision, $user_id ) {
		if ( ! in_array( $entity_type, self::VERIFIABLE_TYPES, true ) ) {
			return new WP_Error( 'agx_invalid_entity', __( 'This entity type cannot be verified.', 'agrixpert' ) );
		}
		global $wpdb;
		$table = self::table_for( $entity_type );
		$row   = self::get_entity( $entity_type, $id );
		if ( ! $row ) {
			return new WP_Error( 'agx_not_found', __( 'Record not found.', 'agrixpert' ) );
		}
		$old_status = $row->verification_status;

		$update = array( 'verification_status' => $decision );
		if ( $decision === 'pending' ) {
			$update['verified_by'] = null;
			$update['verified_at'] = null;
		} else {
			$update['verified_by'] = $user_id;
			$update['verified_at'] = current_time( 'mysql' );
		}

		$ok = $wpdb->update( $table, $update, array( 'id' => $id ) );
		if ( $ok === false ) {
			return new WP_Error( 'agx_update_failed', $wpdb->last_error );
		}

		self::log_status_history( $entity_type, $id, $old_status, $decision, $user_id );
		self::log_activity( $user_id, 'verification_' . $decision, $entity_type, $id, array( 'verification_status' => $old_status ), $update );

		if ( $decision === 'verified' || $decision === 'rejected' ) {
			/**
			 * @param string $entity_type
			 * @param object $row       Row as it was BEFORE this decision (still has old contact fields).
			 * @param string $decision  'verified' | 'rejected'
			 */
			do_action( 'agrixpert_verification_decided', $entity_type, $row, $decision );
		}

		return true;
	}

	public static function bulk_decide( $entity_type, array $ids, $decision, $user_id ) {
		$results = array();
		foreach ( $ids as $id ) {
			$results[ $id ] = self::decide( $entity_type, $id, $decision, $user_id );
		}
		return $results;
	}

	/** Enquiries use a plain `status` lifecycle (new/in_progress/resolved/archived), not verification. */
	public static function set_enquiry_status( $id, $status, $user_id ) {
		global $wpdb;
		$table = self::table_for( 'enquiry' );
		$row   = self::get_entity( 'enquiry', $id );
		if ( ! $row ) {
			return new WP_Error( 'agx_not_found', __( 'Record not found.', 'agrixpert' ) );
		}
		$ok = $wpdb->update( $table, array( 'status' => $status ), array( 'id' => $id ) );
		if ( $ok === false ) {
			return new WP_Error( 'agx_update_failed', $wpdb->last_error );
		}
		self::log_status_history( 'enquiry', $id, $row->status, $status, $user_id );
		self::log_activity( $user_id, 'status_changed', 'enquiry', $id, array( 'status' => $row->status ), array( 'status' => $status ) );
		return true;
	}

	private static function log_status_history( $entity_type, $entity_id, $old_status, $new_status, $user_id ) {
		global $wpdb;
		$wpdb->insert(
			AgriXpert_Schema::table( 'status_history' ),
			array(
				'id'          => wp_generate_uuid4(),
				'entity_type' => $entity_type,
				'entity_id'   => $entity_id,
				'old_status'  => $old_status,
				'new_status'  => $new_status,
				'changed_by'  => $user_id,
			)
		);
	}

	private static function log_activity( $user_id, $action, $entity_type, $entity_id, $before, $after ) {
		global $wpdb;
		$wpdb->insert(
			AgriXpert_Schema::table( 'activity_log' ),
			array(
				'id'           => wp_generate_uuid4(),
				'actor_id'     => $user_id,
				'action'       => $action,
				'entity_type'  => $entity_type,
				'entity_id'    => $entity_id,
				'performed_by' => $user_id,
				'before_state' => wp_json_encode( $before ),
				'after_state'  => wp_json_encode( $after ),
			)
		);
	}
}
