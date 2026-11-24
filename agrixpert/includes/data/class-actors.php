<?php
/**
 * Resolves the logged-in WordPress user to their marketplace "actor"
 * record (a farmer/buyer/supplier/cooperative row linked via user_id) —
 * the same linking model as the Supabase Portal, where a registration
 * record is claimed by whichever auth user first signs in with its
 * email. One WP user maps to at most one actor record per type; this
 * checks farmer -> buyer -> supplier -> cooperative in that order, since
 * that is the order the registration forms themselves are presented in.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Actors {

	const TYPES = array( 'farmer', 'buyer', 'supplier', 'cooperative' );

	/**
	 * Links the given WP user to a not-yet-claimed registration record
	 * sharing their email, if one exists and they aren't linked to one of
	 * that type yet. Safe to call on every login — it is a no-op once a
	 * record is already linked (user_id is only ever set once per row).
	 */
	public static function claim_by_email( $wp_user_id, $email ) {
		global $wpdb;
		foreach ( self::TYPES as $type ) {
			$table = AgriXpert_Verification::table_for( $type );
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table} SET user_id = %d WHERE email = %s AND user_id IS NULL",
				$wp_user_id, $email
			) );
		}
	}

	/** @return array{type:string,id:string,row:object}|null */
	public static function current_actor() {
		return self::actor_for_user( get_current_user_id() );
	}

	public static function actor_for_user( $wp_user_id ) {
		if ( ! $wp_user_id ) {
			return null;
		}
		global $wpdb;
		foreach ( self::TYPES as $type ) {
			$table = AgriXpert_Verification::table_for( $type );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $wp_user_id ) );
			if ( $row ) {
				return array( 'type' => $type, 'id' => $row->id, 'row' => $row );
			}
		}
		return null;
	}

	/**
	 * All registration records linked to this WP user, across all four
	 * types — a person can hold more than one role (e.g. a farmer who is
	 * also a cooperative lead contact), matching the live Portal's
	 * behaviour of loading every table rather than stopping at the first
	 * match. Used by the self-service Portal; marketplace "who am I
	 * acting as" still uses the single current_actor() above, matching
	 * its existing first-match behaviour.
	 *
	 * @return array<array{type:string,id:string,row:object}>
	 */
	public static function current_actors() {
		return self::actors_for_user( get_current_user_id() );
	}

	public static function actors_for_user( $wp_user_id ) {
		if ( ! $wp_user_id ) {
			return array();
		}
		global $wpdb;
		$actors = array();
		foreach ( self::TYPES as $type ) {
			$table = AgriXpert_Verification::table_for( $type );
			$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d LIMIT 1", $wp_user_id ) );
			if ( $row ) {
				$actors[] = array( 'type' => $type, 'id' => $row->id, 'row' => $row );
			}
		}
		return $actors;
	}

	/**
	 * True if the given actor (type + id) is one of this WP user's own
	 * linked records. Every Portal write handler must check this before
	 * acting on a client-supplied type/id pair — the id alone is never
	 * trusted as proof of ownership.
	 */
	public static function user_owns_actor( $wp_user_id, $actor_type, $actor_id ) {
		foreach ( self::actors_for_user( $wp_user_id ) as $actor ) {
			if ( $actor['type'] === $actor_type && (string) $actor['id'] === (string) $actor_id ) {
				return true;
			}
		}
		return false;
	}

	public static function owner_contact( $actor_type, $actor_id ) {
		$row = AgriXpert_Verification::get_entity( $actor_type, $actor_id );
		if ( ! $row ) {
			return null;
		}
		return array(
			'name'  => AgriXpert_Verification::display_name( $actor_type, $row ),
			'email' => AgriXpert_Verification::contact_email( $actor_type, $row ),
		);
	}
}

// Claim any matching registration record the moment a user logs in —
// mirrors the Supabase Portal's "link on first sign-in" behaviour.
add_action( 'wp_login', function ( $user_login, $user ) {
	if ( $user && $user->user_email ) {
		AgriXpert_Actors::claim_by_email( $user->ID, $user->user_email );
	}
}, 10, 2 );
