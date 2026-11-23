<?php
/**
 * Self-service "Portal" shortcode — [agrixpert_portal].
 *
 * Added after a Phase 9 gap review found the live Next.js app has an
 * entire logged-in self-service area (see /portal in the original
 * source) that this rewrite had missed: a registered farmer/buyer/
 * supplier/cooperative signing in to see their own verification status,
 * edit their own details, manage a cooperative's member roster, and
 * leave a review on a completed equipment/storage booking. None of this
 * existed anywhere in the plugin before this file.
 *
 * Every write handler here re-derives "who is this person" from
 * AgriXpert_Actors (resolved from the logged-in WP user, never from a
 * client-posted actor id) and re-checks ownership of whatever record is
 * being touched before writing anything — the same pattern the
 * marketplace shortcode already uses, since there is still no
 * database-level RLS equivalent to fall back on.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Portal_Shortcode {

	public static function init() {
		add_shortcode( 'agrixpert_portal', array( __CLASS__, 'render' ) );

		// Logged-in only — there is nothing for an anonymous visitor to
		// do here, so no _nopriv_ variant is registered (matching the
		// existing marketplace write actions).
		foreach ( array( 'update_profile', 'add_member', 'remove_member', 'submit_review' ) as $action ) {
			add_action( 'admin_post_agrixpert_portal_' . $action, array( __CLASS__, 'handle_' . $action ) );
		}
	}

	private static function current_redirect_target() {
		return isset( $_POST['agx_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['agx_redirect'] ) ) : home_url( '/' );
	}

	private static function redirect_back( $flag, $message = '' ) {
		$url = add_query_arg( array_filter( array( 'agx_portal' => $flag, 'agx_portal_msg' => $message ? rawurlencode( $message ) : '' ) ), self::current_redirect_target() );
		wp_safe_redirect( $url );
		exit;
	}

	public static function render() {
		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="agx-form-error">
				<?php
				printf(
					/* translators: %s: login URL */
					esc_html__( 'Please %s to view your AgriXpert account.', 'agrixpert' ),
					'<a href="' . esc_url( wp_login_url( get_permalink() ?: home_url( '/' ) ) ) . '">' . esc_html__( 'log in', 'agrixpert' ) . '</a>'
				);
				?>
			</div>
			<?php
			return ob_get_clean();
		}

		$wp_user_id = get_current_user_id();
		$actors     = AgriXpert_Actors::actors_for_user( $wp_user_id );

		ob_start();

		$notice = isset( $_GET['agx_portal'] ) ? sanitize_key( wp_unslash( $_GET['agx_portal'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
		$msg    = isset( $_GET['agx_portal_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['agx_portal_msg'] ) ) : '';
		if ( $notice === 'success' ) {
			echo '<p class="agx-form-success-inline">' . esc_html( $msg ?: __( 'Saved.', 'agrixpert' ) ) . '</p>';
		} elseif ( $notice === 'error' ) {
			echo '<p class="agx-form-error">' . esc_html( $msg ?: __( 'Something went wrong.', 'agrixpert' ) ) . '</p>';
		}

		if ( empty( $actors ) ) {
			echo '<p>' . esc_html__( "No AgriXpert registration is linked to this account yet. If you've registered with this account's email, it will link automatically next time you log in.", 'agrixpert' ) . '</p>';
			return ob_get_clean();
		}

		foreach ( $actors as $actor ) {
			self::render_actor_section( $actor );
		}

		self::render_bookings_section( $wp_user_id );

		return ob_get_clean();
	}

	private static function render_actor_section( $actor ) {
		$type  = $actor['type'];
		$row   = $actor['row'];
		$name  = AgriXpert_Verification::display_name( $type, $row );
		$vstat = $row->verification_status ?? 'pending';

		echo '<div class="agx-post-listing">';
		echo '<h3>' . esc_html( ucfirst( $type ) ) . ': ' . esc_html( $name ) . '</h3>';

		$badges = array(
			'pending'  => __( 'Pending review', 'agrixpert' ),
			'verified' => __( 'Verified', 'agrixpert' ),
			'rejected' => __( 'Not approved', 'agrixpert' ),
		);
		echo '<p><strong>' . esc_html__( 'Status:', 'agrixpert' ) . '</strong> ' . esc_html( $badges[ $vstat ] ?? ucfirst( $vstat ) ) . '</p>';

		$fields = AgriXpert_Registrations::editable_fields( $type );
		if ( ! empty( $fields ) ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agx-form">';
			echo '<input type="hidden" name="action" value="agrixpert_portal_update_profile" />';
			echo '<input type="hidden" name="agx_type" value="' . esc_attr( $type ) . '" />';
			echo '<input type="hidden" name="agx_id" value="' . esc_attr( $row->id ) . '" />';
			echo '<input type="hidden" name="agx_redirect" value="' . esc_url( get_permalink() ?: home_url( '/' ) ) . '" />';
			wp_nonce_field( 'agrixpert_portal_update_profile_' . $row->id, 'agrixpert_portal_nonce' );
			echo '<div class="agx-form-grid">';
			foreach ( $fields as $field ) {
				$current = $row->{$field['name']} ?? '';
				echo '<div class="agx-field">';
				echo '<label for="agx-portal-' . esc_attr( $field['name'] ) . '-' . esc_attr( $row->id ) . '">' . esc_html( $field['label'] ) . '</label>';
				if ( $field['type'] === 'textarea' ) {
					echo '<textarea id="agx-portal-' . esc_attr( $field['name'] ) . '-' . esc_attr( $row->id ) . '" name="' . esc_attr( $field['name'] ) . '" rows="3">' . esc_textarea( $current ) . '</textarea>';
				} else {
					echo '<input id="agx-portal-' . esc_attr( $field['name'] ) . '-' . esc_attr( $row->id ) . '" name="' . esc_attr( $field['name'] ) . '" type="' . esc_attr( $field['type'] ) . '" value="' . esc_attr( $current ) . '"'
						. ( isset( $field['step'] ) ? ' step="' . esc_attr( $field['step'] ) . '"' : '' )
						. ( isset( $field['min'] ) ? ' min="' . esc_attr( $field['min'] ) . '"' : '' )
						. ( ! empty( $field['required'] ) ? ' required' : '' ) . ' />';
				}
				echo '</div>';
			}
			echo '</div>';
			echo '<button type="submit" class="agx-submit">' . esc_html__( 'Save Changes', 'agrixpert' ) . '</button>';
			echo '</form>';
		}

		if ( $type === 'cooperative' ) {
			self::render_members_section( $row->id );
		}

		echo '</div>';
	}

	private static function render_members_section( $cooperative_id ) {
		$members = AgriXpert_Cooperative_Members::get_active_members( $cooperative_id );
		echo '<div class="agx-service-fields">';
		echo '<p><strong>' . esc_html__( 'Members', 'agrixpert' ) . ' (' . count( $members ) . ')</strong></p>';
		if ( $members ) {
			echo '<ul>';
			foreach ( $members as $member ) {
				echo '<li>' . esc_html( $member->member_name ) . ' ';
				echo '<form method="post" style="display:inline" onsubmit="return confirm(\'' . esc_js( __( 'Remove this member?', 'agrixpert' ) ) . '\');">';
				echo '<input type="hidden" name="action" value="agrixpert_portal_remove_member" />';
				echo '<input type="hidden" name="agx_cooperative_id" value="' . esc_attr( $cooperative_id ) . '" />';
				echo '<input type="hidden" name="agx_member_id" value="' . esc_attr( $member->id ) . '" />';
				echo '<input type="hidden" name="agx_redirect" value="' . esc_url( get_permalink() ?: home_url( '/' ) ) . '" />';
				wp_nonce_field( 'agrixpert_portal_members_' . $cooperative_id, 'agrixpert_portal_nonce' );
				echo '<button type="submit" class="button-link" style="color:#b91c1c;">' . esc_html__( 'Remove', 'agrixpert' ) . '</button>';
				echo '</form></li>';
			}
			echo '</ul>';
		} else {
			echo '<p><em>' . esc_html__( 'No members added yet.', 'agrixpert' ) . '</em></p>';
		}
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="agrixpert_portal_add_member" />';
		echo '<input type="hidden" name="agx_cooperative_id" value="' . esc_attr( $cooperative_id ) . '" />';
		echo '<input type="hidden" name="agx_redirect" value="' . esc_url( get_permalink() ?: home_url( '/' ) ) . '" />';
		wp_nonce_field( 'agrixpert_portal_members_' . $cooperative_id, 'agrixpert_portal_nonce' );
		echo '<input type="text" name="agx_member_name" placeholder="' . esc_attr__( 'Member name', 'agrixpert' ) . '" required /> ';
		echo '<button type="submit" class="button">' . esc_html__( 'Add Member', 'agrixpert' ) . '</button>';
		echo '</form>';
		echo '</div>';
	}

	private static function render_bookings_section( $wp_user_id ) {
		$bookings = AgriXpert_Marketplace::get_bookings_for_user( $wp_user_id );
		if ( empty( $bookings ) ) {
			return;
		}
		echo '<div class="agx-post-listing"><h3>' . esc_html__( 'My Bookings', 'agrixpert' ) . '</h3>';
		echo '<div class="agx-mkt-grid">';
		foreach ( $bookings as $booking ) {
			echo '<div class="agx-mkt-card">';
			echo '<strong>' . esc_html( $booking->commodity ?: ucfirst( $booking->listing_type ) ) . '</strong>';
			echo '<p class="agx-mkt-location">' . esc_html( $booking->start_date . ' – ' . $booking->end_date ) . '</p>';
			if ( $booking->already_reviewed ) {
				echo '<p><em>' . esc_html__( 'Review submitted.', 'agrixpert' ) . '</em></p>';
			} elseif ( $booking->reviewable ) {
				echo '<details><summary>' . esc_html__( 'Leave a review', 'agrixpert' ) . '</summary>';
				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="agx-form">';
				echo '<input type="hidden" name="action" value="agrixpert_portal_submit_review" />';
				echo '<input type="hidden" name="agx_booking_table" value="' . esc_attr( $booking->booking_table ) . '" />';
				echo '<input type="hidden" name="agx_booking_id" value="' . esc_attr( $booking->booking_id ) . '" />';
				echo '<input type="hidden" name="agx_redirect" value="' . esc_url( get_permalink() ?: home_url( '/' ) ) . '" />';
				wp_nonce_field( 'agrixpert_portal_review_' . $booking->booking_id, 'agrixpert_portal_nonce' );
				echo '<label>' . esc_html__( 'Rating', 'agrixpert' ) . ' <select name="agx_rating">';
				for ( $i = 5; $i >= 1; $i-- ) {
					echo '<option value="' . esc_attr( $i ) . '">' . esc_html( $i ) . ' / 5</option>';
				}
				echo '</select></label>';
				echo '<textarea name="agx_comment" rows="2" placeholder="' . esc_attr__( 'Optional comment', 'agrixpert' ) . '"></textarea>';
				echo '<button type="submit" class="agx-submit">' . esc_html__( 'Submit Review', 'agrixpert' ) . '</button>';
				echo '</form></details>';
			} else {
				echo '<p><em>' . esc_html__( 'Review available once the booking period ends.', 'agrixpert' ) . '</em></p>';
			}
			echo '</div>';
		}
		echo '</div></div>';
	}

	// ---- Handlers ---------------------------------------------------------

	public static function handle_update_profile() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'agrixpert' ) );
		}
		$type = sanitize_key( $_POST['agx_type'] ?? '' );
		$id   = sanitize_text_field( wp_unslash( $_POST['agx_id'] ?? '' ) );
		if (
			! isset( $_POST['agrixpert_portal_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agrixpert_portal_nonce'] ) ), 'agrixpert_portal_update_profile_' . $id )
		) {
			self::redirect_back( 'error', __( 'Your session expired — please try again.', 'agrixpert' ) );
		}
		// Note: $_POST is passed raw here, not pre-unslashed — update_own_record()
		// does its own single wp_unslash() per field (matching the form engine's
		// pattern); unslashing twice would incorrectly strip literal backslashes
		// a user actually typed.
		$result = AgriXpert_Registrations::update_own_record( $type, $id, get_current_user_id(), $_POST );
		if ( is_wp_error( $result ) ) {
			self::redirect_back( 'error', $result->get_error_message() );
		}
		self::redirect_back( 'success', __( 'Your details were updated.', 'agrixpert' ) );
	}

	public static function handle_add_member() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'agrixpert' ) );
		}
		$cooperative_id = sanitize_text_field( wp_unslash( $_POST['agx_cooperative_id'] ?? '' ) );
		if (
			! isset( $_POST['agrixpert_portal_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agrixpert_portal_nonce'] ) ), 'agrixpert_portal_members_' . $cooperative_id )
		) {
			self::redirect_back( 'error', __( 'Your session expired — please try again.', 'agrixpert' ) );
		}
		$result = AgriXpert_Cooperative_Members::add_member( $cooperative_id, get_current_user_id(), wp_unslash( $_POST['agx_member_name'] ?? '' ) );
		if ( is_wp_error( $result ) ) {
			self::redirect_back( 'error', $result->get_error_message() );
		}
		self::redirect_back( 'success', __( 'Member added.', 'agrixpert' ) );
	}

	public static function handle_remove_member() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'agrixpert' ) );
		}
		$cooperative_id = sanitize_text_field( wp_unslash( $_POST['agx_cooperative_id'] ?? '' ) );
		$member_id      = sanitize_text_field( wp_unslash( $_POST['agx_member_id'] ?? '' ) );
		if (
			! isset( $_POST['agrixpert_portal_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agrixpert_portal_nonce'] ) ), 'agrixpert_portal_members_' . $cooperative_id )
		) {
			self::redirect_back( 'error', __( 'Your session expired — please try again.', 'agrixpert' ) );
		}
		$result = AgriXpert_Cooperative_Members::remove_member( $member_id, $cooperative_id, get_current_user_id() );
		if ( is_wp_error( $result ) ) {
			self::redirect_back( 'error', $result->get_error_message() );
		}
		self::redirect_back( 'success', __( 'Member removed.', 'agrixpert' ) );
	}

	public static function handle_submit_review() {
		if ( ! is_user_logged_in() ) {
			wp_die( esc_html__( 'You must be logged in.', 'agrixpert' ) );
		}
		$booking_table = sanitize_key( $_POST['agx_booking_table'] ?? '' );
		$booking_id    = sanitize_text_field( wp_unslash( $_POST['agx_booking_id'] ?? '' ) );
		if (
			! isset( $_POST['agrixpert_portal_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agrixpert_portal_nonce'] ) ), 'agrixpert_portal_review_' . $booking_id )
		) {
			self::redirect_back( 'error', __( 'Your session expired — please try again.', 'agrixpert' ) );
		}
		if ( ! in_array( $booking_table, array( 'equipment_bookings', 'storage_bookings' ), true ) ) {
			self::redirect_back( 'error', __( 'Invalid booking.', 'agrixpert' ) );
		}

		$wp_user_id = get_current_user_id();

		// Re-derive everything server-side from the user's own bookings —
		// never trust the posted booking_id as proof it belongs to them,
		// that it's reviewable, or who the subject should be.
		$own_bookings = AgriXpert_Marketplace::get_bookings_for_user( $wp_user_id );
		$booking      = null;
		foreach ( $own_bookings as $candidate ) {
			if ( $candidate->booking_table === $booking_table && (string) $candidate->booking_id === $booking_id ) {
				$booking = $candidate;
				break;
			}
		}
		if ( ! $booking ) {
			self::redirect_back( 'error', __( 'Booking not found.', 'agrixpert' ) );
		}
		if ( ! $booking->reviewable || $booking->already_reviewed ) {
			self::redirect_back( 'error', __( 'This booking cannot be reviewed.', 'agrixpert' ) );
		}

		$rating  = isset( $_POST['agx_rating'] ) ? (int) $_POST['agx_rating'] : 5;
		$comment = isset( $_POST['agx_comment'] ) ? sanitize_textarea_field( wp_unslash( $_POST['agx_comment'] ) ) : '';

		$result = AgriXpert_Marketplace::create_review( $booking->subject_id, $booking_table, $booking_id, $rating, $comment );
		if ( is_wp_error( $result ) ) {
			self::redirect_back( 'error', $result->get_error_message() );
		}
		self::redirect_back( 'success', __( 'Thanks for your review.', 'agrixpert' ) );
	}
}
AgriXpert_Portal_Shortcode::init();
