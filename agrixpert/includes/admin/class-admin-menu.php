<?php
/**
 * The AgriXpert admin dashboard shell: a top-level menu with one submenu
 * per registration queue (Farmers/Buyers/Suppliers/Cooperatives), an
 * Enquiries inbox, and Staff management — the WordPress-native
 * equivalent of the 12-tab Next.js /admin dashboard's verification
 * section. CRM (Phase 4) and Marketplace (Phase 5) add further submenus
 * under the same top-level menu later.
 *
 * Every entry point here calls current_user_can() before touching data —
 * per the Phase 1 note, this is the sole enforcement layer now that
 * there is no database-level RLS equivalent.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Admin_Menu {

	const ENTITY_TABS = array(
		'farmer'      => array( 'label' => 'Farmers' ),
		'buyer'       => array( 'label' => 'Buyers' ),
		'supplier'    => array( 'label' => 'Suppliers' ),
		'cooperative' => array( 'label' => 'Cooperatives' ),
		'enquiry'     => array( 'label' => 'Enquiries' ),
	);

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_action' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
	}

	public static function enqueue_styles( $hook ) {
		if ( strpos( $hook, 'agrixpert' ) === false ) {
			return;
		}
		wp_enqueue_style( 'agrixpert-admin', AGRIXPERT_PLUGIN_URL . 'assets/css/admin.css', array(), AGRIXPERT_VERSION );
	}

	public static function register_menu() {
		if ( ! AgriXpert_Roles::current_user_is_staff_or_admin() ) {
			return;
		}

		add_menu_page(
			__( 'AgriXpert', 'agrixpert' ),
			__( 'AgriXpert', 'agrixpert' ),
			AgriXpert_Roles::CAP_VERIFY,
			'agrixpert',
			array( __CLASS__, 'render_registrations_page' ),
			'dashicons-tree',
			26
		);

		foreach ( self::ENTITY_TABS as $type => $meta ) {
			add_submenu_page(
				'agrixpert',
				$meta['label'],
				$meta['label'],
				AgriXpert_Roles::CAP_VERIFY,
				'agrixpert-' . $type,
				array( __CLASS__, 'render_registrations_page' )
			);
		}

		if ( current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			add_submenu_page(
				'agrixpert',
				__( 'Staff', 'agrixpert' ),
				__( 'Staff', 'agrixpert' ),
				AgriXpert_Roles::CAP_MANAGE_ALL,
				'agrixpert-staff',
				array( __CLASS__, 'render_staff_page' )
			);
		}

		// Remove the auto-added duplicate first submenu item (WP adds one
		// matching the top-level slug) in favour of the explicit Farmers tab.
		remove_submenu_page( 'agrixpert', 'agrixpert' );
	}

	private static function current_tab() {
		$slug = sanitize_key( $_GET['page'] ?? 'agrixpert' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only
		$tab  = str_replace( 'agrixpert-', '', $slug );
		return isset( self::ENTITY_TABS[ $tab ] ) ? $tab : 'farmer';
	}

	public static function render_registrations_page() {
		if ( ! AgriXpert_Roles::current_user_is_staff_or_admin() ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}

		$entity_type = self::current_tab();
		$table       = new AgriXpert_Verification_List_Table( $entity_type );
		$table->prepare_items();

		echo '<div class="wrap agrixpert-wrap">';
		echo '<h1>' . esc_html__( 'AgriXpert', 'agrixpert' ) . '</h1>';

		if ( isset( $_GET['agx_notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only
			$notice = sanitize_key( $_GET['agx_notice'] );
			if ( $notice === 'updated' ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Updated.', 'agrixpert' ) . '</p></div>';
			} elseif ( $notice === 'error' ) {
				echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Something went wrong. Please try again.', 'agrixpert' ) . '</p></div>';
			}
		}

		echo '<h2 class="nav-tab-wrapper">';
		foreach ( self::ENTITY_TABS as $type => $meta ) {
			$class = $type === $entity_type ? ' nav-tab-active' : '';
			printf(
				'<a class="nav-tab%s" href="%s">%s</a>',
				esc_attr( $class ),
				esc_url( admin_url( 'admin.php?page=agrixpert-' . $type ) ),
				esc_html( $meta['label'] )
			);
		}
		echo '</h2>';

		/*
		 * POST, not GET: the bulk-action dropdown and the CSV export
		 * button both submit data that maybe_handle_action() reads from
		 * $_POST. A GET form here would mean "Apply" and "Export CSV"
		 * silently do nothing — sortable-column links and row actions
		 * (verify/reject/CRM) are plain <a href> GET navigation and are
		 * unaffected by the wrapping form's method either way, and
		 * get_entities() already reads search/filter/sort from
		 * $_REQUEST, so it does not care whether this form is GET or POST.
		 */
		echo '<form method="post">';
		echo '<input type="hidden" name="page" value="' . esc_attr( 'agrixpert-' . $entity_type ) . '" />';
		wp_nonce_field( 'bulk-' . $entity_type . 's' );
		wp_nonce_field( 'agrixpert_export_csv', 'agx_export_nonce' );
		$table->search_box( __( 'Search', 'agrixpert' ), 'agx-search' );
		$table->display();
		echo '</form>';
		echo '</div>';
	}

	public static function render_staff_page() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_ALL ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}
		global $wpdb;
		$table = AgriXpert_Schema::table( 'admins' );

		if ( isset( $_POST['agx_add_staff_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['agx_add_staff_nonce'] ) ), 'agrixpert_add_staff' ) ) {
			$email = sanitize_email( wp_unslash( $_POST['agx_staff_email'] ?? '' ) );
			$role  = in_array( $_POST['agx_staff_role'] ?? '', array( 'admin', 'staff' ), true ) ? $_POST['agx_staff_role'] : 'staff';
			if ( is_email( $email ) ) {
				$wpdb->replace( $table, array( 'email' => $email, 'role' => $role ) );
				$wp_user = get_user_by( 'email', $email );
				if ( $wp_user ) {
					$wp_user->add_role( $role === 'admin' ? 'agrixpert_admin' : 'agrixpert_staff' );
				}
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Staff member saved.', 'agrixpert' ) . '</p></div>';
			}
		}

		$rows = $wpdb->get_results( "SELECT email, role, created_at FROM {$table} ORDER BY created_at ASC" );

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'AgriXpert Staff', 'agrixpert' ) . '</h1>';
		echo '<form method="post" class="agx-staff-form">';
		wp_nonce_field( 'agrixpert_add_staff', 'agx_add_staff_nonce' );
		echo '<input type="email" name="agx_staff_email" placeholder="' . esc_attr__( 'Email', 'agrixpert' ) . '" required />';
		echo '<select name="agx_staff_role"><option value="staff">' . esc_html__( 'Staff', 'agrixpert' ) . '</option><option value="admin">' . esc_html__( 'Admin', 'agrixpert' ) . '</option></select>';
		submit_button( __( 'Add / Update', 'agrixpert' ), 'secondary', '', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Email', 'agrixpert' ) . '</th><th>' . esc_html__( 'Role', 'agrixpert' ) . '</th><th>' . esc_html__( 'Since', 'agrixpert' ) . '</th></tr></thead><tbody>';
		foreach ( $rows as $row ) {
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
				esc_html( $row->email ),
				esc_html( ucfirst( $row->role ) ),
				esc_html( mysql2date( get_option( 'date_format' ), $row->created_at ) )
			);
		}
		echo '</tbody></table></div>';
	}

	/**
	 * Handles: single row verify/reject/reset (class-verification-list-table
	 * row actions), bulk actions from the list table form, and CSV export.
	 * All three paths require a verified nonce AND the verify capability.
	 */
	/**
	 * The list table's row actions and bulk-action dropdown use the
	 * short verb 'verify' / 'reject' / 'reset' as their action identifier
	 * (see class-verification-list-table.php's get_bulk_actions() and
	 * row_action_url()) — but AgriXpert_Verification::decide() writes
	 * its $decision argument directly into the verification_status
	 * column, which must hold 'verified' / 'rejected' / 'pending' to
	 * match the schema default and the status filter dropdown
	 * (extra_tablenav()), and decide() only fires
	 * 'agrixpert_verification_decided' when $decision is exactly
	 * 'verified' or 'rejected'. Passing the short verb straight through
	 * silently wrote 'verify'/'reject' into that column and never fired
	 * the notification email. Fixed here; see docs/VERIFICATION-STATUS-FIX.md
	 * for the one-time data check/fix this requires on top of the code fix.
	 */
	private static function map_decision_to_status( $action ) {
		$map = array( 'verify' => 'verified', 'reject' => 'rejected', 'reset' => 'pending' );
		return $map[ $action ] ?? $action;
	}

	public static function maybe_handle_action() {
		if ( empty( $_GET['page'] ) || strpos( sanitize_key( $_GET['page'] ), 'agrixpert-' ) !== 0 ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! AgriXpert_Roles::current_user_is_staff_or_admin() ) {
			return;
		}

		$entity_type = self::current_tab();
		$user_id     = get_current_user_id();

		// --- Single row action (verify / reject / reset) ---
		if ( isset( $_GET['agx_action'], $_GET['agx_id'], $_GET['agx_decision'] ) && $_GET['agx_action'] === 'decide' ) {
			$id = sanitize_text_field( wp_unslash( $_GET['agx_id'] ) );
			check_admin_referer( 'agrixpert_decide_' . $id );

			$decision = sanitize_key( $_GET['agx_decision'] );
			$result   = $entity_type === 'enquiry'
				? AgriXpert_Verification::set_enquiry_status( $id, $decision, $user_id )
				: AgriXpert_Verification::decide( $entity_type, $id, self::map_decision_to_status( $decision ), $user_id );

			self::redirect_with_notice( $entity_type, is_wp_error( $result ) ? 'error' : 'updated' );
		}

		// --- Bulk action from the list table's own form ---
		// WP_List_Table renders two bulk-action dropdowns (top: "action",
		// bottom: "action2") — a user can submit from either, and the one
		// not used is left at its default "-1". Checking "action" alone
		// would silently do nothing when the bottom dropdown was used;
		// this mirrors WP_List_Table::current_action()'s own fallback
		// without needing a table instance here.
		$bulk_action = '';
		if ( ! empty( $_REQUEST['action'] ) && $_REQUEST['action'] !== '-1' ) {
			$bulk_action = sanitize_key( wp_unslash( $_REQUEST['action'] ) );
		} elseif ( ! empty( $_REQUEST['action2'] ) && $_REQUEST['action2'] !== '-1' ) {
			$bulk_action = sanitize_key( wp_unslash( $_REQUEST['action2'] ) );
		}
		if ( ! empty( $_POST['agx_ids'] ) && $bulk_action !== '' ) {
			check_admin_referer( 'bulk-' . $entity_type . 's' );
			$ids    = array_map( 'sanitize_text_field', wp_unslash( (array) $_POST['agx_ids'] ) );
			$action = $bulk_action;

			if ( $entity_type === 'enquiry' ) {
				foreach ( $ids as $id ) {
					AgriXpert_Verification::set_enquiry_status( $id, $action, $user_id );
				}
			} else {
				AgriXpert_Verification::bulk_decide( $entity_type, $ids, self::map_decision_to_status( $action ), $user_id );
			}
			self::redirect_with_notice( $entity_type, 'updated' );
		}

		// --- CSV export ---
		if ( isset( $_POST['agx_export'] ) && $_POST['agx_export'] === 'csv' ) {
			check_admin_referer( 'agrixpert_export_csv', 'agx_export_nonce' );
			self::export_csv( $entity_type );
		}
	}

	private static function redirect_with_notice( $entity_type, $notice ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'agrixpert-' . $entity_type, 'agx_notice' => $notice ), admin_url( 'admin.php' ) ) );
		exit;
	}

	private static function export_csv( $entity_type ) {
		$result = AgriXpert_Verification::get_entities( $entity_type, array( 'per_page' => 100000, 'paged' => 1 ) );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=agrixpert-' . $entity_type . 's-' . gmdate( 'Y-m-d' ) . '.csv' );

		$out = fopen( 'php://output', 'w' );
		if ( ! empty( $result['items'] ) ) {
			fputcsv( $out, array_keys( (array) $result['items'][0] ) );
			foreach ( $result['items'] as $row ) {
				fputcsv( $out, (array) $row );
			}
		}
		fclose( $out );
		exit;
	}
}

AgriXpert_Admin_Menu::init();
