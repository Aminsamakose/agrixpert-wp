<?php
/**
 * Cooperative member roster — the WordPress equivalent of the live
 * Portal's "Members" section, where a logged-in cooperative adds/removes
 * its own members. Previously missing entirely from this rewrite (the
 * cooperative_members table existed in the schema with no code anywhere
 * touching it); added as part of the Portal self-service area.
 *
 * Soft-delete only: "removing" a member sets member_status = 'removed'
 * rather than deleting the row, matching the live app's behaviour and
 * keeping a full roster history available if ever needed.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Cooperative_Members {

	public static function get_active_members( $cooperative_id ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'cooperative_members' );
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM {$table} WHERE cooperative_id = %s AND member_status = 'active' ORDER BY joined_at ASC",
			$cooperative_id
		) );
	}

	/**
	 * $wp_user_id must own the cooperative record being added to —
	 * checked here, not left to the caller, for the same reason as
	 * AgriXpert_Registrations::update_own_record().
	 */
	public static function add_member( $cooperative_id, $wp_user_id, $member_name ) {
		if ( ! AgriXpert_Actors::user_owns_actor( $wp_user_id, 'cooperative', $cooperative_id ) ) {
			return new WP_Error( 'agx_forbidden', __( 'You do not have permission to manage members for this cooperative.', 'agrixpert' ) );
		}
		$member_name = sanitize_text_field( trim( (string) $member_name ) );
		if ( $member_name === '' ) {
			return new WP_Error( 'agx_required', __( 'Member name is required.', 'agrixpert' ) );
		}
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'cooperative_members' ), array(
			'id'             => $id,
			'cooperative_id' => $cooperative_id,
			'member_name'    => $member_name,
			'role'           => 'member',
			'member_status'  => 'active',
		) );
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}

	/**
	 * $cooperative_id is passed in and checked against the member row
	 * itself (not just against the current user) so a member id can
	 * never be used to remove a row belonging to a different
	 * cooperative, even one the user also happens to own.
	 */
	public static function remove_member( $member_id, $cooperative_id, $wp_user_id ) {
		if ( ! AgriXpert_Actors::user_owns_actor( $wp_user_id, 'cooperative', $cooperative_id ) ) {
			return new WP_Error( 'agx_forbidden', __( 'You do not have permission to manage members for this cooperative.', 'agrixpert' ) );
		}
		global $wpdb;
		$table = AgriXpert_Schema::table( 'cooperative_members' );
		$ok    = $wpdb->update(
			$table,
			array( 'member_status' => 'removed' ),
			array( 'id' => $member_id, 'cooperative_id' => $cooperative_id )
		);
		// $wpdb->update() returns an int (rows affected) on success, or false
		// on a genuine DB error — 0 is a valid, non-error return, but here it
		// specifically means $member_id didn't match a row under this
		// cooperative_id (wrong id, already removed, or a forged/mismatched
		// pair). Reporting that as success would hide a real problem rather
		// than a harmless no-op, so it's treated as an error here.
		if ( $ok === false ) {
			return new WP_Error( 'agx_update_failed', $wpdb->last_error );
		}
		if ( (int) $ok === 0 ) {
			return new WP_Error( 'agx_not_found', __( 'That member could not be found for this cooperative.', 'agrixpert' ) );
		}
		return true;
	}
}
