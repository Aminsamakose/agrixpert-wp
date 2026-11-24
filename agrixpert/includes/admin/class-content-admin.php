<?php
/**
 * Content admin: edit the flat site_content copy/image fields, and a
 * simple Insights CMS (list + create/edit/delete), matching the live
 * admin dashboard's content-management tabs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Content_Admin {

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page( 'agrixpert', __( 'Site Content', 'agrixpert' ), __( 'Site Content', 'agrixpert' ), AgriXpert_Roles::CAP_MANAGE_ALL, 'agrixpert-content', array( __CLASS__, 'render_content' ) );
			add_submenu_page( 'agrixpert', __( 'Insights', 'agrixpert' ), __( 'Insights', 'agrixpert' ), AgriXpert_Roles::CAP_MANAGE_ALL, 'agrixpert-insights', array( __CLASS__, 'render_insights' ) );
		}, 20 );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle' ) );
	}

	public static function maybe_handle() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			return;
		}

		if ( isset( $_POST['agx_content_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agx_content_nonce'] ) ), 'agrixpert_content' ) ) {
			foreach ( (array) ( $_POST['agx_content'] ?? array() ) as $key => $value ) {
				AgriXpert_Content::set_value( sanitize_key( $key ), wp_kses_post( wp_unslash( $value ) ) );
			}
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Content saved.', 'agrixpert' ) . '</p></div>';
			} );
		}

		if ( isset( $_POST['agx_insight_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agx_insight_nonce'] ) ), 'agrixpert_insight' ) ) {
			$v = array(
				'title'     => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
				'slug'      => sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ),
				'excerpt'   => sanitize_textarea_field( wp_unslash( $_POST['excerpt'] ?? '' ) ),
				'body'      => wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) ),
				'category'  => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
				'image_url' => esc_url_raw( wp_unslash( $_POST['image_url'] ?? '' ) ),
				'author'    => sanitize_text_field( wp_unslash( $_POST['author'] ?? '' ) ),
				'published' => ! empty( $_POST['published'] ),
			);
			$id = sanitize_text_field( $_POST['insight_id'] ?? '' );
			AgriXpert_Content::save_insight( $v, $id ?: null );
			wp_safe_redirect( admin_url( 'admin.php?page=agrixpert-insights&agx_notice=updated' ) );
			exit;
		}

		if ( isset( $_GET['agx_delete_insight'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$insight_id = sanitize_text_field( wp_unslash( $_GET['agx_delete_insight'] ) );
			check_admin_referer( 'agrixpert_delete_insight_' . $insight_id );
			AgriXpert_Content::delete_insight( $insight_id );
			wp_safe_redirect( admin_url( 'admin.php?page=agrixpert-insights&agx_notice=updated' ) );
			exit;
		}
	}

	public static function render_content() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}
		$rows = AgriXpert_Content::get_all_site_content();

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'Site Content', 'agrixpert' ) . '</h1>';
		echo '<form method="post">';
		wp_nonce_field( 'agrixpert_content', 'agx_content_nonce' );
		$current_group = null;
		foreach ( $rows as $row ) {
			if ( $row->group_name !== $current_group ) {
				if ( $current_group !== null ) {
					echo '</table>';
				}
				echo '<h2>' . esc_html( $row->group_name ) . '</h2><table class="form-table"><tbody>';
				$current_group = $row->group_name;
			}
			echo '<tr><th><label for="agx-c-' . esc_attr( $row->content_key ) . '">' . esc_html( $row->label ) . '</label></th><td>';
			if ( $row->type === 'textarea' ) {
				echo '<textarea id="agx-c-' . esc_attr( $row->content_key ) . '" name="agx_content[' . esc_attr( $row->content_key ) . ']" rows="4" class="large-text">' . esc_textarea( $row->value ) . '</textarea>';
			} else {
				echo '<input type="text" id="agx-c-' . esc_attr( $row->content_key ) . '" name="agx_content[' . esc_attr( $row->content_key ) . ']" value="' . esc_attr( $row->value ) . '" class="regular-text" />';
			}
			echo '</td></tr>';
		}
		if ( $current_group !== null ) {
			echo '</table>';
		}
		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No editable content fields are registered yet.', 'agrixpert' ) . '</p>';
		}
		submit_button( __( 'Save Content', 'agrixpert' ) );
		echo '</form></div>';
	}

	public static function render_insights() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}
		$editing = isset( $_GET['agx_edit'] ) ? AgriXpert_Content::get_insight( sanitize_text_field( wp_unslash( $_GET['agx_edit'] ) ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'Insights', 'agrixpert' ) . '</h1>';

		echo '<h2>' . ( $editing ? esc_html__( 'Edit Insight', 'agrixpert' ) : esc_html__( 'New Insight', 'agrixpert' ) ) . '</h2>';
		echo '<form method="post">';
		wp_nonce_field( 'agrixpert_insight', 'agx_insight_nonce' );
		if ( $editing ) {
			echo '<input type="hidden" name="insight_id" value="' . esc_attr( $editing->id ) . '" />';
		}
		echo '<table class="form-table"><tbody>';
		self::insight_field( 'title', __( 'Title', 'agrixpert' ), 'text', $editing->title ?? '' );
		self::insight_field( 'slug', __( 'Slug', 'agrixpert' ), 'text', $editing->slug ?? '' );
		self::insight_field( 'category', __( 'Category', 'agrixpert' ), 'text', $editing->category ?? 'News' );
		self::insight_field( 'image_url', __( 'Image URL', 'agrixpert' ), 'text', $editing->image_url ?? '' );
		self::insight_field( 'author', __( 'Author', 'agrixpert' ), 'text', $editing->author ?? 'AgriXpert' );
		self::insight_field( 'excerpt', __( 'Excerpt', 'agrixpert' ), 'textarea', $editing->excerpt ?? '' );
		self::insight_field( 'body', __( 'Body', 'agrixpert' ), 'textarea', $editing->body ?? '' );
		echo '<tr><th>' . esc_html__( 'Published', 'agrixpert' ) . '</th><td><input type="checkbox" name="published" value="1"' . checked( $editing->published ?? 0, 1, false ) . ' /></td></tr>';
		echo '</tbody></table>';
		submit_button( $editing ? __( 'Update', 'agrixpert' ) : __( 'Publish', 'agrixpert' ) );
		echo '</form>';

		echo '<h2>' . esc_html__( 'All Insights', 'agrixpert' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Title', 'agrixpert' ) . '</th><th>' . esc_html__( 'Status', 'agrixpert' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( AgriXpert_Content::get_all_insights() as $insight ) {
			$edit_url   = admin_url( 'admin.php?page=agrixpert-insights&agx_edit=' . $insight->id );
			$delete_url = wp_nonce_url( admin_url( 'admin.php?page=agrixpert-insights&agx_delete_insight=' . $insight->id ), 'agrixpert_delete_insight_' . $insight->id );
			echo '<tr><td>' . esc_html( $insight->title ) . '</td><td>' . ( $insight->published ? esc_html__( 'Published', 'agrixpert' ) : esc_html__( 'Draft', 'agrixpert' ) ) . '</td>';
			echo '<td><a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'agrixpert' ) . '</a> | <a href="' . esc_url( $delete_url ) . '" onclick="return confirm(\'' . esc_js( __( 'Delete this insight?', 'agrixpert' ) ) . '\')">' . esc_html__( 'Delete', 'agrixpert' ) . '</a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function insight_field( $name, $label, $type, $value ) {
		echo '<tr><th>' . esc_html( $label ) . '</th><td>';
		if ( $type === 'textarea' ) {
			echo '<textarea name="' . esc_attr( $name ) . '" rows="5" class="large-text">' . esc_textarea( $value ) . '</textarea>';
		} else {
			echo '<input type="text" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" class="regular-text" />';
		}
		echo '</td></tr>';
	}
}

AgriXpert_Content_Admin::init();
