<?php
/**
 * [agrixpert_marketplace] — browse listings, post a new listing, express
 * interest, and request an equipment/storage booking. Mirrors the single
 * Marketplace page in the Next.js app (browse/post/interest/booking all
 * in one place) rather than splitting into separate pages, since that is
 * how actors actually use it — browse, then act, without navigating away.
 *
 * Posting a listing, expressing interest, and booking all require a WP
 * login resolved to an actor record (AgriXpert_Actors::current_actor()) —
 * anonymous visitors can browse but not transact, matching the Portal's
 * access model.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Marketplace_Shortcode {

	public static function init() {
		add_shortcode( 'agrixpert_marketplace', array( __CLASS__, 'render' ) );

		foreach ( array( 'post_listing', 'express_interest', 'book_equipment', 'book_storage' ) as $action ) {
			add_action( 'admin_post_agrixpert_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	private static function notice() {
		if ( empty( $_GET['agx_mkt_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
			return '';
		}
		$notice = sanitize_key( $_GET['agx_mkt_notice'] );
		$msg    = isset( $_GET['agx_mkt_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['agx_mkt_msg'] ) ) : '';
		$class  = $notice === 'error' ? 'agx-form-error' : 'agx-form-success-inline';
		return $msg ? '<p class="' . esc_attr( $class ) . '">' . esc_html( $msg ) . '</p>' : '';
	}

	public static function render() {
		$actor = AgriXpert_Actors::current_actor();
		$page_url = get_permalink() ?: home_url( '/marketplace' );

		ob_start();
		echo '<div class="agx-marketplace">';
		echo self::notice(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html/esc_attr above

		// --- Post a listing (actors only) ---
		if ( $actor ) {
			echo '<details class="agx-post-listing"><summary>' . esc_html__( 'Post a Listing', 'agrixpert' ) . '</summary>';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agx-form">';
			echo '<input type="hidden" name="action" value="agrixpert_post_listing" />';
			echo '<input type="hidden" name="agx_redirect" value="' . esc_url( $page_url ) . '" />';
			wp_nonce_field( 'agrixpert_post_listing' );
			echo '<div class="agx-form-grid">';
			echo '<div class="agx-field"><label>' . esc_html__( 'Listing Type', 'agrixpert' ) . '</label><select name="listing_type" required>
				<option value="supply">' . esc_html__( 'Supply (I have commodity to sell)', 'agrixpert' ) . '</option>
				<option value="demand">' . esc_html__( 'Demand (I want to buy commodity)', 'agrixpert' ) . '</option>
				<option value="service">' . esc_html__( 'Service (equipment or storage)', 'agrixpert' ) . '</option>
			</select></div>';
			echo '<div class="agx-field"><label>' . esc_html__( 'Commodity', 'agrixpert' ) . '</label><input type="text" name="commodity" /></div>';
			echo '<div class="agx-field"><label>' . esc_html__( 'Quantity', 'agrixpert' ) . '</label><input type="number" step="0.01" min="0" name="quantity" /></div>';
			echo '<div class="agx-field"><label>' . esc_html__( 'Unit', 'agrixpert' ) . '</label><input type="text" name="unit" placeholder="e.g. bags, tonnes" /></div>';
			echo '<div class="agx-field"><label>' . esc_html__( 'Price Indication', 'agrixpert' ) . '</label><input type="text" name="price_indication" /></div>';
			echo '<div class="agx-field"><label>' . esc_html__( 'Location', 'agrixpert' ) . '</label><input type="text" name="location_label" /></div>';
			echo '</div>';

			echo '<fieldset class="agx-service-fields"><legend>' . esc_html__( 'If this is an equipment service listing', 'agrixpert' ) . '</legend>';
			echo '<input type="text" name="equipment_type" placeholder="' . esc_attr__( 'Equipment type, e.g. Tractor', 'agrixpert' ) . '" /> ';
			echo '<input type="text" name="equipment_capacity" placeholder="' . esc_attr__( 'Capacity spec', 'agrixpert' ) . '" /> ';
			echo '<input type="text" name="equipment_coverage" placeholder="' . esc_attr__( 'Coverage area', 'agrixpert' ) . '" /></fieldset>';

			echo '<fieldset class="agx-service-fields"><legend>' . esc_html__( 'If this is a storage service listing', 'agrixpert' ) . '</legend>';
			echo '<input type="number" step="0.01" min="0" name="storage_capacity_tonnes" placeholder="' . esc_attr__( 'Total capacity (tonnes)', 'agrixpert' ) . '" /></fieldset>';

			echo '<button type="submit" class="agx-submit">' . esc_html__( 'Post Listing', 'agrixpert' ) . '</button>';
			echo '</form></details>';
		} else {
			echo '<p class="agx-notice">' . esc_html__( 'Sign in with a verified farmer, buyer, supplier or cooperative account to post a listing or respond to one.', 'agrixpert' ) . '</p>';
		}

		// --- Browse ---
		$filter_type = sanitize_key( $_GET['agx_type'] ?? '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<form method="get" class="agx-mkt-filter">';
		foreach ( array( 'page_id', 'p' ) as $k ) { // preserve the page context on filter submit
			if ( isset( $_GET[ $k ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( sanitize_text_field( wp_unslash( $_GET[ $k ] ) ) ) . '" />'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
		}
		echo '<select name="agx_type" onchange="this.form.submit()"><option value="">' . esc_html__( 'All listing types', 'agrixpert' ) . '</option>';
		foreach ( array( 'supply', 'demand', 'service' ) as $t ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $t ), selected( $filter_type, $t, false ), esc_html( ucfirst( $t ) ) );
		}
		echo '</select></form>';

		$listings = AgriXpert_Marketplace::get_listings( array( 'listing_type' => $filter_type ) );
		echo '<div class="agx-mkt-grid">';
		foreach ( $listings as $listing ) {
			self::render_listing_card( $listing, $actor, $page_url );
		}
		if ( empty( $listings ) ) {
			echo '<p>' . esc_html__( 'No listings yet.', 'agrixpert' ) . '</p>';
		}
		echo '</div></div>';

		return ob_get_clean();
	}

	private static function render_listing_card( $listing, $actor, $page_url ) {
		echo '<div class="agx-mkt-card">';
		echo '<h4>' . esc_html( $listing->commodity ?: ucfirst( $listing->listing_type ) ) . '</h4>';
		echo '<p>' . esc_html( ucfirst( $listing->listing_type ) ) . ( $listing->quantity ? ' &middot; ' . esc_html( $listing->quantity . ' ' . $listing->unit ) : '' ) . '</p>';
		if ( $listing->location_label ) {
			echo '<p class="agx-mkt-location">' . esc_html( $listing->location_label ) . '</p>';
		}
		if ( $listing->price_indication ) {
			echo '<p>' . esc_html( $listing->price_indication ) . '</p>';
		}

		$equipment = AgriXpert_Marketplace::get_equipment_for_listing( $listing->id );
		$storage   = AgriXpert_Marketplace::get_storage_for_listing( $listing->id );

		if ( ! $actor ) {
			echo '</div>';
			return;
		}

		if ( $equipment ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agx-mkt-action">';
			echo '<input type="hidden" name="action" value="agrixpert_book_equipment" /><input type="hidden" name="equipment_id" value="' . esc_attr( $equipment->id ) . '" /><input type="hidden" name="agx_redirect" value="' . esc_url( $page_url ) . '" />';
			wp_nonce_field( 'agrixpert_book_equipment' );
			echo '<label>' . esc_html__( 'Start', 'agrixpert' ) . ' <input type="date" name="start_date" required /></label> ';
			echo '<label>' . esc_html__( 'End', 'agrixpert' ) . ' <input type="date" name="end_date" required /></label> ';
			echo '<button type="submit" class="button">' . esc_html__( 'Request Booking', 'agrixpert' ) . '</button></form>';
		} elseif ( $storage ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agx-mkt-action">';
			echo '<input type="hidden" name="action" value="agrixpert_book_storage" /><input type="hidden" name="facility_id" value="' . esc_attr( $storage->id ) . '" /><input type="hidden" name="agx_redirect" value="' . esc_url( $page_url ) . '" />';
			wp_nonce_field( 'agrixpert_book_storage' );
			echo '<label>' . esc_html__( 'Tonnage', 'agrixpert' ) . ' <input type="number" step="0.01" min="0.01" name="tonnage" required /></label> ';
			echo '<label>' . esc_html__( 'Start', 'agrixpert' ) . ' <input type="date" name="start_date" required /></label> ';
			echo '<label>' . esc_html__( 'End', 'agrixpert' ) . ' <input type="date" name="end_date" required /></label> ';
			echo '<button type="submit" class="button">' . esc_html__( 'Request Booking', 'agrixpert' ) . '</button></form>';
		} else {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agx-mkt-action">';
			echo '<input type="hidden" name="action" value="agrixpert_express_interest" /><input type="hidden" name="listing_id" value="' . esc_attr( $listing->id ) . '" /><input type="hidden" name="agx_redirect" value="' . esc_url( $page_url ) . '" />';
			wp_nonce_field( 'agrixpert_express_interest' );
			echo '<input type="number" step="0.01" min="0" name="quantity_requested" placeholder="' . esc_attr__( 'Quantity', 'agrixpert' ) . '" /> ';
			echo '<input type="text" name="message" placeholder="' . esc_attr__( 'Message (optional)', 'agrixpert' ) . '" /> ';
			echo '<button type="submit" class="button">' . esc_html__( 'Express Interest', 'agrixpert' ) . '</button></form>';
		}
		echo '</div>';
	}

	private static function require_actor_or_die() {
		$actor = AgriXpert_Actors::current_actor();
		if ( ! $actor ) {
			wp_die( esc_html__( 'You need a verified AgriXpert account to do that.', 'agrixpert' ) );
		}
		return $actor;
	}

	private static function redirect( $redirect, $notice, $msg = '' ) {
		wp_safe_redirect( add_query_arg( array( 'agx_mkt_notice' => $notice, 'agx_mkt_msg' => rawurlencode( $msg ) ), $redirect ) );
		exit;
	}

	public static function handle_post_listing() {
		check_admin_referer( 'agrixpert_post_listing' );
		$actor    = self::require_actor_or_die();
		$redirect = esc_url_raw( wp_unslash( $_POST['agx_redirect'] ?? home_url( '/' ) ) );

		$v = array(
			'listing_type'     => in_array( $_POST['listing_type'] ?? '', array( 'supply', 'demand', 'service' ), true ) ? $_POST['listing_type'] : 'supply',
			'commodity'        => sanitize_text_field( wp_unslash( $_POST['commodity'] ?? '' ) ),
			'quantity'         => is_numeric( $_POST['quantity'] ?? '' ) ? (float) $_POST['quantity'] : null,
			'unit'             => sanitize_text_field( wp_unslash( $_POST['unit'] ?? '' ) ),
			'price_indication' => sanitize_text_field( wp_unslash( $_POST['price_indication'] ?? '' ) ),
			'location_label'   => sanitize_text_field( wp_unslash( $_POST['location_label'] ?? '' ) ),
		);
		if ( ! empty( $_POST['equipment_type'] ) ) {
			$v['equipment'] = array(
				'type'           => sanitize_text_field( wp_unslash( $_POST['equipment_type'] ) ),
				'capacity_spec'  => sanitize_text_field( wp_unslash( $_POST['equipment_capacity'] ?? '' ) ),
				'coverage_area'  => sanitize_text_field( wp_unslash( $_POST['equipment_coverage'] ?? '' ) ),
			);
		}
		if ( ! empty( $_POST['storage_capacity_tonnes'] ) && is_numeric( $_POST['storage_capacity_tonnes'] ) ) {
			$v['storage'] = array( 'total_capacity_tonnes' => (float) $_POST['storage_capacity_tonnes'] );
		}

		$result = AgriXpert_Marketplace::create_listing( $actor, $v );
		self::redirect( $redirect, is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Listing posted.', 'agrixpert' ) );
	}

	public static function handle_express_interest() {
		check_admin_referer( 'agrixpert_express_interest' );
		$actor    = self::require_actor_or_die();
		$redirect = esc_url_raw( wp_unslash( $_POST['agx_redirect'] ?? home_url( '/' ) ) );
		$listing_id = sanitize_text_field( wp_unslash( $_POST['listing_id'] ?? '' ) );
		$qty        = is_numeric( $_POST['quantity_requested'] ?? '' ) ? (float) $_POST['quantity_requested'] : null;
		$message    = sanitize_text_field( wp_unslash( $_POST['message'] ?? '' ) );

		$result = AgriXpert_Marketplace::create_interest( $actor, $listing_id, $message, $qty );
		self::redirect( $redirect, is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Interest sent to the listing owner.', 'agrixpert' ) );
	}

	public static function handle_book_equipment() {
		check_admin_referer( 'agrixpert_book_equipment' );
		self::require_actor_or_die();
		$redirect = esc_url_raw( wp_unslash( $_POST['agx_redirect'] ?? home_url( '/' ) ) );
		$result = AgriXpert_Marketplace::create_equipment_booking(
			sanitize_text_field( wp_unslash( $_POST['equipment_id'] ?? '' ) ),
			sanitize_text_field( $_POST['start_date'] ?? '' ),
			sanitize_text_field( $_POST['end_date'] ?? '' )
		);
		self::redirect( $redirect, is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Booking requested.', 'agrixpert' ) );
	}

	public static function handle_book_storage() {
		check_admin_referer( 'agrixpert_book_storage' );
		self::require_actor_or_die();
		$redirect = esc_url_raw( wp_unslash( $_POST['agx_redirect'] ?? home_url( '/' ) ) );
		$result = AgriXpert_Marketplace::create_storage_booking(
			sanitize_text_field( wp_unslash( $_POST['facility_id'] ?? '' ) ),
			is_numeric( $_POST['tonnage'] ?? '' ) ? (float) $_POST['tonnage'] : 0,
			sanitize_text_field( $_POST['start_date'] ?? '' ),
			sanitize_text_field( $_POST['end_date'] ?? '' )
		);
		self::redirect( $redirect, is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Booking requested.', 'agrixpert' ) );
	}
}

AgriXpert_Marketplace_Shortcode::init();
