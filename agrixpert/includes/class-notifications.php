<?php
/**
 * Wires every notification trigger to the actions fired by earlier
 * phases — `agrixpert_submission_saved` (Phase 2), `agrixpert_
 * verification_decided` (Phase 3), `agrixpert_listing_interest_created`
 * / `agrixpert_equipment_booking_created` / `agrixpert_storage_booking_
 * created` (Phase 5) — plus a daily WP-Cron follow-up digest, matching
 * the notify-lead / notify / cron-followup-digest routes in the live
 * Next.js app one-for-one.
 *
 * Every send here is best-effort: AgriXpert_Mailer::send() already
 * returns false quietly when SMTP isn't configured, and nothing in this
 * file treats a failed send as an error worth surfacing — the
 * underlying record is always already saved by the time any of these
 * fire.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Notifications {

	public static function init() {
		add_action( 'agrixpert_submission_saved', array( __CLASS__, 'on_submission_saved' ), 10, 3 );
		add_action( 'agrixpert_verification_decided', array( __CLASS__, 'on_verification_decided' ), 10, 3 );
		add_action( 'agrixpert_listing_interest_created', array( __CLASS__, 'on_listing_interest_created' ), 10, 3 );
		add_action( 'agrixpert_equipment_booking_created', array( __CLASS__, 'on_equipment_booking_created' ), 10, 2 );
		add_action( 'agrixpert_storage_booking_created', array( __CLASS__, 'on_storage_booking_created' ), 10, 2 );

		add_action( 'agrixpert_followup_digest_cron', array( __CLASS__, 'send_followup_digest' ) );
		add_action( 'init', array( __CLASS__, 'maybe_schedule_cron' ) );
	}

	public static function maybe_schedule_cron() {
		if ( ! wp_next_scheduled( 'agrixpert_followup_digest_cron' ) ) {
			// 07:00 site-local time, matching the Vercel Cron job's 07:00
			// Africa/Accra schedule (UTC+0, so the two are numerically the
			// same cron time — no DST to account for).
			wp_schedule_event( strtotime( 'tomorrow 07:00:00' ), 'daily', 'agrixpert_followup_digest_cron' );
		}
	}

	private static function admin_url() {
		return admin_url( 'admin.php?page=agrixpert-followups' );
	}

	// ---- Registration / enquiry (Phase 2) --------------------------------

	private static $presentation = array(
		'farmer'      => array( 'badge' => 'Farmer', 'heading' => 'New farmer registration', 'tab' => 'agrixpert-farmer' ),
		'buyer'       => array( 'badge' => 'Buyer', 'heading' => 'New buyer registration', 'tab' => 'agrixpert-buyer' ),
		'supplier'    => array( 'badge' => 'Supplier', 'heading' => 'New supplier registration', 'tab' => 'agrixpert-supplier' ),
		'cooperative' => array( 'badge' => 'Cooperative', 'heading' => 'New cooperative registration', 'tab' => 'agrixpert-cooperative' ),
		'contact'     => array( 'badge' => 'Enquiry', 'heading' => 'New enquiry', 'tab' => 'agrixpert-enquiry' ),
	);

	public static function on_submission_saved( $form_id, $insert_id, $values ) {
		$meta = self::$presentation[ $form_id ] ?? null;
		if ( ! $meta ) {
			return;
		}
		$admin_url = admin_url( 'admin.php?page=' . $meta['tab'] );

		$fields = array();
		foreach ( $values as $key => $val ) {
			$fields[] = array(
				'label' => ucwords( preg_replace( '/([A-Z])/', ' $1', $key ) ),
				'value' => is_array( $val ) ? ( $val ? implode( ', ', $val ) : __( 'None selected', 'agrixpert' ) ) : ( (string) $val !== '' ? (string) $val : __( 'Not provided', 'agrixpert' ) ),
			);
		}

		$notify_to = AgriXpert_Settings::get( 'notify_email' ) ?: get_option( 'admin_email' );
		AgriXpert_Mailer::send(
			$notify_to,
			$meta['heading'],
			AgriXpert_Email_Templates::lead_notification( $meta['heading'], __( 'A new submission just came in.', 'agrixpert' ), $fields, $admin_url )
		);

		// Confirmation back to the submitter, where an email was given.
		$email = $values['email'] ?? null;
		$name  = $values['fullName'] ?? $values['contactName'] ?? $values['leadContactName'] ?? $values['name'] ?? '';
		if ( $email && is_email( $email ) ) {
			AgriXpert_Mailer::send(
				$email,
				__( 'We received your submission — AgriXpert', 'agrixpert' ),
				AgriXpert_Email_Templates::confirmation(
					$name ?: __( 'there', 'agrixpert' ),
					__( 'Thanks for reaching out', 'agrixpert' ),
					__( 'we have received your submission to AgriXpert. Our team will review it and be in touch.', 'agrixpert' ),
					array( __( 'Our team reviews your details.', 'agrixpert' ), __( "We'll reach out about next steps.", 'agrixpert' ) ),
					$fields
				)
			);
		}
	}

	// ---- Verification decisions (Phase 3) --------------------------------

	public static function on_verification_decided( $entity_type, $row, $decision ) {
		$email = AgriXpert_Verification::contact_email( $entity_type, $row );
		if ( ! $email || ! is_email( $email ) ) {
			return;
		}
		$name       = AgriXpert_Verification::display_name( $entity_type, $row );
		$is_verified = $decision === 'verified';

		AgriXpert_Mailer::send(
			$email,
			$is_verified ? __( "You're verified on AgriXpert", 'agrixpert' ) : __( 'Update on your AgriXpert registration', 'agrixpert' ),
			AgriXpert_Email_Templates::notice(
				$is_verified ? __( 'VERIFIED', 'agrixpert' ) : __( 'NOT APPROVED', 'agrixpert' ),
				$is_verified ? 'agri_green' : 'red',
				$is_verified ? __( "You're verified!", 'agrixpert' ) : __( 'Update on your registration', 'agrixpert' ),
				$is_verified
					? sprintf( __( 'your %s account has been verified. You now have full access to the AgriXpert marketplace.', 'agrixpert' ), $entity_type )
					: sprintf( __( 'after review, we were unable to verify your %s registration at this time. If you have questions or would like to update your details and resubmit, just reply to this email.', 'agrixpert' ), $entity_type ),
				$is_verified ? __( 'Open AgriXpert', 'agrixpert' ) : '',
				$is_verified ? home_url( '/marketplace' ) : ''
			)
		);
	}

	// ---- Marketplace activity (Phase 5) -----------------------------------

	private static function notify_listing_owner( $listing_id, $heading, $message ) {
		$listing = AgriXpert_Marketplace::get_listing( $listing_id );
		if ( ! $listing ) {
			return;
		}
		$owner = AgriXpert_Actors::owner_contact( $listing->actor_type, $listing->actor_id );
		if ( ! $owner || ! $owner['email'] || ! is_email( $owner['email'] ) ) {
			return;
		}
		AgriXpert_Mailer::send(
			$owner['email'],
			$heading . ' — AgriXpert',
			AgriXpert_Email_Templates::notice( __( 'MARKETPLACE', 'agrixpert' ), 'harvest_gold', $heading, $message, __( 'Open Marketplace', 'agrixpert' ), home_url( '/marketplace' ) )
		);
	}

	public static function on_listing_interest_created( $listing_id, $interest_id, $actor ) {
		self::notify_listing_owner( $listing_id, __( 'New interest in your listing', 'agrixpert' ), __( 'Someone has expressed interest in one of your listings. Sign in to the Marketplace to respond.', 'agrixpert' ) );
	}

	public static function on_equipment_booking_created( $booking_id, $equipment_id ) {
		global $wpdb;
		$listing_id = $wpdb->get_var( $wpdb->prepare( 'SELECT listing_id FROM ' . AgriXpert_Schema::table( 'equipment' ) . ' WHERE id = %s', $equipment_id ) );
		if ( $listing_id ) {
			self::notify_listing_owner( $listing_id, __( 'New equipment booking request', 'agrixpert' ), __( 'Someone has requested to book your equipment. Sign in to the Marketplace to respond.', 'agrixpert' ) );
		}
	}

	public static function on_storage_booking_created( $booking_id, $facility_id ) {
		global $wpdb;
		$listing_id = $wpdb->get_var( $wpdb->prepare( 'SELECT listing_id FROM ' . AgriXpert_Schema::table( 'storage_facilities' ) . ' WHERE id = %s', $facility_id ) );
		if ( $listing_id ) {
			self::notify_listing_owner( $listing_id, __( 'New storage booking request', 'agrixpert' ), __( 'Someone has requested to book your storage facility. Sign in to the Marketplace to respond.', 'agrixpert' ) );
		}
	}

	// ---- Daily follow-up digest (WP-Cron) --------------------------------

	public static function send_followup_digest() {
		$rows = AgriXpert_CRM::get_due_followup_tasks();
		if ( empty( $rows ) ) {
			return;
		}
		$by_assignee = array();
		foreach ( $rows as $row ) {
			if ( ! $row->assigned_to ) {
				continue;
			}
			$by_assignee[ $row->assigned_to ][] = array(
				'title'        => $row->title,
				'entity_label' => $row->entity_label,
				'due_date'     => $row->due_date,
			);
		}
		foreach ( $by_assignee as $email => $tasks ) {
			AgriXpert_Mailer::send(
				$email,
				sprintf( _n( '%d follow-up due — AgriXpert', '%d follow-ups due — AgriXpert', count( $tasks ), 'agrixpert' ), count( $tasks ) ),
				AgriXpert_Email_Templates::digest( $tasks, self::admin_url() )
			);
		}
	}
}

AgriXpert_Notifications::init();

register_deactivation_hook( AGRIXPERT_PLUGIN_FILE, function () {
	wp_clear_scheduled_hook( 'agrixpert_followup_digest_cron' );
} );
