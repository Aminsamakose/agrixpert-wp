<?php
/**
 * Cross-entity Follow-ups screen — matches FollowUpsManager.tsx: every
 * open task across all entity types in one list, a "My tasks only"
 * filter keyed to the logged-in user's email (same assignment-by-email
 * model as crm_tasks.assigned_to), and a "Show completed" toggle.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Followups_Page {

	public static function init() {
		add_action( 'admin_menu', function () {
			add_submenu_page(
				'agrixpert',
				__( 'Follow-ups', 'agrixpert' ),
				__( 'Follow-ups', 'agrixpert' ),
				AgriXpert_Roles::CAP_MANAGE_CRM,
				'agrixpert-followups',
				array( __CLASS__, 'render' )
			);
		}, 20 );
	}

	public static function render() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_CRM ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}

		$my_only        = ! empty( $_GET['agx_mine'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display filter only
		$show_completed = ! empty( $_GET['agx_completed'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$current_email  = wp_get_current_user()->user_email;

		$args = array( 'show_completed' => $show_completed );
		if ( $my_only ) {
			$args['assigned_to'] = $current_email;
		}
		$tasks = AgriXpert_CRM::get_followups( $args );

		$open_mine = count( AgriXpert_CRM::get_followups( array( 'assigned_to' => $current_email ) ) );

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'Follow-ups', 'agrixpert' ) . '</h1>';
		printf( '<p>%s</p>', esc_html( sprintf(
			/* translators: %d: number of open tasks assigned to the current user */
			_n( '%d task assigned to you.', '%d tasks assigned to you.', $open_mine, 'agrixpert' ), $open_mine
		) ) );

		echo '<form method="get"><input type="hidden" name="page" value="agrixpert-followups" />';
		echo '<label><input type="checkbox" name="agx_mine" value="1"' . checked( $my_only, true, false ) . ' onchange="this.form.submit()" /> ' . esc_html__( 'My tasks only', 'agrixpert' ) . '</label> ';
		echo '<label><input type="checkbox" name="agx_completed" value="1"' . checked( $show_completed, true, false ) . ' onchange="this.form.submit()" /> ' . esc_html__( 'Show completed', 'agrixpert' ) . '</label>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Entity', 'agrixpert' ) . '</th><th>' . esc_html__( 'Task', 'agrixpert' ) . '</th><th>' . esc_html__( 'Due', 'agrixpert' ) . '</th><th>' . esc_html__( 'Assigned', 'agrixpert' ) . '</th><th>' . esc_html__( 'Status', 'agrixpert' ) . '</th><th></th></tr></thead><tbody>';

		$today = current_time( 'Y-m-d' );
		foreach ( $tasks as $task ) {
			$overdue = $task->status === 'open' && $task->due_date && $task->due_date < $today;
			$due_today = $task->status === 'open' && $task->due_date === $today;
			$style = $overdue ? ' style="color:#991b1b;"' : ( $due_today ? ' style="color:#92400e;"' : '' );

			echo '<tr' . $style . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed literal values only
			echo '<td>' . esc_html( $task->entity_label ) . ' <small>(' . esc_html( ucfirst( $task->entity_type ) ) . ')</small></td>';
			echo '<td>' . esc_html( $task->title ) . '</td>';
			echo '<td>' . esc_html( $task->due_date ?: '—' ) . ( $overdue ? ' ' . esc_html__( '(Overdue)', 'agrixpert' ) : ( $due_today ? ' ' . esc_html__( '(Due today)', 'agrixpert' ) : '' ) ) . '</td>';
			echo '<td>' . esc_html( $task->assigned_to ?: __( 'Unassigned', 'agrixpert' ) ) . '</td>';
			echo '<td>' . esc_html( ucfirst( $task->status ) ) . '</td>';
			echo '<td><a href="' . esc_url( admin_url( 'admin.php?page=agrixpert-crm&entity_type=' . $task->entity_type . '&entity_id=' . $task->entity_id ) ) . '">' . esc_html__( 'Open', 'agrixpert' ) . '</a></td>';
			echo '</tr>';
		}
		if ( empty( $tasks ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No follow-up tasks.', 'agrixpert' ) . '</td></tr>';
		}
		echo '</tbody></table></div>';
	}
}

AgriXpert_Followups_Page::init();
