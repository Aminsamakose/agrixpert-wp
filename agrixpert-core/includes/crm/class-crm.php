<?php
/**
 * CRM data access: pipeline stage, notes, and tasks against any of the
 * five entity types. Mirrors CrmPanel.tsx and FollowUpsManager.tsx from
 * the live Next.js admin dashboard — same stages, same task fields
 * (including staff assignment by admin email), same cross-entity
 * follow-ups query.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_CRM {

	const STAGES = array( 'new', 'contacted', 'engaged', 'active', 'dormant' );

	// ---- Pipeline stage ---------------------------------------------------

	public static function get_stage( $entity_type, $entity_id ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'crm_pipeline' );
		$stage = $wpdb->get_var( $wpdb->prepare(
			"SELECT stage FROM {$table} WHERE entity_type = %s AND entity_id = %s",
			$entity_type, $entity_id
		) );
		return $stage ?: 'new';
	}

	public static function set_stage( $entity_type, $entity_id, $stage, $user_id ) {
		if ( ! in_array( $stage, self::STAGES, true ) ) {
			return new WP_Error( 'agx_invalid_stage', __( 'Invalid pipeline stage.', 'agrixpert' ) );
		}
		global $wpdb;
		$table = AgriXpert_Schema::table( 'crm_pipeline' );
		$ok    = $wpdb->replace( $table, array(
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
			'stage'       => $stage,
			'updated_by'  => $user_id,
		) );
		return $ok === false ? new WP_Error( 'agx_update_failed', $wpdb->last_error ) : true;
	}

	/** Stage for every row of a given entity type, keyed by entity_id — used by the list table to show a Stage column without an N+1 query per row. */
	public static function get_stage_map( $entity_type ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'crm_pipeline' );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT entity_id, stage FROM {$table} WHERE entity_type = %s", $entity_type
		) );
		$map = array();
		foreach ( $rows as $row ) {
			$map[ $row->entity_id ] = $row->stage;
		}
		return $map;
	}

	// ---- Notes --------------------------------------------------------

	public static function get_notes( $entity_type, $entity_id ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'crm_notes' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE entity_type = %s AND entity_id = %s ORDER BY created_at DESC",
			$entity_type, $entity_id
		) );
	}

	public static function add_note( $entity_type, $entity_id, $body, $user_id ) {
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'crm_notes' ), array(
			'id'          => $id,
			'entity_type' => $entity_type,
			'entity_id'   => $entity_id,
			'body'        => $body,
			'created_by'  => $user_id,
		) );
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}

	public static function update_note( $note_id, $body ) {
		global $wpdb;
		$ok = $wpdb->update( AgriXpert_Schema::table( 'crm_notes' ), array( 'body' => $body ), array( 'id' => $note_id ) );
		return $ok === false ? new WP_Error( 'agx_update_failed', $wpdb->last_error ) : true;
	}

	public static function delete_note( $note_id ) {
		global $wpdb;
		$wpdb->delete( AgriXpert_Schema::table( 'crm_notes' ), array( 'id' => $note_id ) );
		return true;
	}

	// ---- Tasks --------------------------------------------------------

	public static function get_tasks( $entity_type, $entity_id ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'crm_tasks' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE entity_type = %s AND entity_id = %s ORDER BY status ASC, due_date IS NULL, due_date ASC",
			$entity_type, $entity_id
		) );
	}

	public static function add_task( $entity_type, $entity_id, $entity_label, $title, $due_date, $assigned_to, $user_id ) {
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'crm_tasks' ), array(
			'id'            => $id,
			'entity_type'   => $entity_type,
			'entity_id'     => $entity_id,
			'entity_label'  => $entity_label,
			'title'         => $title,
			'due_date'      => $due_date ?: null,
			'assigned_to'   => $assigned_to ?: null,
			'created_by'    => $user_id,
		) );
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}

	public static function update_task( $task_id, $title, $due_date, $assigned_to ) {
		global $wpdb;
		$ok = $wpdb->update( AgriXpert_Schema::table( 'crm_tasks' ), array(
			'title'       => $title,
			'due_date'    => $due_date ?: null,
			'assigned_to' => $assigned_to ?: null,
		), array( 'id' => $task_id ) );
		return $ok === false ? new WP_Error( 'agx_update_failed', $wpdb->last_error ) : true;
	}

	public static function set_task_status( $task_id, $status ) {
		global $wpdb;
		$data = array( 'status' => $status );
		$data['completed_at'] = $status === 'done' ? current_time( 'mysql' ) : null;
		$wpdb->update( AgriXpert_Schema::table( 'crm_tasks' ), $data, array( 'id' => $task_id ) );
		return true;
	}

	public static function delete_task( $task_id ) {
		global $wpdb;
		$wpdb->delete( AgriXpert_Schema::table( 'crm_tasks' ), array( 'id' => $task_id ) );
		return true;
	}

	/**
	 * Cross-entity follow-ups view, matching FollowUpsManager.tsx: open
	 * tasks (optionally completed too), optionally restricted to one
	 * assignee, newest due date first with nulls last.
	 */
	public static function get_followups( array $args = array() ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'crm_tasks' );

		$where  = array();
		$params = array();

		if ( empty( $args['show_completed'] ) ) {
			$where[] = 'status = %s';
			$params[] = 'open';
		}
		if ( ! empty( $args['assigned_to'] ) ) {
			$where[] = 'LOWER(assigned_to) = LOWER(%s)';
			$params[] = $args['assigned_to'];
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$sql       = "SELECT * FROM {$table} {$where_sql} ORDER BY status ASC, due_date IS NULL, due_date ASC";

		return $params
			? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			: $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/** Used by the cron digest (Phase 6) and the Follow-ups screen's "due" badge. */
	public static function get_due_followup_tasks() {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'crm_tasks' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE status = %s AND due_date IS NOT NULL AND due_date <= %s ORDER BY assigned_to, due_date",
			'open', current_time( 'Y-m-d' )
		) );
	}

	/** All admin emails, for the assignee dropdown. */
	public static function get_staff_emails() {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'admins' );
		return $wpdb->get_col( "SELECT email FROM {$table} ORDER BY email ASC" );
	}
}
