<?php
/**
 * SMTP + notification settings screen. Stores connection details via the
 * WordPress Settings API (wp_options) — admin-capability gated, same as
 * every other write path in this plugin. There is no separate secrets
 * store in plain WordPress the way Vercel's environment variables are;
 * this is flagged in the Phase 8 security pass (a host offering an
 * encrypted-option plugin, or defining these as PHP constants in
 * wp-config.php instead of the DB, are both reasonable hardening options
 * to offer at packaging time).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Settings {

	const OPTION = 'agrixpert_settings';

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page(
				'agrixpert',
				__( 'Settings', 'agrixpert' ),
				__( 'Settings', 'agrixpert' ),
				AgriXpert_Roles::CAP_MANAGE_ALL,
				'agrixpert-settings',
				array( __CLASS__, 'render' )
			);
		}, 20 );
	}

	public static function get( $key, $default = '' ) {
		// wp-config.php constants (AGRIXPERT_SMTP_HOST etc.) take priority
		// over DB-stored options when defined — the hardening path noted
		// above, available without any UI change.
		$const = 'AGRIXPERT_' . strtoupper( $key );
		if ( defined( $const ) ) {
			return constant( $const );
		}
		$opts = get_option( self::OPTION, array() );
		return $opts[ $key ] ?? $default;
	}

	public static function is_smtp_configured() {
		return self::get( 'smtp_host' ) && self::get( 'smtp_user' ) && self::get( 'smtp_pass' );
	}

	public static function render() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}

		if ( isset( $_POST['agx_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agx_settings_nonce'] ) ), 'agrixpert_settings' ) ) {
			$opts = array(
				'smtp_host'       => sanitize_text_field( wp_unslash( $_POST['smtp_host'] ?? '' ) ),
				'smtp_port'       => sanitize_text_field( wp_unslash( $_POST['smtp_port'] ?? '' ) ),
				'smtp_user'       => sanitize_text_field( wp_unslash( $_POST['smtp_user'] ?? '' ) ),
				'from_email'      => sanitize_email( wp_unslash( $_POST['from_email'] ?? '' ) ),
				'from_name'       => sanitize_text_field( wp_unslash( $_POST['from_name'] ?? '' ) ),
				'notify_email'    => sanitize_email( wp_unslash( $_POST['notify_email'] ?? '' ) ),
			);
			// Only overwrite the stored password if a new one was typed —
			// the field is always rendered blank, so an unchanged submit
			// must not wipe a previously saved password.
			$existing = get_option( self::OPTION, array() );
			$opts['smtp_pass'] = ! empty( $_POST['smtp_pass'] ) ? sanitize_text_field( wp_unslash( $_POST['smtp_pass'] ) ) : ( $existing['smtp_pass'] ?? '' );

			update_option( self::OPTION, $opts );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'agrixpert' ) . '</p></div>';
		}

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'AgriXpert Settings', 'agrixpert' ) . '</h1>';

		if ( self::is_smtp_configured() ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'SMTP is configured — notification emails are active.', 'agrixpert' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'SMTP is not configured yet — every notification below will silently no-op until host, user and an app password are set.', 'agrixpert' ) . '</p></div>';
		}

		echo '<form method="post">';
		wp_nonce_field( 'agrixpert_settings', 'agx_settings_nonce' );
		echo '<table class="form-table"><tbody>';
		self::field( 'smtp_host', __( 'SMTP Host', 'agrixpert' ), 'text', 'smtp.gmail.com' );
		self::field( 'smtp_port', __( 'SMTP Port', 'agrixpert' ), 'text', '465' );
		self::field( 'smtp_user', __( 'SMTP Username', 'agrixpert' ), 'text' );
		self::field( 'smtp_pass', __( 'SMTP Password / App Password', 'agrixpert' ), 'password', __( '(leave blank to keep current)', 'agrixpert' ) );
		self::field( 'from_name', __( 'From Name', 'agrixpert' ), 'text', 'AgriXpert' );
		self::field( 'from_email', __( 'From Email', 'agrixpert' ), 'email' );
		self::field( 'notify_email', __( 'Internal Notification Email', 'agrixpert' ), 'email', __( 'Where new-lead alerts are sent', 'agrixpert' ) );
		echo '</tbody></table>';
		submit_button( __( 'Save Settings', 'agrixpert' ) );
		echo '</form></div>';
	}

	private static function field( $key, $label, $type, $placeholder = '' ) {
		$value = $type === 'password' ? '' : esc_attr( self::get( $key ) );
		printf(
			'<tr><th><label for="agx-%1$s">%2$s</label></th><td><input type="%3$s" id="agx-%1$s" name="%1$s" value="%4$s" placeholder="%5$s" class="regular-text" /></td></tr>',
			esc_attr( $key ), esc_html( $label ), esc_attr( $type ), $value, esc_attr( $placeholder ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $value already escaped above
		);
	}
}

AgriXpert_Settings::init();
