<?php
/**
 * REST API under /wp-json/agrixpert/v1/ (master spec `api/` module).
 *
 * New scope: the plugin's existing public surface is shortcodes +
 * admin-post.php (Post/Redirect/Get, no JS required — see the rebuild
 * charter's architecture decisions). Nothing in this plugin or the live
 * app currently consumes a REST namespace; this exists so an external
 * consumer (a mobile app, a partner integration, a future JS-driven
 * front end) has one, without replacing or weakening the existing
 * shortcode-based flows, which keep working unchanged.
 *
 * Every write route here re-derives the acting actor server-side via
 * AgriXpert_Actors::current_actor() — exactly like the shortcode
 * handlers in class-marketplace-shortcode.php — and never trusts a
 * client-supplied actor id. Read routes that return PII (verification
 * queue, /me) require the same capability checks as the equivalent
 * wp-admin screen; nothing here is a looser door into data the admin UI
 * already protects.
 *
 * AUTHENTICATION: this plugin does not add a custom auth scheme. A
 * logged-in browser session already authenticates via WordPress's own
 * REST cookie+nonce (send X-WP-Nonce: wp_create_nonce('wp_rest') on
 * same-origin requests). For a non-browser client (a mobile app, a
 * server-to-server integration), use WordPress core's built-in
 * Application Passwords (Users -> Profile -> Application Passwords, or
 * the application-passwords REST routes WordPress itself already
 * ships) — this plugin does not need to, and does not, invent its own
 * token or API-key mechanism alongside it.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Rest_Api {

	const NAMESPACE = 'agrixpert/v1';

	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	public static function register_routes() {
		register_rest_route( self::NAMESPACE, '/listings', array(
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'             => array( __CLASS__, 'get_listings' ),
				'permission_callback' => '__return_true', // Public browse, matches [agrixpert_marketplace]'s anonymous-visitor behaviour.
				'args'                 => array(
					'listing_type' => array( 'type' => 'string', 'enum' => array( 'supply', 'demand', 'service' ) ),
					'commodity'    => array( 'type' => 'string' ),
				),
			),
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'             => array( __CLASS__, 'create_listing' ),
				'permission_callback' => array( __CLASS__, 'require_actor' ),
			),
		) );

		register_rest_route( self::NAMESPACE, '/listings/(?P<id>[\w-]+)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'             => array( __CLASS__, 'get_listing' ),
			'permission_callback' => '__return_true',
		) );

		register_rest_route( self::NAMESPACE, '/listings/(?P<id>[\w-]+)/interest', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'             => array( __CLASS__, 'express_interest' ),
			'permission_callback' => array( __CLASS__, 'require_actor' ),
		) );

		register_rest_route( self::NAMESPACE, '/equipment/(?P<id>[\w-]+)/bookings', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'             => array( __CLASS__, 'book_equipment' ),
			'permission_callback' => array( __CLASS__, 'require_actor' ),
		) );

		register_rest_route( self::NAMESPACE, '/storage/(?P<id>[\w-]+)/bookings', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'             => array( __CLASS__, 'book_storage' ),
			'permission_callback' => array( __CLASS__, 'require_actor' ),
		) );

		register_rest_route( self::NAMESPACE, '/me', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'             => array( __CLASS__, 'get_me' ),
			'permission_callback' => 'is_user_logged_in',
		) );

		register_rest_route( self::NAMESPACE, '/verification/(?P<type>farmer|buyer|supplier|cooperative)', array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'             => array( __CLASS__, 'get_verification_queue' ),
			'permission_callback' => array( __CLASS__, 'require_verify_capability' ),
		) );

		register_rest_route( self::NAMESPACE, '/verification/(?P<type>farmer|buyer|supplier|cooperative)/(?P<id>[\w-]+)/decide', array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'             => array( __CLASS__, 'decide_verification' ),
			'permission_callback' => array( __CLASS__, 'require_verify_capability' ),
			'args'                 => array(
				'decision' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'verify', 'reject', 'reset' ) ),
			),
		) );
	}

	// ---- Permission callbacks -------------------------------------------

	public static function require_actor( WP_REST_Request $request ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'agx_rest_unauthenticated', __( 'You need an AgriXpert account to do that.', 'agrixpert' ), array( 'status' => 401 ) );
		}
		if ( ! AgriXpert_Actors::current_actor() ) {
			return new WP_Error( 'agx_rest_no_actor', __( 'Your WordPress account is not linked to a farmer, buyer, supplier or cooperative registration yet.', 'agrixpert' ), array( 'status' => 403 ) );
		}
		return true;
	}

	public static function require_verify_capability() {
		if ( ! AgriXpert_Roles::current_user_is_staff_or_admin() ) {
			return new WP_Error( 'agx_rest_forbidden', __( 'You do not have permission to view or decide verification requests.', 'agrixpert' ), array( 'status' => 403 ) );
		}
		return true;
	}

	/**
	 * Same rule as class-marketplace-shortcode.php's
	 * require_verified_if_gated(), re-expressed as a WP_Error instead of
	 * wp_die() since this is an API response, not a browser redirect.
	 */
	private static function verification_gate_error( array $actor, $action ) {
		if ( ! AgriXpert_Settings::requires_verification( $action ) ) {
			return null;
		}
		if ( ( $actor['row']->verification_status ?? '' ) === 'verified' ) {
			return null;
		}
		return new WP_Error(
			'agx_rest_verification_required',
			__( 'This action requires a verified account. Your registration is still under review; you will be able to do this once it is verified.', 'agrixpert' ),
			array( 'status' => 403 )
		);
	}

	// ---- Listings --------------------------------------------------------

	public static function get_listings( WP_REST_Request $request ) {
		$listings = AgriXpert_Marketplace::get_listings( array(
			'listing_type' => $request->get_param( 'listing_type' ),
			'commodity'    => $request->get_param( 'commodity' ),
		) );
		return new WP_REST_Response( array_map( array( __CLASS__, 'present_listing' ), $listings ), 200 );
	}

	public static function get_listing( WP_REST_Request $request ) {
		$listing = AgriXpert_Marketplace::get_listing( $request->get_param( 'id' ) );
		if ( ! $listing ) {
			return new WP_Error( 'agx_rest_not_found', __( 'Listing not found.', 'agrixpert' ), array( 'status' => 404 ) );
		}
		return new WP_REST_Response( self::present_listing( $listing ), 200 );
	}

	private static function present_listing( $listing ) {
		$out               = (array) $listing;
		$out['equipment']  = AgriXpert_Marketplace::get_equipment_for_listing( $listing->id );
		$out['storage']    = AgriXpert_Marketplace::get_storage_for_listing( $listing->id );
		// Coarsened per the code-audit's privacy finding (1.3): exact GPS
		// stays out of the public API response, same as any future fix to
		// the public marketplace card should apply. Full precision is
		// still in the raw $listing row available to admin screens, which
		// go through wp-admin capability checks, not this public route.
		if ( isset( $out['latitude'], $out['longitude'] ) && $out['latitude'] !== null && $out['longitude'] !== null ) {
			$out['latitude']  = round( (float) $out['latitude'], 2 );
			$out['longitude'] = round( (float) $out['longitude'], 2 );
		}
		return $out;
	}

	public static function create_listing( WP_REST_Request $request ) {
		$actor = AgriXpert_Actors::current_actor();
		$gate  = self::verification_gate_error( $actor, 'post_listing' );
		if ( $gate ) {
			return $gate;
		}

		$type = $request->get_param( 'listing_type' );
		$v    = array(
			'listing_type'     => in_array( $type, array( 'supply', 'demand', 'service' ), true ) ? $type : 'supply',
			'commodity'        => sanitize_text_field( (string) $request->get_param( 'commodity' ) ),
			'quantity'         => is_numeric( $request->get_param( 'quantity' ) ) ? (float) $request->get_param( 'quantity' ) : null,
			'unit'             => sanitize_text_field( (string) $request->get_param( 'unit' ) ),
			'price_indication' => sanitize_text_field( (string) $request->get_param( 'price_indication' ) ),
			'location_label'   => sanitize_text_field( (string) $request->get_param( 'location_label' ) ),
			'latitude'         => is_numeric( $request->get_param( 'latitude' ) ) ? (float) $request->get_param( 'latitude' ) : null,
			'longitude'        => is_numeric( $request->get_param( 'longitude' ) ) ? (float) $request->get_param( 'longitude' ) : null,
		);
		$equipment_type = $request->get_param( 'equipment_type' );
		if ( $equipment_type ) {
			$v['equipment'] = array(
				'type'          => sanitize_text_field( $equipment_type ),
				'capacity_spec' => sanitize_text_field( (string) $request->get_param( 'equipment_capacity' ) ),
				'coverage_area' => sanitize_text_field( (string) $request->get_param( 'equipment_coverage' ) ),
			);
		}
		$storage_capacity = $request->get_param( 'storage_capacity_tonnes' );
		if ( is_numeric( $storage_capacity ) ) {
			$v['storage'] = array( 'total_capacity_tonnes' => (float) $storage_capacity );
		}

		$result = AgriXpert_Marketplace::create_listing( $actor, $v );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'id' => $result ), 201 );
	}

	public static function express_interest( WP_REST_Request $request ) {
		$actor = AgriXpert_Actors::current_actor();
		$gate  = self::verification_gate_error( $actor, 'express_interest' );
		if ( $gate ) {
			return $gate;
		}
		$result = AgriXpert_Marketplace::create_interest(
			$actor,
			$request->get_param( 'id' ),
			sanitize_text_field( (string) $request->get_param( 'message' ) ),
			is_numeric( $request->get_param( 'quantity_requested' ) ) ? (float) $request->get_param( 'quantity_requested' ) : null
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'id' => $result ), 201 );
	}

	public static function book_equipment( WP_REST_Request $request ) {
		$actor = AgriXpert_Actors::current_actor();
		$gate  = self::verification_gate_error( $actor, 'book_equipment' );
		if ( $gate ) {
			return $gate;
		}
		$result = AgriXpert_Marketplace::create_equipment_booking(
			$request->get_param( 'id' ),
			sanitize_text_field( (string) $request->get_param( 'start_date' ) ),
			sanitize_text_field( (string) $request->get_param( 'end_date' ) )
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'id' => $result ), 201 );
	}

	public static function book_storage( WP_REST_Request $request ) {
		$actor = AgriXpert_Actors::current_actor();
		$gate  = self::verification_gate_error( $actor, 'book_storage' );
		if ( $gate ) {
			return $gate;
		}
		$result = AgriXpert_Marketplace::create_storage_booking(
			$request->get_param( 'id' ),
			is_numeric( $request->get_param( 'tonnage' ) ) ? (float) $request->get_param( 'tonnage' ) : 0,
			sanitize_text_field( (string) $request->get_param( 'start_date' ) ),
			sanitize_text_field( (string) $request->get_param( 'end_date' ) )
		);
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'id' => $result ), 201 );
	}

	// ---- Self / verification ---------------------------------------------

	public static function get_me( WP_REST_Request $request ) {
		$actors = AgriXpert_Actors::current_actors();
		$out    = array();
		foreach ( $actors as $actor ) {
			$out[] = array(
				'type'                => $actor['type'],
				'id'                  => $actor['id'],
				'display_name'        => AgriXpert_Verification::display_name( $actor['type'], $actor['row'] ),
				'verification_status' => $actor['row']->verification_status ?? null,
			);
		}
		return new WP_REST_Response( array( 'actors' => $out ), 200 );
	}

	public static function get_verification_queue( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		$result = AgriXpert_Verification::get_entities( $type, array(
			'status_filter' => $request->get_param( 'status' ) ?: 'all',
			'paged'         => max( 1, (int) $request->get_param( 'page' ) ?: 1 ),
			'per_page'      => min( 100, (int) ( $request->get_param( 'per_page' ) ?: 20 ) ),
		) );
		$items = array_map( function ( $row ) use ( $type ) {
			$out = (array) $row;
			$out['display_name'] = AgriXpert_Verification::display_name( $type, $row );
			return $out;
		}, $result['items'] );
		return new WP_REST_Response( array( 'items' => $items, 'total' => $result['total'] ), 200 );
	}

	public static function decide_verification( WP_REST_Request $request ) {
		$type = $request->get_param( 'type' );
		if ( ! in_array( $type, AgriXpert_Verification::VERIFIABLE_TYPES, true ) ) {
			return new WP_Error( 'agx_rest_invalid_type', __( 'Not a verifiable entity type.', 'agrixpert' ), array( 'status' => 400 ) );
		}
		// 'verify'/'reject'/'reset' (this route's public vocabulary,
		// matching the admin screen's own action labels) map to the
		// actual verification_status values decide() writes — see
		// class-admin-menu.php::map_decision_to_status() for why this
		// mapping matters and isn't just decide()'s own job to do.
		$status_map = array( 'verify' => 'verified', 'reject' => 'rejected', 'reset' => 'pending' );
		$result = AgriXpert_Verification::decide( $type, $request->get_param( 'id' ), $status_map[ $request->get_param( 'decision' ) ], get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			$result->add_data( array( 'status' => 400 ) );
			return $result;
		}
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
}

AgriXpert_Rest_Api::init();
