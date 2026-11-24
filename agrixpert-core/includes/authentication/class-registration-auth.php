<?php
/**
 * Immediate registration → account → Portal access (master spec Part 7).
 *
 * IMPORTANT CONTEXT — read before changing anything in this file:
 * the live Next.js/Supabase application this plugin ports does NOT do
 * this (confirmed directly against its source: FarmerRegistrationForm.tsx
 * and siblings call a plain `supabase.from(...).insert()`, never
 * `auth.signUp()`). Portal access there has always been a separate,
 * deliberate sign-in step, linked to a registration row by matching email
 * on first login (see includes/stakeholders/class-actors.php's
 * claim_by_email(), which still exists and still matters — see below).
 * This file is genuinely NEW functionality requested for this rebuild,
 * not a restoration of something the original app had. See
 * agrixpert-core-rebuild-phase1-audit.md in the project for the full
 * finding.
 *
 * What this does, on a successful farmer/buyer/supplier/cooperative
 * registration (never on the 'contact' enquiry form, which has no
 * portal):
 *
 *   1. If no email was given (allowed for farmers — see
 *      class-registration-forms.php, the only form where email isn't
 *      required), there is nothing to create an account with. The
 *      registration still succeeds; it just isn't linked to a login,
 *      exactly like today, until the person registers a WP account
 *      separately and it's claimed by email at login.
 *   2. If a WordPress user with that email ALREADY exists, we do
 *      nothing here — deliberately. Auto-creating or auto-logging-in
 *      to an existing account from an anonymous, unauthenticated form
 *      POST would be an account-takeover vector: anyone could "register"
 *      with a stranger's email and get signed into their account. The
 *      existing, safe claim_by_email() path (fired on wp_login) already
 *      handles this case correctly — the real account owner claims the
 *      registration themselves, by actually logging in with their real
 *      password.
 *   3. Otherwise: create a new WP user with a random, never-emailed
 *      password; assign the matching stakeholder role (permissions/
 *      class-roles.php); link it to the just-created registration row
 *      immediately (no need to wait for a future login); establish a
 *      logged-in session for this request; and send a "set your
 *      password" email using WordPress's own password-reset token flow
 *      (get_password_reset_key()) rather than emailing a generated
 *      password in plaintext — the standard, safer pattern.
 *   4. Redirect straight to the Portal, intercepting the form engine's
 *      normal "back to the marketing page" redirect for these four
 *      form ids only.
 *
 * Verification status is untouched by any of this — it stays whatever
 * class-schema.php defaults it to ('pending'), exactly satisfying the
 * spec's Part 7.2 requirement that account access and verification
 * status are independent: this account can log in and use the Portal
 * immediately, same as the master spec requires, while still showing
 * as unverified until staff review it (class-portal-shortcode.php
 * already renders that status badge and never gates portal *access* on
 * it — only individual marketplace actions optionally do, per
 * class-roles.php / class-marketplace.php's existing checks).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Registration_Auth {

	/** Form id => entity_type table lookup, and the role each gets. */
	const STAKEHOLDER_FORMS = array(
		'farmer'      => 'farmer',
		'buyer'       => 'buyer',
		'supplier'    => 'supplier',
		'cooperative' => 'cooperative',
	);

	public static function init() {
		// Priority 20: after the existing notification hooks (10) so the
		// lead-notification and submitter-confirmation emails always fire
		// first, regardless of whether account creation below succeeds.
		add_action( 'agrixpert_submission_saved', array( __CLASS__, 'maybe_create_account_and_login' ), 20, 3 );
	}

	public static function maybe_create_account_and_login( $form_id, $insert_id, array $values ) {
		if ( ! isset( self::STAKEHOLDER_FORMS[ $form_id ] ) ) {
			return; // Not a stakeholder registration (e.g. the 'contact' enquiry form) — no portal to log in to.
		}

		$email = isset( $values['email'] ) ? trim( (string) $values['email'] ) : '';
		if ( $email === '' || ! is_email( $email ) ) {
			// No usable email — most commonly a farmer who left the optional
			// email field blank. Nothing to create an account with; the
			// registration itself already succeeded and is unaffected.
			return;
		}

		if ( email_exists( $email ) ) {
			// An account with this email already exists. Never auto-create
			// or auto-log-in here — see the file header. The existing
			// claim_by_email() flow (fired on wp_login) is what links this
			// registration to that account, safely, the next time its real
			// owner actually signs in.
			return;
		}

		$entity_type = self::STAKEHOLDER_FORMS[ $form_id ];
		$user_id     = self::create_account( $email, $values, $entity_type );
		if ( is_wp_error( $user_id ) ) {
			// Fail open: the registration record is already saved. A person
			// can still use the Portal later via the ordinary sign-in +
			// claim-by-email path. We do not show this error to the visitor
			// or block their success message — account creation is a bonus
			// on top of a registration that already succeeded.
			return;
		}

		self::link_registration_to_user( $entity_type, $insert_id, $user_id );
		self::log_user_in( $user_id );
		self::send_set_password_email( $user_id, $email, $entity_type );

		// Intercept the form engine's normal redirect: go straight to the
		// Portal, not back to the marketing page, per spec 7.1 step 11.
		wp_safe_redirect( add_query_arg( 'agx_welcome', $entity_type, home_url( '/portal' ) ) );
		exit;
	}

	private static function create_account( $email, array $values, $entity_type ) {
		$display_name = self::display_name_from_values( $entity_type, $values );
		$username     = self::unique_username_from_email( $email );
		$password     = wp_generate_password( 32, true, true ); // Never emailed, never logged — see send_set_password_email().

		$user_id = wp_insert_user( array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'display_name' => $display_name,
			'role'         => AgriXpert_Roles::STAKEHOLDER_ROLES[ $entity_type ] ?? 'subscriber',
		) );

		return $user_id; // int on success, WP_Error on failure (e.g. race on a duplicate email/login).
	}

	private static function display_name_from_values( $entity_type, array $values ) {
		switch ( $entity_type ) {
			case 'farmer':
				return $values['fullName'] ?? $values['full_name'] ?? '';
			case 'buyer':
			case 'supplier':
				return $values['contactName'] ?? $values['companyName'] ?? '';
			case 'cooperative':
				return $values['leadContactName'] ?? $values['name'] ?? '';
			default:
				return '';
		}
	}

	/**
	 * sanitize_user() + a numeric suffix on collision — WordPress has no
	 * built-in "give me a guaranteed-unique username from this string"
	 * helper, so this is the standard hand-rolled pattern plugins use.
	 * Bounded at 20 attempts purely as a sanity cap; a real collision
	 * streak this long would mean something else is wrong.
	 */
	private static function unique_username_from_email( $email ) {
		$base = sanitize_user( current( explode( '@', $email ) ), true );
		if ( $base === '' ) {
			$base = 'agrixpert-user';
		}
		$candidate = $base;
		$i         = 1;
		while ( username_exists( $candidate ) && $i < 20 ) {
			$candidate = $base . $i;
			++$i;
		}
		return $candidate;
	}

	private static function link_registration_to_user( $entity_type, $insert_id, $user_id ) {
		global $wpdb;
		$table = AgriXpert_Verification::table_for( $entity_type );
		$wpdb->update( $table, array( 'user_id' => $user_id ), array( 'id' => $insert_id ) );
	}

	private static function log_user_in( $user_id ) {
		wp_clear_auth_cookie();
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id );
		$user = get_userdata( $user_id );
		if ( $user ) {
			do_action( 'wp_login', $user->user_login, $user ); // Mirrors a normal login, so anything else hooked there (e.g. claim_by_email, a no-op here since this user owns nothing else yet) behaves consistently.
		}
	}

	/**
	 * Sends a "welcome, set your password" email using WordPress's own
	 * password-reset token — never the generated password itself.
	 * Reuses the existing SMTP mailer/templates (notifications/) rather
	 * than building a parallel email path.
	 */
	private static function send_set_password_email( $user_id, $email, $entity_type ) {
		if ( ! class_exists( 'AgriXpert_Mailer' ) || ! class_exists( 'AgriXpert_Email_Templates' ) ) {
			return;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return; // Account still works and is logged in for this session; they can use "Forgot password" later.
		}
		$reset_url = add_query_arg(
			array( 'action' => 'rp', 'key' => $key, 'login' => rawurlencode( $user->user_login ) ),
			wp_login_url()
		);

		AgriXpert_Mailer::send(
			$email,
			__( 'Welcome to AgriXpert — set your password', 'agrixpert' ),
			AgriXpert_Email_Templates::notice(
				__( 'WELCOME', 'agrixpert' ),
				'agri_green',
				__( "You're in", 'agrixpert' ),
				sprintf(
					/* translators: %s: stakeholder type, e.g. "farmer" */
					__( 'your AgriXpert account is ready and you are already signed in to your %s Portal on this device. To sign in again later from another device or browser, set a password using the link below.', 'agrixpert' ),
					$entity_type
				),
				__( 'Set your password', 'agrixpert' ),
				$reset_url
			)
		);
	}
}

AgriXpert_Registration_Auth::init();
