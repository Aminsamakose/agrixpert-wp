<?php
/**
 * Branded HTML email templates — ported from the live app's
 * src/lib/email-template.ts (same brand colors, same shell/fieldTable/
 * badgePill/ctaButton building blocks, same four render functions) so
 * every notification looks like it comes from the same product whether
 * it was sent by the Next.js app or this plugin.
 *
 * No logo image ships with this plugin yet (the live app's logo.png is
 * a project asset, not code) — the header renders a text wordmark by
 * default and reads its image from the `agrixpert_email_logo_url`
 * filter, so dropping in the real logo at packaging time (Phase 9) is a
 * one-line filter, not a template rewrite.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Email_Templates {

	const BRAND = array(
		'forest'      => '#075421',
		'forest_dark' => '#053A17',
		'agri_green'  => '#69B42E',
		'harvest_gold'=> '#C8622C',
		'white'       => '#FFFFFF',
		'ink'         => '#1F2A20',
		'muted'       => '#6B7A6D',
		'border'      => '#E3E8DE',
		'bg'          => '#F4F6F1',
		'red'         => '#C0392B',
	);

	private static function badge_color( $key ) {
		$map = array(
			'agri_green'   => self::BRAND['agri_green'],
			'harvest_gold' => self::BRAND['harvest_gold'],
			'forest'       => self::BRAND['forest'],
			'red'          => self::BRAND['red'],
		);
		return $map[ $key ] ?? self::BRAND['forest'];
	}

	private static function field_table( array $fields ) {
		$rows = '';
		foreach ( $fields as $f ) {
			$rows .= sprintf(
				'<tr><td style="padding:6px 0;color:%1$s;font-size:13px;width:160px;vertical-align:top;">%2$s</td><td style="padding:6px 0;color:%3$s;font-size:14px;">%4$s</td></tr>',
				esc_attr( self::BRAND['muted'] ), esc_html( $f['label'] ), esc_attr( self::BRAND['ink'] ), esc_html( $f['value'] )
			);
		}
		return '<table role="presentation" style="width:100%;border-collapse:collapse;margin-top:12px;">' . $rows . '</table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from esc_html/esc_attr above
	}

	private static function badge_pill( $label, $color_key ) {
		$color = self::badge_color( $color_key );
		return sprintf(
			'<span style="display:inline-block;background:%1$s1a;color:%1$s;font-size:12px;font-weight:700;letter-spacing:0.04em;padding:4px 12px;border-radius:999px;">%2$s</span>',
			esc_attr( $color ), esc_html( $label )
		);
	}

	private static function cta_button( $label, $url, $color_key ) {
		$color = self::badge_color( $color_key );
		return sprintf(
			'<a href="%1$s" style="display:inline-block;background:%2$s;color:#fff;text-decoration:none;font-weight:600;font-size:14px;padding:10px 24px;border-radius:999px;margin-top:16px;">%3$s</a>',
			esc_url( $url ), esc_attr( $color ), esc_html( $label )
		);
	}

	private static function logo_header() {
		$logo_url = apply_filters( 'agrixpert_email_logo_url', '' );
		if ( $logo_url ) {
			return '<img src="' . esc_url( $logo_url ) . '" width="156" height="52" alt="AgriXpert" style="display:block;" />';
		}
		return '<span style="font-family:Georgia,serif;font-size:22px;font-weight:700;color:' . esc_attr( self::BRAND['forest'] ) . ';">AgriXpert</span>';
	}

	private static function shell( $body_html, $footer_note = '' ) {
		$brand = self::BRAND;
		return '
		<table role="presentation" style="width:100%;background:' . esc_attr( $brand['bg'] ) . ';padding:32px 0;">
			<tr><td align="center">
				<table role="presentation" style="width:100%;max-width:560px;background:' . esc_attr( $brand['white'] ) . ';border-radius:16px;overflow:hidden;">
					<tr><td style="padding:24px 32px;background:' . esc_attr( $brand['white'] ) . ';border-bottom:3px solid ' . esc_attr( $brand['forest'] ) . ';">' . self::logo_header() . '</td></tr>
					<tr><td style="padding:28px 32px;">' . $body_html . '</td></tr>
					<tr><td style="padding:16px 32px;background:' . esc_attr( $brand['bg'] ) . ';text-align:center;">
						<p style="margin:0;color:' . esc_attr( $brand['muted'] ) . ';font-size:12px;">' . esc_html( $footer_note ?: 'AgriXpert · a Samakose venture' ) . '</p>
					</td></tr>
				</table>
			</td></tr>
		</table>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- all dynamic values escaped at point of use
	}

	/** Internal, admin-facing lead/registration alert. */
	public static function lead_notification( $heading, $intro, array $fields, $admin_url ) {
		$body = '<h2 style="margin:0 0 8px;color:' . esc_attr( self::BRAND['forest'] ) . ';font-size:20px;">' . esc_html( $heading ) . '</h2>'
			. '<p style="margin:0;color:' . esc_attr( self::BRAND['ink'] ) . ';font-size:14px;">' . esc_html( $intro ) . '</p>'
			. self::field_table( $fields )
			. self::cta_button( __( 'Open Admin', 'agrixpert' ), $admin_url, 'forest' );
		return self::shell( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Submitter-facing confirmation after a registration/enquiry. */
	public static function confirmation( $name, $heading, $body_message, array $next_steps, array $fields ) {
		$steps_html = '';
		foreach ( $next_steps as $i => $step ) {
			$steps_html .= '<li style="margin-bottom:6px;color:' . esc_attr( self::BRAND['ink'] ) . ';font-size:14px;">' . esc_html( $step ) . '</li>';
		}
		$body = '<h2 style="margin:0 0 8px;color:' . esc_attr( self::BRAND['forest'] ) . ';font-size:20px;">' . esc_html( $heading ) . '</h2>'
			. '<p style="margin:0 0 12px;color:' . esc_attr( self::BRAND['ink'] ) . ';font-size:14px;">' . esc_html( sprintf( __( 'Hi %s, %s', 'agrixpert' ), $name, $body_message ) ) . '</p>'
			. ( $next_steps ? '<ol style="padding-left:18px;margin:0 0 12px;">' . $steps_html . '</ol>' : '' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::field_table( $fields );
		return self::shell( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Generic single-message notice (verification decisions, marketplace activity). */
	public static function notice( $badge_label, $badge_color, $heading, $message, $cta_label = '', $cta_url = '' ) {
		$body = self::badge_pill( $badge_label, $badge_color )
			. '<h2 style="margin:12px 0 8px;color:' . esc_attr( self::BRAND['forest'] ) . ';font-size:20px;">' . esc_html( $heading ) . '</h2>'
			. '<p style="margin:0;color:' . esc_attr( self::BRAND['ink'] ) . ';font-size:14px;">' . esc_html( $message ) . '</p>'
			. ( $cta_label && $cta_url ? self::cta_button( $cta_label, $cta_url, $badge_color ) : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return self::shell( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/** Per-staff follow-up task digest. */
	public static function digest( array $tasks, $admin_url ) {
		$today = current_time( 'Y-m-d' );
		$rows  = '';
		foreach ( $tasks as $t ) {
			$overdue = $t['due_date'] && $t['due_date'] < $today;
			$label   = $overdue ? __( 'Overdue', 'agrixpert' ) : ( $t['due_date'] === $today ? __( 'Due today', 'agrixpert' ) : $t['due_date'] );
			$rows   .= '<tr><td style="padding:8px 0;border-bottom:1px solid ' . esc_attr( self::BRAND['border'] ) . ';">'
				. '<strong style="color:' . esc_attr( self::BRAND['ink'] ) . ';font-size:14px;">' . esc_html( $t['title'] ) . '</strong><br />'
				. '<span style="color:' . esc_attr( self::BRAND['muted'] ) . ';font-size:12px;">' . esc_html( $t['entity_label'] ) . ' &middot; ' . esc_html( $label ) . '</span>'
				. '</td></tr>';
		}
		$body = '<h2 style="margin:0 0 8px;color:' . esc_attr( self::BRAND['forest'] ) . ';font-size:20px;">'
			. esc_html( sprintf( _n( '%d follow-up due', '%d follow-ups due', count( $tasks ), 'agrixpert' ), count( $tasks ) ) ) . '</h2>'
			. '<table role="presentation" style="width:100%;border-collapse:collapse;">' . $rows . '</table>' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			. self::cta_button( __( 'Open Follow-ups', 'agrixpert' ), $admin_url, 'harvest_gold' );
		return self::shell( $body ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
