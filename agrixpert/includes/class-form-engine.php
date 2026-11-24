<?php
/**
 * Generic, config-driven registration/enquiry form engine.
 *
 * All five forms on the live Next.js site (farmer, buyer, supplier,
 * cooperative, contact/enquiry) follow the same shape: a handful of text
 * fields, an optional checkbox group, a required consent checkbox, and a
 * single-table insert. Rather than hand-write five near-identical classes,
 * each form is a config array (see class-registration-forms.php) rendered
 * and handled by this one engine — keeping validation/sanitisation rules
 * in one audited place instead of five.
 *
 * Submission flow follows the WordPress-native Post/Redirect/Get pattern
 * via admin-post.php (works with JS disabled, unlike the original SPA
 * form — a genuine improvement, not just parity): nonce-protected POST ->
 * validate -> insert -> redirect back to the referring page with a query
 * flag the shortcode reads to show a success or error message.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Form_Engine {

	/** @var array<string,array> Registered form configs, keyed by form id. */
	private static $forms = array();

	public static function register_form( $id, array $config ) {
		self::$forms[ $id ] = $config;
		add_shortcode( 'agrixpert_' . $id . '_form', function ( $atts ) use ( $id ) {
			return self::render( $id, (array) $atts );
		} );
		add_action( 'admin_post_nopriv_agrixpert_submit_' . $id, function () use ( $id ) {
			self::handle_submit( $id );
		} );
		add_action( 'admin_post_agrixpert_submit_' . $id, function () use ( $id ) {
			self::handle_submit( $id );
		} );
	}

	private static function get_value_chains() {
		return apply_filters( 'agrixpert_value_chains', array( 'Maize', 'Soybeans', 'Rice', 'Groundnuts', 'Vegetables' ) );
	}

	public static function render( $id, $atts = array() ) {
		if ( ! isset( self::$forms[ $id ] ) ) {
			return '';
		}
		$config = self::$forms[ $id ];

		// Post/Redirect/Get result flags, read once and not persisted.
		$result = isset( $_GET['agx_result'], $_GET['agx_form'] ) && $_GET['agx_form'] === $id // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only, no state change
			? sanitize_key( wp_unslash( $_GET['agx_result'] ) )
			: null;
		$error_message = $result === 'error' && isset( $_GET['agx_msg'] )
			? sanitize_text_field( wp_unslash( $_GET['agx_msg'] ) )
			: null;

		ob_start();

		if ( $result === 'success' ) {
			echo '<div class="agx-form-success"><h3>' . esc_html( $config['success_title'] ) . '</h3><p>' . esc_html( $config['success_body'] ) . '</p></div>';
			return ob_get_clean();
		}
		?>
		<form class="agx-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="agrixpert_submit_<?php echo esc_attr( $id ); ?>" />
			<input type="hidden" name="agx_redirect" value="<?php echo esc_url( get_permalink() ?: home_url( '/' ) ); ?>" />
			<input type="hidden" name="agx_ts" value="<?php echo esc_attr( time() ); ?>" />
			<?php // Honeypot: real visitors never see or fill this; CSS-hidden (not type="hidden")
			// so bots that only skip hidden inputs still fall for it. Paired with the agx_ts
			// timestamp (a submit faster than a human could type is treated as spam) and a
			// per-IP transient rate limit in handle_submit() below. ?>
			<p class="agx-hp-field" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;" tabindex="-1">
				<label for="agx-hp-<?php echo esc_attr( $id ); ?>">Leave this field empty</label>
				<input type="text" id="agx-hp-<?php echo esc_attr( $id ); ?>" name="agx_hp" value="" tabindex="-1" autocomplete="off" />
			</p>
			<?php wp_nonce_field( 'agrixpert_submit_' . $id, 'agrixpert_nonce' ); ?>

			<?php if ( $error_message ) : ?>
				<p class="agx-form-error"><?php echo esc_html( $error_message ); ?></p>
			<?php endif; ?>

			<div class="agx-form-grid">
				<?php foreach ( $config['fields'] as $field ) : ?>
					<div class="agx-field agx-field-<?php echo esc_attr( $field['type'] ); ?>">
						<label for="agx-<?php echo esc_attr( $field['name'] ); ?>">
							<?php echo esc_html( $field['label'] ); ?>
							<?php if ( empty( $field['required'] ) ) : ?>
								<span class="agx-optional">(optional)</span>
							<?php endif; ?>
						</label>

						<?php if ( $field['type'] === 'textarea' ) : ?>
							<textarea id="agx-<?php echo esc_attr( $field['name'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>" rows="4" <?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>></textarea>

						<?php elseif ( $field['type'] === 'select' ) : ?>
							<select id="agx-<?php echo esc_attr( $field['name'] ); ?>" name="<?php echo esc_attr( $field['name'] ); ?>">
								<?php foreach ( $field['options'] as $opt ) : ?>
									<option value="<?php echo esc_attr( $opt ); ?>"><?php echo esc_html( $opt ); ?></option>
								<?php endforeach; ?>
							</select>

						<?php elseif ( $field['type'] === 'checkbox_group' ) :
							$options = $field['options'] === '__value_chains__' ? self::get_value_chains() : $field['options'];
							// Pre-check an option matching ?commodity= on the URL — this is
							// how the value-chain pages' "I Want to Produce"/"I Want to Buy"
							// CTAs (see class-page-library-shortcodes.php) hand off a
							// commodity to this form, mirroring the live Next.js app's
							// ValueChainCtas -> /for-farmers?commodity=... flow.
							$prefill = isset( $_GET['commodity'] ) ? sanitize_text_field( wp_unslash( $_GET['commodity'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only prefill, not a state change
							?>
							<div class="agx-checkbox-group">
								<?php foreach ( $options as $opt ) : ?>
									<label class="agx-checkbox">
										<input type="checkbox" name="<?php echo esc_attr( $field['name'] ); ?>[]" value="<?php echo esc_attr( $opt ); ?>"<?php echo ( $prefill && strcasecmp( $prefill, $opt ) === 0 ) ? ' checked' : ''; ?> />
										<?php echo esc_html( $opt ); ?>
									</label>
								<?php endforeach; ?>
							</div>

						<?php else : ?>
							<input
								id="agx-<?php echo esc_attr( $field['name'] ); ?>"
								name="<?php echo esc_attr( $field['name'] ); ?>"
								type="<?php echo esc_attr( $field['type'] ); ?>"
								<?php echo isset( $field['min'] ) ? 'min="' . esc_attr( $field['min'] ) . '"' : ''; ?>
								<?php echo isset( $field['step'] ) ? 'step="' . esc_attr( $field['step'] ) . '"' : ''; ?>
								<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
							/>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="agx-field agx-consent">
				<label>
					<input type="checkbox" name="agx_consent" value="1" required />
					<?php
					printf(
						/* translators: 1: privacy policy link, 2: terms link, 3: cookie policy link */
						esc_html__( 'I have read and agree to AgriXpert\'s %1$s, %2$s and %3$s.', 'agrixpert' ),
						'<a href="' . esc_url( home_url( '/privacy-policy' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Privacy Policy', 'agrixpert' ) . '</a>',
						'<a href="' . esc_url( home_url( '/terms-of-service' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Terms of Service', 'agrixpert' ) . '</a>',
						'<a href="' . esc_url( home_url( '/cookie-policy' ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Cookie Policy', 'agrixpert' ) . '</a>'
					);
					?>
				</label>
			</div>

			<button type="submit" class="agx-submit"><?php echo esc_html( $config['submit_label'] ); ?></button>
		</form>
		<?php
		return ob_get_clean();
	}

	public static function handle_submit( $id ) {
		if ( ! isset( self::$forms[ $id ] ) ) {
			wp_die( esc_html__( 'Unknown form.', 'agrixpert' ) );
		}
		$config    = self::$forms[ $id ];
		$redirect  = isset( $_POST['agx_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['agx_redirect'] ) ) : home_url( '/' );

		if (
			! isset( $_POST['agrixpert_nonce'] ) ||
			! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agrixpert_nonce'] ) ), 'agrixpert_submit_' . $id )
		) {
			self::redirect_with_error( $id, $redirect, __( 'Your session expired — please try again.', 'agrixpert' ) );
		}

		// Honeypot: a filled-in hidden field means a bot, not a visitor. Fail silently
		// with a generic success-looking redirect rather than telling the bot why it
		// was rejected, but never actually insert the row.
		if ( ! empty( $_POST['agx_hp'] ) ) {
			wp_safe_redirect( add_query_arg( array( 'agx_form' => $id, 'agx_result' => 'success' ), $redirect ) );
			exit;
		}

		// Timestamp floor: a real person needs at least a couple of seconds to see the
		// form and fill it in. A submission faster than that is almost certainly scripted.
		$submitted_at = isset( $_POST['agx_ts'] ) ? (int) $_POST['agx_ts'] : 0;
		if ( $submitted_at <= 0 || ( time() - $submitted_at ) < 2 ) {
			self::redirect_with_error( $id, $redirect, __( 'Please try submitting again.', 'agrixpert' ) );
		}

		// Per-IP rate limit: at most 5 submissions to this form per 10 minutes. Uses the
		// WP transients API (object cache if present, else wp_options) rather than a
		// dedicated table, since this is a lightweight, self-expiring counter, not data
		// we need to query or report on.
		$ip = self::get_client_ip();
		if ( $ip ) {
			$key   = 'agx_rl_' . $id . '_' . md5( $ip );
			$count = (int) get_transient( $key );
			if ( $count >= 5 ) {
				self::redirect_with_error( $id, $redirect, __( 'You have submitted this form too many times recently. Please try again later.', 'agrixpert' ) );
			}
			set_transient( $key, $count + 1, 10 * MINUTE_IN_SECONDS );
		}

		$values = array();
		foreach ( $config['fields'] as $field ) {
			$raw = isset( $_POST[ $field['name'] ] ) ? wp_unslash( $_POST[ $field['name'] ] ) : ( $field['type'] === 'checkbox_group' ? array() : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-type below

			switch ( $field['type'] ) {
				case 'checkbox_group':
					$values[ $field['name'] ] = is_array( $raw ) ? array_map( 'sanitize_text_field', $raw ) : array();
					break;
				case 'email':
					$values[ $field['name'] ] = sanitize_email( trim( (string) $raw ) );
					break;
				case 'number':
					$trimmed = trim( (string) $raw );
					if ( $trimmed !== '' && ! is_numeric( $trimmed ) ) {
						self::redirect_with_error( $id, $redirect, sprintf( __( '%s must be a number.', 'agrixpert' ), $field['label'] ) );
					}
					$values[ $field['name'] ] = $trimmed === '' ? null : (float) $trimmed;
					break;
				case 'textarea':
					$values[ $field['name'] ] = sanitize_textarea_field( trim( (string) $raw ) );
					break;
				default:
					$values[ $field['name'] ] = sanitize_text_field( trim( (string) $raw ) );
			}

			if ( ! empty( $field['required'] ) ) {
				$empty = $field['type'] === 'checkbox_group' ? empty( $values[ $field['name'] ] ) : ( $values[ $field['name'] ] === '' || $values[ $field['name'] ] === null );
				if ( $empty ) {
					self::redirect_with_error( $id, $redirect, $config['required_message'] );
				}
			}
		}

		if ( empty( $_POST['agx_consent'] ) ) {
			self::redirect_with_error( $id, $redirect, __( 'Please agree to the Privacy Policy, Terms of Service and Cookie Policy to continue.', 'agrixpert' ) );
		}

		$insert_id = call_user_func( $config['insert_callback'], $values );
		if ( is_wp_error( $insert_id ) ) {
			self::redirect_with_error( $id, $redirect, __( 'Something went wrong submitting your registration. Please try again or contact us directly.', 'agrixpert' ) );
		}

		/**
		 * Fires after a registration/enquiry is successfully stored, with
		 * enough context for the Phase 6 notification system (internal
		 * lead alert + submitter confirmation) to hook in without this
		 * engine needing to know anything about email.
		 */
		do_action( 'agrixpert_submission_saved', $id, $insert_id, $values );

		$url = add_query_arg(
			array( 'agx_form' => $id, 'agx_result' => 'success' ),
			$redirect
		);
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Best-effort client IP for rate limiting only — never stored against a
	 * registration record, and not treated as trustworthy identity (the
	 * X-Forwarded-For header can be spoofed by the client itself if no
	 * trusted proxy is in front of the site, so this is a throttle against
	 * casual abuse, not a security boundary).
	 */
	private static function get_client_ip() {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				$first = trim( explode( ',', $value )[0] );
				if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
					return $first;
				}
			}
		}
		return '';
	}

	private static function redirect_with_error( $id, $redirect, $message ) {
		$url = add_query_arg(
			array( 'agx_form' => $id, 'agx_result' => 'error', 'agx_msg' => rawurlencode( $message ) ),
			$redirect
		);
		wp_safe_redirect( $url );
		exit;
	}
}
