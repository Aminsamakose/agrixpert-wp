<?php
/**
 * Admin-side marketplace moderation: every listing with its interests,
 * and accept/decline/complete actions. Matches the moderation capability
 * the live admin dashboard's Marketplace tab provides for staff.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Marketplace_Admin {

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page(
				'agrixpert',
				__( 'Marketplace', 'agrixpert' ),
				__( 'Marketplace', 'agrixpert' ),
				AgriXpert_Roles::CAP_MODERATE_MKT,
				'agrixpert-marketplace',
				array( __CLASS__, 'render' )
			);
		}, 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle' ) );
	}

	public static function maybe_handle() {
		if ( empty( $_POST['agx_mkt_action'] ) || ! current_user_can( AgriXpert_Roles::CAP_MODERATE_MKT ) ) {
			return;
		}
		check_admin_referer( 'agrixpert_marketplace_admin' );
		$interest_id = sanitize_text_field( wp_unslash( $_POST['interest_id'] ?? '' ) );
		$status      = sanitize_key( $_POST['agx_mkt_action'] );
		if ( in_array( $status, array( 'accepted', 'declined', 'completed' ), true ) ) {
			AgriXpert_Marketplace::set_interest_status( $interest_id, $status );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=agrixpert-marketplace' ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MODERATE_MKT ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}
		$listings = AgriXpert_Marketplace::get_listings();

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'Marketplace', 'agrixpert' ) . '</h1>';
		foreach ( $listings as $listing ) {
			echo '<h3>' . esc_html( $listing->commodity ?: ucfirst( $listing->listing_type ) ) . ' <small>(' . esc_html( ucfirst( $listing->actor_type ) ) . ')</small></h3>';
			$interests = AgriXpert_Marketplace::get_interests_for_listing( $listing->id );
			if ( empty( $interests ) ) {
				echo '<p><em>' . esc_html__( 'No interest yet.', 'agrixpert' ) . '</em></p>';
				continue;
			}
			echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Quantity', 'agrixpert' ) . '</th><th>' . esc_html__( 'Message', 'agrixpert' ) . '</th><th>' . esc_html__( 'Status', 'agrixpert' ) . '</th><th></th></tr></thead><tbody>';
			foreach ( $interests as $interest ) {
				echo '<tr><td>' . esc_html( $interest->quantity_requested ?: '—' ) . '</td><td>' . esc_html( $interest->message ?: '—' ) . '</td><td>' . esc_html( ucfirst( $interest->status ) ) . '</td><td>';
				foreach ( array( 'accepted' => __( 'Accept', 'agrixpert' ), 'declined' => __( 'Decline', 'agrixpert' ), 'completed' => __( 'Mark Completed', 'agrixpert' ) ) as $action => $label ) {
					echo '<form method="post" style="display:inline">';
					wp_nonce_field( 'agrixpert_marketplace_admin' );
					echo '<input type="hidden" name="agx_mkt_action" value="' . esc_attr( $action ) . '" /><input type="hidden" name="interest_id" value="' . esc_attr( $interest->id ) . '" />';
					echo '<button type="submit" class="button">' . esc_html( $label ) . '</button></form> ';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		if ( empty( $listings ) ) {
			echo '<p>' . esc_html__( 'No listings yet.', 'agrixpert' ) . '</p>';
		}
		echo '</div>';
	}
}

AgriXpert_Marketplace_Admin::init();
