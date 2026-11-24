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

	/**
	 * Spec Part 7.4 — "Verification-Based Marketplace Permissions...
	 * Administrators must be able to configure which activities require
	 * verification." Stored separately from OPTION (above) since these are
	 * booleans toggled independently of the SMTP/branding fields.
	 *
	 * Default is false (not gated) for every action: this plugin never had
	 * this gate before this rebuild, and the live app has never blocked an
	 * unverified actor from listing, expressing interest, or booking —
	 * flipping the default to "required" would silently change behaviour
	 * for an already-live site the moment this plugin activates. An admin
	 * opts in per action from the Settings screen once they decide they
	 * want it.
	 */
	const GATES_OPTION = 'agrixpert_verification_gates';

	const GATED_ACTIONS = array(
		'post_listing'     => true,
		'express_interest' => true,
		'book_equipment'   => true,
		'book_storage'     => true,
	);

	/** True if $action currently requires verification_status === 'verified'. */
	public static function requires_verification( $action ) {
		if ( ! isset( self::GATED_ACTIONS[ $action ] ) ) {
			return false;
		}
		$gates = get_option( self::GATES_OPTION, array() );
		return ! empty( $gates[ $action ] );
	}

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
			$opts['logo_attachment_id'] = isset( $_POST['logo_attachment_id'] ) ? absint( $_POST['logo_attachment_id'] ) : ( $existing['logo_attachment_id'] ?? 0 );

			update_option( self::OPTION, $opts );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'agrixpert' ) . '</p></div>';
		}

		if ( isset( $_POST['agx_gates_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agx_gates_nonce'] ) ), 'agrixpert_gates' ) ) {
			$gates = array();
			foreach ( array_keys( self::GATED_ACTIONS ) as $action ) {
				$gates[ $action ] = ! empty( $_POST[ 'gate_' . $action ] );
			}
			update_option( self::GATES_OPTION, $gates );
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Verification requirements saved.', 'agrixpert' ) . '</p></div>';
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

		echo '<h2>' . esc_html__( 'Branding', 'agrixpert' ) . '</h2>';
		wp_enqueue_media();
		$logo_id  = (int) self::get( 'logo_attachment_id', 0 );
		$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
		echo '<p><img id="agx-logo-preview" src="' . esc_url( $logo_url ) . '" style="max-height:60px;' . ( $logo_url ? '' : 'display:none;' ) . '" /></p>';
		echo '<input type="hidden" id="agx-logo-id" name="logo_attachment_id" value="' . esc_attr( $logo_id ) . '" />';
		echo '<button type="button" class="button" id="agx-logo-pick">' . esc_html__( 'Choose Logo', 'agrixpert' ) . '</button>';
		echo '<script>
			(function(){
				var btn = document.getElementById("agx-logo-pick");
				if (!btn) return;
				btn.addEventListener("click", function(e){
					e.preventDefault();
					var frame = wp.media({ title: "' . esc_js( __( 'Select Logo', 'agrixpert' ) ) . '", multiple: false });
					frame.on("select", function(){
						var att = frame.state().get("selection").first().toJSON();
						document.getElementById("agx-logo-id").value = att.id;
						var img = document.getElementById("agx-logo-preview");
						img.src = att.url; img.style.display = "block";
					});
					frame.open();
				});
			})();
		</script>';
		submit_button( __( 'Save Settings', 'agrixpert' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'Marketplace Verification Requirements', 'agrixpert' ) . '</h2>';
		echo '<p>' . esc_html__( 'By default, an unverified farmer/buyer/supplier/cooperative account can already use every marketplace action below: verification only shows a status badge. Turn an action on here to block it until staff mark that account verified.', 'agrixpert' ) . '</p>';
		echo '<form method="post">';
		wp_nonce_field( 'agrixpert_gates', 'agx_gates_nonce' );
		echo '<table class="form-table"><tbody>';
		$labels = array(
			'post_listing'     => __( 'Post a marketplace listing', 'agrixpert' ),
			'express_interest' => __( 'Express interest in a listing', 'agrixpert' ),
			'book_equipment'   => __( 'Request an equipment booking', 'agrixpert' ),
			'book_storage'     => __( 'Request a storage booking', 'agrixpert' ),
		);
		foreach ( $labels as $action => $label ) {
			printf(
				'<tr><th>%1$s</th><td><label><input type="checkbox" name="gate_%2$s" value="1"%3$s /> %4$s</label></td></tr>',
				esc_html( $label ),
				esc_attr( $action ),
				checked( self::requires_verification( $action ), true, false ),
				esc_html__( 'Require verification', 'agrixpert' )
			);
		}
		echo '</tbody></table>';
		submit_button( __( 'Save Verification Requirements', 'agrixpert' ) );
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

add_filter( 'agrixpert_email_logo_url', function ( $url ) {
	$id = (int) AgriXpert_Settings::get( 'logo_attachment_id', 0 );
	if ( $id ) {
		$real = wp_get_attachment_image_url( $id, 'medium' );
		if ( $real ) {
			return $real;
		}
	}
	return $url;
} );
