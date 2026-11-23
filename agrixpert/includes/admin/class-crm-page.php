<?php
/**
 * CRM detail screen for a single registration record: pipeline stage,
 * notes, and tasks. Reached via a "CRM" row action added to the
 * verification list table (not its own top-level menu item, matching how
 * CrmPanel.tsx is an expandable panel per row rather than a separate tab
 * in the live dashboard).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_CRM_Page {

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page(
				null, // hidden — reached only via row action links
				__( 'AgriXpert CRM', 'agrixpert' ),
				__( 'CRM', 'agrixpert' ),
				AgriXpert_Roles::CAP_MANAGE_CRM,
				'agrixpert-crm',
				array( __CLASS__, 'render' )
			);
		} );
		add_action( 'admin_init', array( __CLASS__, 'maybe_handle_post' ) );
	}

	private static function context() {
		return array(
			'entity_type' => sanitize_key( $_REQUEST['entity_type'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'entity_id'   => sanitize_text_field( $_REQUEST['entity_id'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
	}

	public static function maybe_handle_post() {
		if ( empty( $_POST['agx_crm_action'] ) || ! current_user_can( AgriXpert_Roles::CAP_MANAGE_CRM ) ) {
			return;
		}
		check_admin_referer( 'agrixpert_crm' );

		$ctx    = self::context();
		$action = sanitize_key( $_POST['agx_crm_action'] );
		$user_id = get_current_user_id();

		switch ( $action ) {
			case 'set_stage':
				AgriXpert_CRM::set_stage( $ctx['entity_type'], $ctx['entity_id'], sanitize_key( $_POST['stage'] ?? '' ), $user_id );
				break;

			case 'add_note':
				$body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );
				if ( $body !== '' ) {
					AgriXpert_CRM::add_note( $ctx['entity_type'], $ctx['entity_id'], $body, $user_id );
				}
				break;

			case 'update_note':
				AgriXpert_CRM::update_note( sanitize_text_field( $_POST['note_id'] ?? '' ), sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ) );
				break;

			case 'delete_note':
				AgriXpert_CRM::delete_note( sanitize_text_field( $_POST['note_id'] ?? '' ) );
				break;

			case 'add_task':
				$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
				if ( $title !== '' ) {
					$row   = AgriXpert_Verification::get_entity( $ctx['entity_type'], $ctx['entity_id'] );
					$label = $row ? AgriXpert_Verification::display_name( $ctx['entity_type'], $row ) : $ctx['entity_id'];
					AgriXpert_CRM::add_task(
						$ctx['entity_type'], $ctx['entity_id'], $label, $title,
						sanitize_text_field( $_POST['due_date'] ?? '' ),
						sanitize_email( $_POST['assigned_to'] ?? '' ),
						$user_id
					);
				}
				break;

			case 'update_task':
				AgriXpert_CRM::update_task(
					sanitize_text_field( $_POST['task_id'] ?? '' ),
					sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
					sanitize_text_field( $_POST['due_date'] ?? '' ),
					sanitize_email( $_POST['assigned_to'] ?? '' )
				);
				break;

			case 'toggle_task':
				AgriXpert_CRM::set_task_status( sanitize_text_field( $_POST['task_id'] ?? '' ), $_POST['new_status'] === 'done' ? 'done' : 'open' );
				break;

			case 'delete_task':
				AgriXpert_CRM::delete_task( sanitize_text_field( $_POST['task_id'] ?? '' ) );
				break;
		}

		wp_safe_redirect( add_query_arg( array(
			'page'        => 'agrixpert-crm',
			'entity_type' => $ctx['entity_type'],
			'entity_id'   => $ctx['entity_id'],
		), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function render() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_CRM ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}

		$ctx = self::context();
		$row = AgriXpert_Verification::get_entity( $ctx['entity_type'], $ctx['entity_id'] );
		if ( ! $row ) {
			wp_die( esc_html__( 'Record not found.', 'agrixpert' ) );
		}

		$label       = AgriXpert_Verification::display_name( $ctx['entity_type'], $row );
		$stage       = AgriXpert_CRM::get_stage( $ctx['entity_type'], $ctx['entity_id'] );
		$notes       = AgriXpert_CRM::get_notes( $ctx['entity_type'], $ctx['entity_id'] );
		$tasks       = AgriXpert_CRM::get_tasks( $ctx['entity_type'], $ctx['entity_id'] );
		$staff       = AgriXpert_CRM::get_staff_emails();
		$back_url    = admin_url( 'admin.php?page=agrixpert-' . $ctx['entity_type'] );

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html( $label ) . '</h1>';
		echo '<p><a href="' . esc_url( $back_url ) . '">&larr; ' . esc_html__( 'Back to list', 'agrixpert' ) . '</a></p>';

		// --- Stage ---
		echo '<h2>' . esc_html__( 'Pipeline Stage', 'agrixpert' ) . '</h2>';
		echo '<form method="post">';
		self::nonce_and_ctx( $ctx );
		echo '<input type="hidden" name="agx_crm_action" value="set_stage" />';
		echo '<select name="stage">';
		foreach ( AgriXpert_CRM::STAGES as $s ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $s ), selected( $stage, $s, false ), esc_html( ucfirst( $s ) ) );
		}
		echo '</select> ';
		submit_button( __( 'Update Stage', 'agrixpert' ), 'secondary', '', false );
		echo '</form>';

		// --- Notes ---
		echo '<h2>' . esc_html__( 'Notes', 'agrixpert' ) . '</h2>';
		echo '<form method="post" class="agx-crm-add">';
		self::nonce_and_ctx( $ctx );
		echo '<input type="hidden" name="agx_crm_action" value="add_note" />';
		echo '<textarea name="body" rows="2" placeholder="' . esc_attr__( 'Add a note…', 'agrixpert' ) . '" required></textarea> ';
		submit_button( __( 'Add Note', 'agrixpert' ), 'primary', '', false );
		echo '</form>';
		echo '<ul class="agx-crm-list">';
		foreach ( $notes as $note ) {
			echo '<li><p>' . nl2br( esc_html( $note->body ) ) . '</p>';
			echo '<small>' . esc_html( mysql2date( 'd M Y, H:i', $note->created_at ) ) . '</small> ';
			echo '<form method="post" style="display:inline">';
			self::nonce_and_ctx( $ctx );
			echo '<input type="hidden" name="agx_crm_action" value="delete_note" /><input type="hidden" name="note_id" value="' . esc_attr( $note->id ) . '" />';
			echo '<button type="submit" class="button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Delete this note?', 'agrixpert' ) ) . '\')">' . esc_html__( 'Delete', 'agrixpert' ) . '</button>';
			echo '</form></li>';
		}
		echo '</ul>';

		// --- Tasks ---
		echo '<h2>' . esc_html__( 'Follow-up Tasks', 'agrixpert' ) . '</h2>';
		echo '<form method="post" class="agx-crm-add">';
		self::nonce_and_ctx( $ctx );
		echo '<input type="hidden" name="agx_crm_action" value="add_task" />';
		echo '<input type="text" name="title" placeholder="' . esc_attr__( 'Task title', 'agrixpert' ) . '" required /> ';
		echo '<input type="date" name="due_date" /> ';
		self::staff_select( $staff );
		submit_button( __( 'Add Task', 'agrixpert' ), 'primary', '', false );
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Task', 'agrixpert' ) . '</th><th>' . esc_html__( 'Due', 'agrixpert' ) . '</th><th>' . esc_html__( 'Assigned', 'agrixpert' ) . '</th><th>' . esc_html__( 'Status', 'agrixpert' ) . '</th><th></th></tr></thead><tbody>';
		foreach ( $tasks as $task ) {
			$overdue = $task->status === 'open' && $task->due_date && $task->due_date < current_time( 'Y-m-d' );
			echo '<tr' . ( $overdue ? ' style="color:#991b1b;"' : '' ) . '>';
			echo '<td>' . esc_html( $task->title ) . '</td>';
			echo '<td>' . esc_html( $task->due_date ?: '—' ) . '</td>';
			echo '<td>' . esc_html( $task->assigned_to ?: __( 'Unassigned', 'agrixpert' ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $task->status ) ) . '</td>';
			echo '<td>';
			echo '<form method="post" style="display:inline">';
			self::nonce_and_ctx( $ctx );
			echo '<input type="hidden" name="agx_crm_action" value="toggle_task" /><input type="hidden" name="task_id" value="' . esc_attr( $task->id ) . '" /><input type="hidden" name="new_status" value="' . esc_attr( $task->status === 'done' ? 'open' : 'done' ) . '" />';
			echo '<button type="submit" class="button">' . ( $task->status === 'done' ? esc_html__( 'Reopen', 'agrixpert' ) : esc_html__( 'Mark Done', 'agrixpert' ) ) . '</button>';
			echo '</form> ';
			echo '<form method="post" style="display:inline">';
			self::nonce_and_ctx( $ctx );
			echo '<input type="hidden" name="agx_crm_action" value="delete_task" /><input type="hidden" name="task_id" value="' . esc_attr( $task->id ) . '" />';
			echo '<button type="submit" class="button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Delete this task?', 'agrixpert' ) ) . '\')">' . esc_html__( 'Delete', 'agrixpert' ) . '</button>';
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div>';
	}

	private static function nonce_and_ctx( $ctx ) {
		wp_nonce_field( 'agrixpert_crm' );
		self::hidden_ctx_fields( $ctx );
	}

	private static function hidden_ctx_fields( $ctx ) {
		printf(
			'<input type="hidden" name="entity_type" value="%s" /><input type="hidden" name="entity_id" value="%s" />',
			esc_attr( $ctx['entity_type'] ), esc_attr( $ctx['entity_id'] )
		);
	}

	private static function staff_select( $staff ) {
		echo '<select name="assigned_to"><option value="">' . esc_html__( 'Unassigned', 'agrixpert' ) . '</option>';
		foreach ( $staff as $email ) {
			printf( '<option value="%s">%s</option>', esc_attr( $email ), esc_html( $email ) );
		}
		echo '</select> ';
	}
}

AgriXpert_CRM_Page::init();
