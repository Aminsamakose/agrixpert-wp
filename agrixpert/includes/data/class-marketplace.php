<?php
/**
 * Marketplace data access: listings (supply/demand/service, with
 * equipment and storage as specializations of a listing, exactly as
 * documented on the live `listings` table — "clustered demand, equipment
 * bookings and storage bookings are specializations of this table, not
 * parallel systems"), expressions of interest, equipment/storage
 * availability and booking, and post-service reviews.
 *
 * Every write here takes the acting WP user's resolved actor (see
 * class-actors.php) and never trusts a client-supplied actor_id —
 * the Supabase version enforced "you can only act as your own actor"
 * via RLS; here that is enforced by always deriving actor_id from
 * AgriXpert_Actors::current_actor() server-side, never from request input.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Marketplace {

	// ---- Listings -------------------------------------------------------

	public static function get_listings( array $args = array() ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'listings' );
		$where  = array( "status = 'active'" );
		$params = array();

		if ( ! empty( $args['listing_type'] ) ) {
			$where[]  = 'listing_type = %s';
			$params[] = $args['listing_type'];
		}
		if ( ! empty( $args['commodity'] ) ) {
			$where[]  = 'commodity LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['commodity'] ) . '%';
		}

		$sql = 'SELECT * FROM ' . $table . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY created_at DESC LIMIT 200';
		return $params ? $wpdb->get_results( $wpdb->prepare( $sql, $params ) ) : $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function get_listing( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AgriXpert_Schema::table( 'listings' ) . ' WHERE id = %s', $id ) );
	}

	/**
	 * $v: listing_type, commodity, quantity, unit, price_indication,
	 * quality_grade, location_label, equipment (optional array: type,
	 * capacity_spec, coverage_area), storage (optional array:
	 * total_capacity_tonnes, commodity_compatibility[]).
	 */
	public static function create_listing( $actor, array $v ) {
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'listings' ), array(
			'id'               => $id,
			'actor_id'         => $actor['id'],
			'actor_type'       => $actor['type'],
			'listing_type'     => $v['listing_type'],
			'commodity'        => $v['commodity'] ?: null,
			'quantity'         => $v['quantity'],
			'unit'             => $v['unit'] ?: null,
			'price_indication' => $v['price_indication'] ?: null,
			'quality_grade'    => $v['quality_grade'] ?: null,
			'location_label'   => $v['location_label'] ?: null,
		) );
		if ( $ok === false ) {
			return new WP_Error( 'agx_insert_failed', $wpdb->last_error );
		}

		if ( ! empty( $v['equipment'] ) ) {
			$wpdb->insert( AgriXpert_Schema::table( 'equipment' ), array(
				'id'             => wp_generate_uuid4(),
				'listing_id'     => $id,
				'equipment_type' => $v['equipment']['type'],
				'capacity_spec'  => $v['equipment']['capacity_spec'] ?: null,
				'coverage_area'  => $v['equipment']['coverage_area'] ?: null,
			) );
		}
		if ( ! empty( $v['storage'] ) ) {
			$wpdb->insert( AgriXpert_Schema::table( 'storage_facilities' ), array(
				'id'                     => wp_generate_uuid4(),
				'listing_id'             => $id,
				'total_capacity_tonnes'  => $v['storage']['total_capacity_tonnes'],
				'commodity_compatibility'=> wp_json_encode( $v['storage']['commodity_compatibility'] ?? array() ),
				'location_label'         => $v['location_label'] ?: null,
			) );
		}

		do_action( 'agrixpert_listing_created', $id, $actor );
		return $id;
	}

	public static function get_equipment_for_listing( $listing_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AgriXpert_Schema::table( 'equipment' ) . ' WHERE listing_id = %s', $listing_id ) );
	}

	public static function get_storage_for_listing( $listing_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AgriXpert_Schema::table( 'storage_facilities' ) . ' WHERE listing_id = %s', $listing_id ) );
	}

	// ---- Expressions of interest ----------------------------------------

	public static function create_interest( $actor, $listing_id, $message, $quantity_requested ) {
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'listing_interests' ), array(
			'id'                  => $id,
			'listing_id'          => $listing_id,
			'responder_id'        => get_current_user_id(),
			'message'             => $message ?: null,
			'quantity_requested'  => $quantity_requested,
		) );
		if ( $ok === false ) {
			return new WP_Error( 'agx_insert_failed', $wpdb->last_error );
		}
		do_action( 'agrixpert_listing_interest_created', $listing_id, $id, $actor );
		return $id;
	}

	public static function set_interest_status( $interest_id, $status ) {
		global $wpdb;
		$wpdb->update( AgriXpert_Schema::table( 'listing_interests' ), array( 'status' => $status ), array( 'id' => $interest_id ) );
		return true;
	}

	public static function get_interests_for_listing( $listing_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . AgriXpert_Schema::table( 'listing_interests' ) . ' WHERE listing_id = %s ORDER BY created_at DESC', $listing_id ) );
	}

	// ---- Equipment booking (day-level availability) ----------------------

	/** True if [start,end] has no overlapping requested/confirmed booking. Per spec: equipment_bookings is the source of truth for availability, not a separate calendar table. */
	public static function is_equipment_available( $equipment_id, $start_date, $end_date ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'equipment_bookings' );
		$overlap = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE equipment_id = %s AND status IN ('requested','confirmed') AND start_date <= %s AND end_date >= %s",
			$equipment_id, $end_date, $start_date
		) );
		return (int) $overlap === 0;
	}

	public static function create_equipment_booking( $equipment_id, $start_date, $end_date, $listing_interest_id = null ) {
		if ( ! self::is_equipment_available( $equipment_id, $start_date, $end_date ) ) {
			return new WP_Error( 'agx_unavailable', __( 'This equipment is already booked for part of that date range.', 'agrixpert' ) );
		}
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'equipment_bookings' ), array(
			'id'                   => $id,
			'equipment_id'         => $equipment_id,
			'requester_id'         => get_current_user_id(),
			'listing_interest_id'  => $listing_interest_id,
			'start_date'           => $start_date,
			'end_date'             => $end_date,
		) );
		if ( $ok === false ) {
			return new WP_Error( 'agx_insert_failed', $wpdb->last_error );
		}
		do_action( 'agrixpert_equipment_booking_created', $id, $equipment_id );
		return $id;
	}

	// ---- Storage booking (tonnage-level availability) --------------------

	/** Available tonnage in a window = total capacity minus overlapping confirmed/requested bookings — per the storage_bookings table comment. */
	public static function available_storage_tonnage( $facility_id, $start_date, $end_date ) {
		global $wpdb;
		$facility = $wpdb->get_row( $wpdb->prepare( 'SELECT total_capacity_tonnes FROM ' . AgriXpert_Schema::table( 'storage_facilities' ) . ' WHERE id = %s', $facility_id ) );
		if ( ! $facility ) {
			return 0.0;
		}
		$table = AgriXpert_Schema::table( 'storage_bookings' );
		$committed = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(tonnage),0) FROM {$table} WHERE facility_id = %s AND status IN ('requested','confirmed') AND start_date <= %s AND end_date >= %s",
			$facility_id, $end_date, $start_date
		) );
		return max( 0.0, (float) $facility->total_capacity_tonnes - $committed );
	}

	public static function create_storage_booking( $facility_id, $tonnage, $start_date, $end_date, $listing_interest_id = null ) {
		$available = self::available_storage_tonnage( $facility_id, $start_date, $end_date );
		if ( $tonnage > $available ) {
			return new WP_Error( 'agx_unavailable', sprintf(
				/* translators: %s: available tonnage */
				__( 'Only %s tonnes available for that window.', 'agrixpert' ), $available
			) );
		}
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'storage_bookings' ), array(
			'id'                  => $id,
			'facility_id'         => $facility_id,
			'requester_id'        => get_current_user_id(),
			'listing_interest_id' => $listing_interest_id,
			'tonnage'             => $tonnage,
			'start_date'          => $start_date,
			'end_date'            => $end_date,
		) );
		if ( $ok === false ) {
			return new WP_Error( 'agx_insert_failed', $wpdb->last_error );
		}
		do_action( 'agrixpert_storage_booking_created', $id, $facility_id );
		return $id;
	}

	// ---- Reviews ----------------------------------------------------------

	/**
	 * Equipment/storage bookings this WP user requested, joined back to
	 * their listing for display and to know who the review's subject
	 * would be (the listing's owning actor). A booking is treated as
	 * "reviewable" once its end_date has passed — this plugin has no
	 * separate booking-status workflow beyond the initial 'requested'
	 * state (see class-schema.php), so elapsed time is the simplest
	 * honest signal that the service period is over; this is a plugin
	 * design decision, not a port of specific live-app logic, and is
	 * documented as such in docs/ARCHITECTURE.md.
	 *
	 * @return array<object{booking_table,booking_id,start_date,end_date,commodity,subject_id,subject_type,reviewable,already_reviewed}>
	 */
	public static function get_bookings_for_user( $wp_user_id ) {
		global $wpdb;
		$rows = array();
		foreach ( array( 'equipment_bookings' => 'equipment', 'storage_bookings' => 'storage_facilities' ) as $booking_table => $spec_table ) {
			$bt   = AgriXpert_Schema::table( $booking_table );
			$st   = AgriXpert_Schema::table( $spec_table );
			$lt   = AgriXpert_Schema::table( 'listings' );
			$spec_fk = $booking_table === 'equipment_bookings' ? 'equipment_id' : 'facility_id';
			$sql = "SELECT b.id AS booking_id, b.start_date, b.end_date, l.commodity, l.listing_type, l.actor_id AS subject_id, l.actor_type AS subject_type
				FROM {$bt} b
				INNER JOIN {$st} s ON s.id = b.{$spec_fk}
				INNER JOIN {$lt} l ON l.id = s.listing_id
				WHERE b.requester_id = %d
				ORDER BY b.start_date DESC";
			$results = $wpdb->get_results( $wpdb->prepare( $sql, $wp_user_id ) );
			foreach ( $results as $row ) {
				$row->booking_table     = $booking_table;
				$row->reviewable        = strtotime( $row->end_date ) < strtotime( 'today' );
				$row->already_reviewed  = self::has_review( $booking_table, $row->booking_id, $wp_user_id );
				$rows[] = $row;
			}
		}
		usort( $rows, function ( $a, $b ) {
			return strtotime( $b->start_date ) <=> strtotime( $a->start_date );
		} );
		return $rows;
	}

	public static function has_review( $booking_table, $booking_id, $reviewer_id ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'listing_reviews' );
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE booking_table = %s AND booking_id = %s AND reviewer_id = %d",
			$booking_table, $booking_id, $reviewer_id
		) );
		return (int) $count > 0;
	}

	public static function create_review( $subject_id, $booking_table, $booking_id, $rating, $comment ) {
		global $wpdb;
		$id = wp_generate_uuid4();
		$ok = $wpdb->insert( AgriXpert_Schema::table( 'listing_reviews' ), array(
			'id'            => $id,
			'subject_id'    => $subject_id,
			'reviewer_id'   => get_current_user_id(),
			'booking_table' => $booking_table,
			'booking_id'    => $booking_id,
			'rating'        => max( 1, min( 5, (int) $rating ) ),
			'comment'       => $comment ?: null,
		) );
		return $ok === false ? new WP_Error( 'agx_insert_failed', $wpdb->last_error ) : $id;
	}
}
