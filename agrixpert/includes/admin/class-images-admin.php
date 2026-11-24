<?php
/**
 * "Images" admin screen: lets an admin replace any of the bundled
 * placeholder images (the value-chain/solution heroes and grid photos)
 * with AgriXpert's own photography, via the standard WordPress media
 * library — no code change, no redeploying the plugin. See
 * includes/data/class-images.php for where the override is stored and
 * read back from.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Images_Admin {

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page( 'agrixpert', __( 'Images', 'agrixpert' ), __( 'Images', 'agrixpert' ), AgriXpert_Roles::CAP_MANAGE_ALL, 'agrixpert-images', array( __CLASS__, 'render' ) );
		}, 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_media' ) );
	}

	public static function maybe_enqueue_media( $hook ) {
		if ( strpos( (string) $hook, 'agrixpert-images' ) === false ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_script( 'agrixpert-images-admin', AGRIXPERT_PLUGIN_URL . 'assets/js/images-admin.js', array( 'jquery' ), AGRIXPERT_VERSION, true );
	}

	public static function maybe_handle() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			return;
		}

		if ( isset( $_POST['agx_images_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agx_images_nonce'] ) ), 'agrixpert_images' ) ) {
			$key = sanitize_key( wp_unslash( $_POST['agx_image_key'] ?? '' ) );
			$src = esc_url_raw( wp_unslash( $_POST['agx_image_src'] ?? '' ) );
			$alt = sanitize_text_field( wp_unslash( $_POST['agx_image_alt'] ?? '' ) );
			if ( $key && $src ) {
				AgriXpert_Images::set_override( $key, $src, $alt );
				add_action( 'admin_notices', function () {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Image updated.', 'agrixpert' ) . '</p></div>';
				} );
			}
		}

		if ( isset( $_GET['agx_reset_image'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- confirmed by the nonce check on the next line
			$key = sanitize_key( wp_unslash( $_GET['agx_reset_image'] ) );
			check_admin_referer( 'agrixpert_reset_image_' . $key );
			AgriXpert_Images::clear_override( $key );
			wp_safe_redirect( admin_url( 'admin.php?page=agrixpert-images&agx_notice=reset' ) );
			exit;
		}
	}

	public static function render() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'Images', 'agrixpert' ) . '</h1>';
		echo '<p>' . esc_html__( 'Replace any placeholder photo used on the value-chain and solution pages with your own. Changes take effect immediately, everywhere that image is used.', 'agrixpert' ) . '</p>';
		echo '<div class="agx-images-grid">';
		foreach ( AgriXpert_Images::all_slots() as $key => $slot ) {
			echo '<div class="agx-image-card" data-key="' . esc_attr( $key ) . '">';
			echo '<img src="' . esc_url( $slot['src'] ) . '" alt="' . esc_attr( $slot['alt'] ) . '" class="agx-image-preview" />';
			echo '<p class="agx-image-label">' . esc_html( $key ) . '</p>';

			echo '<form method="post" class="agx-image-form">';
			wp_nonce_field( 'agrixpert_images', 'agx_images_nonce' );
			echo '<input type="hidden" name="agx_image_key" value="' . esc_attr( $key ) . '" />';
			echo '<input type="url" name="agx_image_src" class="agx-image-src-field widefat" value="' . esc_attr( $slot['src'] ) . '" placeholder="' . esc_attr__( 'Image URL', 'agrixpert' ) . '" required />';
			echo '<input type="text" name="agx_image_alt" class="widefat" value="' . esc_attr( $slot['alt'] ) . '" placeholder="' . esc_attr__( 'Alt text', 'agrixpert' ) . '" />';
			echo '<p class="agx-image-actions">';
			echo '<button type="button" class="button agx-choose-image">' . esc_html__( 'Choose from Media Library', 'agrixpert' ) . '</button> ';
			echo '<button type="submit" class="button button-primary">' . esc_html__( 'Save', 'agrixpert' ) . '</button>';
			if ( $slot['is_override'] ) {
				$reset_url = wp_nonce_url( admin_url( 'admin.php?page=agrixpert-images&agx_reset_image=' . $key ), 'agrixpert_reset_image_' . $key );
				echo ' <a href="' . esc_url( $reset_url ) . '" class="button">' . esc_html__( 'Reset to Default', 'agrixpert' ) . '</a>';
			}
			echo '</p>';
			echo '</form>';
			echo '</div>';
		}
		echo '</div></div>';
	}
}

AgriXpert_Images_Admin::init();
