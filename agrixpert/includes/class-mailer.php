<?php
/**
 * Routes wp_mail() through the SMTP settings on the AgriXpert Settings
 * screen, via the phpmailer_init hook — WordPress's documented way to
 * configure PHPMailer (which WP bundles) without a dependency on any
 * third-party SMTP plugin. Mirrors the live app's own move away from a
 * third-party email API (Resend) to a plain SMTP mailbox.
 *
 * Same best-effort contract as the rest of this plugin's notification
 * paths: if SMTP isn't configured, send_notification() returns false and
 * the caller (class-notifications.php) never treats that as an error —
 * the underlying record is already saved by the time any notification
 * fires.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Mailer {

	public static function init() {
		add_action( 'phpmailer_init', array( __CLASS__, 'configure_smtp' ) );
	}

	public static function configure_smtp( $phpmailer ) {
		if ( ! AgriXpert_Settings::is_smtp_configured() ) {
			return;
		}
		$port = (int) AgriXpert_Settings::get( 'smtp_port', 587 );

		$phpmailer->isSMTP();
		$phpmailer->Host       = AgriXpert_Settings::get( 'smtp_host' );
		$phpmailer->Port       = $port;
		$phpmailer->SMTPAuth   = true;
		$phpmailer->Username   = AgriXpert_Settings::get( 'smtp_user' );
		$phpmailer->Password   = AgriXpert_Settings::get( 'smtp_pass' );
		$phpmailer->SMTPSecure = $port === 465 ? 'ssl' : 'tls';
		$from_email            = AgriXpert_Settings::get( 'from_email' ) ?: AgriXpert_Settings::get( 'smtp_user' );
		$phpmailer->From       = $from_email;
		$phpmailer->FromName   = AgriXpert_Settings::get( 'from_name' ) ?: 'AgriXpert';
	}

	/**
	 * Sends one HTML email. Returns true/false — never throws, never
	 * surfaces to the caller as something that should block a save.
	 */
	public static function send( $to, $subject, $html ) {
		if ( ! AgriXpert_Settings::is_smtp_configured() || ! is_email( $to ) ) {
			return false;
		}
		return wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}

add_filter( 'wp_mail_content_type', function () {
	return 'text/html';
} );

AgriXpert_Mailer::init();
