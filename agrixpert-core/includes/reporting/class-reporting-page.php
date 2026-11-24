<?php
/**
 * Reports: a single admin screen summarizing the operational data this
 * plugin already collects (spec Part "reporting/analytics," Phase 10 —
 * new scope, nothing in either the live app or the prior agrixpert
 * plugin had this).
 *
 * Deliberately plain HTML tables with CSS-only bar indicators rather than
 * a JS charting library — no external dependency to manage on shared
 * (cPanel) hosting, consistent with the rest of this admin dashboard
 * (plain WP_List_Table screens, no React/build step — see the rebuild
 * charter's architecture decisions).
 *
 * This is a first cut built from what the schema already supports
 * (registration/verification counts, CRM pipeline distribution,
 * marketplace activity, follow-up completion) — not a response to a
 * specific requirements list, since none existed yet for this module.
 * Treat the four sections below as a starting point to react to, not a
 * finished spec: tell me what's missing or wrong and it gets adjusted.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Reporting_Page {

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ), 20 );
	}

	public static function register_menu() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_CRM ) ) {
			return;
		}
		add_submenu_page(
			'agrixpert',
			__( 'Reports', 'agrixpert' ),
			__( 'Reports', 'agrixpert' ),
			AgriXpert_Roles::CAP_MANAGE_CRM,
			'agrixpert-reports',
			array( __CLASS__, 'render' )
		);
	}

	public static function render() {
		if ( ! current_user_can( AgriXpert_Roles::CAP_MANAGE_CRM ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'agrixpert' ) );
		}

		echo '<div class="wrap agrixpert-wrap"><h1>' . esc_html__( 'Reports', 'agrixpert' ) . '</h1>';

		self::render_verification_funnel();
		self::render_pipeline_distribution();
		self::render_marketplace_activity();
		self::render_followup_health();

		echo '</div>';
	}

	// ---- Section 1: registrations by verification status -----------------

	private static function render_verification_funnel() {
		global $wpdb;
		echo '<h2>' . esc_html__( 'Registrations by Verification Status', 'agrixpert' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Counts as currently stored. If you recently ran the verification-status data fix (docs/VERIFICATION-STATUS-FIX.md), these reflect the corrected values.', 'agrixpert' ) . '</p>';
		echo '<table class="widefat striped agx-report-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Type', 'agrixpert' ) . '</th><th>' . esc_html__( 'Pending', 'agrixpert' ) . '</th><th>' . esc_html__( 'Verified', 'agrixpert' ) . '</th><th>' . esc_html__( 'Rejected', 'agrixpert' ) . '</th><th>' . esc_html__( 'Other (unexpected)', 'agrixpert' ) . '</th><th>' . esc_html__( 'Total', 'agrixpert' ) . '</th>';
		echo '</tr></thead><tbody>';

		$labels = array( 'farmer' => __( 'Farmers', 'agrixpert' ), 'buyer' => __( 'Buyers', 'agrixpert' ), 'supplier' => __( 'Suppliers', 'agrixpert' ), 'cooperative' => __( 'Cooperatives', 'agrixpert' ) );
		foreach ( AgriXpert_Verification::VERIFIABLE_TYPES as $type ) {
			$table  = AgriXpert_Verification::table_for( $type );
			$counts = $wpdb->get_results( "SELECT verification_status, COUNT(*) AS n FROM {$table} GROUP BY verification_status", OBJECT_K ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- fixed table name, no user input
			$pending  = (int) ( $counts['pending']->n ?? 0 );
			$verified = (int) ( $counts['verified']->n ?? 0 );
			$rejected = (int) ( $counts['rejected']->n ?? 0 );
			$total    = array_sum( array_map( fn( $row ) => (int) $row->n, $counts ) );
			$other    = $total - $pending - $verified - $rejected;

			printf(
				'<tr><td>%1$s</td><td>%2$d</td><td>%3$d</td><td>%4$d</td><td%5$s>%6$d</td><td>%7$d</td></tr>',
				esc_html( $labels[ $type ] ), $pending, $verified, $rejected,
				$other > 0 ? ' style="color:#a3233b;font-weight:600;"' : '',
				$other,
				$total
			);
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( '"Other (unexpected)" should always be 0 — any non-zero value here means a row exists with a verification_status outside pending/verified/rejected and is worth investigating directly.', 'agrixpert' ) . '</p>';
	}

	// ---- Section 2: CRM pipeline distribution -----------------------------

	private static function render_pipeline_distribution() {
		global $wpdb;
		echo '<h2>' . esc_html__( 'CRM Pipeline Distribution', 'agrixpert' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'A registration with no pipeline activity yet is counted as "New" by default (see class-crm.php::get_stage()), even though it has no row in the pipeline table until a stage is explicitly set.', 'agrixpert' ) . '</p>';

		$pipeline_table = AgriXpert_Schema::table( 'crm_pipeline' );
		$labels = array( 'farmer' => __( 'Farmers', 'agrixpert' ), 'buyer' => __( 'Buyers', 'agrixpert' ), 'supplier' => __( 'Suppliers', 'agrixpert' ), 'cooperative' => __( 'Cooperatives', 'agrixpert' ) );

		echo '<table class="widefat striped agx-report-table"><thead><tr><th>' . esc_html__( 'Type', 'agrixpert' ) . '</th>';
		foreach ( AgriXpert_CRM::STAGES as $stage ) {
			echo '<th>' . esc_html( ucfirst( $stage ) ) . '</th>';
		}
		echo '</tr></thead><tbody>';

		foreach ( AgriXpert_Verification::VERIFIABLE_TYPES as $type ) {
			$entity_table  = AgriXpert_Verification::table_for( $type );
			$total_entities = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$entity_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$stage_counts = $wpdb->get_results( $wpdb->prepare(
				"SELECT stage, COUNT(*) AS n FROM {$pipeline_table} WHERE entity_type = %s GROUP BY stage", $type
			), OBJECT_K );
			$explicitly_staged = array_sum( array_map( fn( $row ) => (int) $row->n, $stage_counts ) );
			$implicit_new      = max( 0, $total_entities - $explicitly_staged );

			echo '<tr><td>' . esc_html( $labels[ $type ] ) . '</td>';
			foreach ( AgriXpert_CRM::STAGES as $stage ) {
				$n = $stage === 'new'
					? $implicit_new + (int) ( $stage_counts['new']->n ?? 0 )
					: (int) ( $stage_counts[ $stage ]->n ?? 0 );
				echo '<td>' . self::bar_cell( $n, max( 1, $total_entities ) ) . '</td>';
			}
			echo '</tr>';
		}
		echo '</tbody></table>';
	}

	// ---- Section 3: marketplace activity, last 30 days --------------------

	private static function render_marketplace_activity() {
		global $wpdb;
		echo '<h2>' . esc_html__( 'Marketplace Activity (last 30 days)', 'agrixpert' ) . '</h2>';

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-30 days', current_time( 'timestamp', true ) ) );

		$listings_table  = AgriXpert_Schema::table( 'listings' );
		$by_type = $wpdb->get_results( $wpdb->prepare(
			"SELECT listing_type, COUNT(*) AS n FROM {$listings_table} WHERE created_at >= %s GROUP BY listing_type", $since
		), OBJECT_K );

		$interests_table = AgriXpert_Schema::table( 'listing_interests' );
		$interests_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$interests_table} WHERE created_at >= %s", $since ) );

		$eq_bookings_table = AgriXpert_Schema::table( 'equipment_bookings' );
		$eq_bookings_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$eq_bookings_table} WHERE created_at >= %s", $since ) );

		$storage_bookings_table = AgriXpert_Schema::table( 'storage_bookings' );
		$storage_bookings_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$storage_bookings_table} WHERE created_at >= %s", $since ) );

		echo '<table class="widefat striped agx-report-table"><tbody>';
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Supply listings posted', 'agrixpert' ), (int) ( $by_type['supply']->n ?? 0 ) );
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Demand listings posted', 'agrixpert' ), (int) ( $by_type['demand']->n ?? 0 ) );
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Service listings posted', 'agrixpert' ), (int) ( $by_type['service']->n ?? 0 ) );
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Expressions of interest', 'agrixpert' ), $interests_count );
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Equipment bookings requested', 'agrixpert' ), $eq_bookings_count );
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Storage bookings requested', 'agrixpert' ), $storage_bookings_count );
		echo '</tbody></table>';
	}

	// ---- Section 4: follow-up task health ----------------------------------

	private static function render_followup_health() {
		global $wpdb;
		echo '<h2>' . esc_html__( 'Follow-up Task Health', 'agrixpert' ) . '</h2>';

		$table = AgriXpert_Schema::table( 'crm_tasks' );
		$open      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'open' ) );
		$done      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", 'done' ) );
		$overdue   = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table} WHERE status = %s AND due_date IS NOT NULL AND due_date < %s", 'open', current_time( 'Y-m-d' )
		) );
		$total     = $open + $done;
		$completion_rate = $total > 0 ? round( ( $done / $total ) * 100 ) : null;

		echo '<table class="widefat striped agx-report-table"><tbody>';
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Open tasks', 'agrixpert' ), $open );
		printf( '<tr><td>%s</td><td%s>%d</td></tr>', esc_html__( 'Overdue (open, past due date)', 'agrixpert' ), $overdue > 0 ? ' style="color:#a3233b;font-weight:600;"' : '', $overdue );
		printf( '<tr><td>%s</td><td>%d</td></tr>', esc_html__( 'Completed tasks (all time)', 'agrixpert' ), $done );
		printf(
			'<tr><td>%s</td><td>%s</td></tr>',
			esc_html__( 'Completion rate', 'agrixpert' ),
			$completion_rate === null ? esc_html__( 'No tasks yet', 'agrixpert' ) : esc_html( $completion_rate . '%' )
		);
		echo '</tbody></table>';
	}

	// ---- Shared bar-cell renderer ------------------------------------------

	private static function bar_cell( $n, $denominator ) {
		$pct = $denominator > 0 ? min( 100, round( ( $n / $denominator ) * 100 ) ) : 0;
		return sprintf(
			'<div class="agx-report-bar-wrap"><span class="agx-report-bar" style="width:%1$d%%"></span><span class="agx-report-bar-label">%2$d</span></div>',
			$pct,
			(int) $n
		);
	}
}

AgriXpert_Reporting_Page::init();
