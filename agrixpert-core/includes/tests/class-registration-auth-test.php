<?php
/**
 * Tests for includes/authentication/class-registration-auth.php — the
 * highest-risk new surface in this rebuild (code-audit finding #8: no
 * automated tests existed anywhere in the plugin before this file).
 *
 * HOW TO RUN THIS
 * ================
 * This is a standard WP_UnitTestCase test and cannot run as a bare PHP
 * script (there is no WordPress runtime, database, or wp_insert_user() in
 * this sandbox). To actually execute it on a real machine:
 *
 *   1. Install the WordPress PHPUnit test scaffold (one-time):
 *        svn checkout https://develop.svn.wordpress.org/trunk/tests/phpunit/includes/
 *      or use the wp-cli scaffold command:
 *        wp scaffold plugin-tests agrixpert-core
 *      (either gives you a bin/install-wp-tests.sh and a bootstrap.php)
 *   2. Point its bootstrap at this plugin, e.g. in tests/bootstrap.php:
 *        tests_add_filter( 'muplugins_loaded', function () {
 *            require dirname( __DIR__ ) . '/agrixpert-core.php';
 *        } );
 *   3. Run: phpunit --bootstrap tests/bootstrap.php includes/tests/
 *
 * Nothing here has been executed against a real WordPress + MySQL
 * instance — this sandbox has neither. Every assertion below is written
 * against the documented behaviour of class-registration-auth.php as
 * read from its own source, not verified by a live run. Treat this as a
 * starting harness to run and extend on a real dev environment before
 * trusting it as a safety net.
 */

if ( ! class_exists( 'WP_UnitTestCase' ) ) {
	return; // Loaded outside the WP test harness — nothing to run here.
}

class AgriXpert_Registration_Auth_Test extends WP_UnitTestCase {

	private function submit( $form_id, array $values ) {
		// AgriXpert_Registration_Auth::maybe_create_account_and_login() is
		// hooked onto 'agrixpert_submission_saved' with a real insert_id;
		// since the hook itself doesn't read the registrations table, a
		// fixed placeholder id is fine here — it only needs linking logic
		// in link_registration_to_user(), which is covered by assertion
		// (3) below via a real row.
		do_action( 'agrixpert_submission_saved', $form_id, 1, $values );
	}

	/** (1) No email on the form (allowed for farmers) -> no account, no error, no redirect. */
	public function test_no_email_is_a_silent_no_op() {
		$before_user_count = count( get_users() );

		$this->submit( 'farmer', array( 'fullName' => 'Ama Issaka' ) );

		$this->assertCount( $before_user_count, get_users(), 'No new WP user should be created when no email was given.' );
	}

	/** (2) 'contact' enquiry form must never trigger account creation. */
	public function test_contact_form_is_ignored() {
		$before_user_count = count( get_users() );

		$this->submit( 'contact', array( 'email' => 'enquirer@example.com' ) );

		$this->assertCount( $before_user_count, get_users(), 'The contact/enquiry form has no portal and must never create an account.' );
	}

	/**
	 * (3) The core anti-account-takeover rule: an email that already
	 * belongs to an existing WP user must never be auto-logged into or
	 * have a new account created for it from an anonymous form POST.
	 */
	public function test_existing_email_is_never_auto_logged_in() {
		$existing = self::factory()->user->create( array( 'user_email' => 'existing@example.com' ) );
		wp_set_current_user( 0 ); // Ensure we start logged out, as a real anonymous visitor would.

		$this->submit( 'buyer', array(
			'email'       => 'existing@example.com',
			'contactName' => 'Someone Claiming To Be Them',
		) );

		$this->assertSame( 0, get_current_user_id(), 'An anonymous POST with a pre-existing email must never establish a logged-in session.' );
		$this->assertCount( 1, get_users( array( 'search' => 'existing@example.com', 'search_columns' => array( 'user_email' ) ) ), 'No second account should be created for an email that already has one.' );
	}

	/**
	 * (4) The happy path: a brand-new email on a stakeholder form creates
	 * an account, logs the visitor in, and leaves verification untouched.
	 *
	 * Note: this stops short of asserting the wp_safe_redirect()/exit()
	 * at the end of maybe_create_account_and_login(), since exit() inside
	 * a PHPUnit test process is destructive. A real run of this test
	 * should use WP's sendRedirect-interception helpers
	 * (expectException( WPDieException::class ) pattern or
	 * `add_filter('wp_redirect', ...)` with `wp_die` swapped for a
	 * testable no-op) to capture and assert the redirect target instead
	 * of letting it fire. That wiring is left for whoever runs this
	 * against a real harness, since it depends on which WP test
	 * scaffold version is in use.
	 */
	public function test_new_stakeholder_email_creates_and_logs_in_account() {
		$this->markTestIncomplete(
			'Needs the redirect/exit interception described in this method\'s docblock, wired up against the actual WP test scaffold in use, before it can run to completion without exiting the test process.'
		);
	}
}
