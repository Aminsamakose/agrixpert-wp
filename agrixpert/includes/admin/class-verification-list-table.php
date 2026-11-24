<?php
/**
 * One WP_List_Table, parameterised by entity type, for all four
 * verification queues (farmers/buyers/suppliers/cooperatives) and the
 * enquiries inbox — mirroring the per-tab tables in the Next.js admin
 * dashboard's /admin page without hand-building four near-identical
 * screens.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class AgriXpert_Verification_List_Table extends WP_List_Table {

	private $entity_type;
	private $is_verifiable;
	private $stage_map;

	public function __construct( $entity_type ) {
		$this->entity_type  = $entity_type;
		$this->is_verifiable = in_array( $entity_type, AgriXpert_Verification::VERIFIABLE_TYPES, true );

		parent::__construct( array(
			'singular' => $entity_type,
			'plural'   => $entity_type . 's',
			'ajax'     => false,
		) );
	}

	public function get_columns() {
		$columns = array(
			'cb'          => '<input type="checkbox" />',
			'name'        => __( 'Name', 'agrixpert' ),
			'contact'     => __( 'Contact', 'agrixpert' ),
			'status'      => $this->is_verifiable ? __( 'Verification', 'agrixpert' ) : __( 'Status', 'agrixpert' ),
			'stage'       => __( 'Pipeline Stage', 'agrixpert' ),
			'created_at'  => __( 'Submitted', 'agrixpert' ),
		);
		if ( $this->entity_type === 'enquiry' ) {
			$columns['enquiry_type'] = __( 'Type', 'agrixpert' );
		}
		return $columns;
	}

	public function get_sortable_columns() {
		return array( 'created_at' => array( 'created_at', true ) );
	}

	public function get_bulk_actions() {
		if ( $this->is_verifiable ) {
			return array(
				'verify' => __( 'Verify', 'agrixpert' ),
				'reject' => __( 'Reject', 'agrixpert' ),
				'reset'  => __( 'Reset to Pending', 'agrixpert' ),
			);
		}
		return array(
			'in_progress' => __( 'Mark In Progress', 'agrixpert' ),
			'resolved'    => __( 'Mark Resolved', 'agrixpert' ),
			'archived'    => __( 'Archive', 'agrixpert' ),
		);
	}

	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="agx_ids[]" value="%s" />', esc_attr( $item->id ) );
	}

	public function column_name( $item ) {
		$name = esc_html( AgriXpert_Verification::display_name( $this->entity_type, $item ) );

		$crm_url = add_query_arg( array(
			'page'        => 'agrixpert-crm',
			'entity_type' => $this->entity_type,
			'entity_id'   => $item->id,
		), admin_url( 'admin.php' ) );
		$actions = array(
			'crm' => '<a href="' . esc_url( $crm_url ) . '">' . esc_html__( 'CRM', 'agrixpert' ) . '</a>',
		);

		if ( $this->is_verifiable ) {
			$verify = $this->row_action_url( $item->id, 'verify' );
			$reject = $this->row_action_url( $item->id, 'reject' );
			$reset  = $this->row_action_url( $item->id, 'reset' );
			$actions = array(
				'verify' => '<a href="' . esc_url( $verify ) . '">' . esc_html__( 'Verify', 'agrixpert' ) . '</a>',
				'reject' => '<a href="' . esc_url( $reject ) . '" onclick="return confirm(\'' . esc_js( __( 'Reject this registration?', 'agrixpert' ) ) . '\')">' . esc_html__( 'Reject', 'agrixpert' ) . '</a>',
				'reset'  => '<a href="' . esc_url( $reset ) . '">' . esc_html__( 'Reset to Pending', 'agrixpert' ) . '</a>',
			) + $actions;
		}

		return sprintf( '<strong>%s</strong>%s', $name, $this->row_actions( $actions ) );
	}

	private function row_action_url( $id, $decision ) {
		return wp_nonce_url(
			add_query_arg( array(
				'page'        => sanitize_key( $_GET['page'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read only, nonce checked on the target
				'agx_tab'     => $this->entity_type,
				'agx_action'  => 'decide',
				'agx_decision'=> $decision,
				'agx_id'      => $id,
			), admin_url( 'admin.php' ) ),
			'agrixpert_decide_' . $id
		);
	}

	public function column_contact( $item ) {
		$bits = array();
		if ( ! empty( $item->email ) ) {
			$bits[] = esc_html( $item->email );
		}
		if ( ! empty( $item->phone ) ) {
			$bits[] = esc_html( $item->phone );
		}
		return implode( '<br />', $bits );
	}

	public function column_status( $item ) {
		$status = $this->is_verifiable ? $item->verification_status : $item->status;
		$class  = 'agx-badge agx-badge-' . sanitize_html_class( $status );
		return '<span class="' . esc_attr( $class ) . '">' . esc_html( ucfirst( str_replace( '_', ' ', $status ) ) ) . '</span>';
	}

	public function column_created_at( $item ) {
		return esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $item->created_at ) );
	}

	public function column_enquiry_type( $item ) {
		return esc_html( $item->enquiry_type );
	}

	public function column_stage( $item ) {
		if ( ! isset( $this->stage_map ) ) {
			$this->stage_map = AgriXpert_CRM::get_stage_map( $this->entity_type );
		}
		$stage = $this->stage_map[ $item->id ] ?? 'new';
		return '<span class="agx-badge agx-badge-' . esc_attr( $stage ) . '">' . esc_html( ucfirst( $stage ) ) . '</span>';
	}

	public function column_default( $item, $column_name ) {
		return isset( $item->$column_name ) ? esc_html( $item->$column_name ) : '';
	}

	public function extra_tablenav( $which ) {
		if ( $which !== 'top' ) {
			return;
		}
		// $_REQUEST, not $_GET: the wrapping form (class-admin-menu.php) submits
		// as POST so bulk actions and CSV export reach $_POST correctly, so the
		// filter value arrives here as POST too — reading $_GET only would show
		// "All" as selected even while a filter is actually applied via prepare_items().
		$current = sanitize_key( $_REQUEST['agx_status'] ?? 'all' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display filter only
		$options = $this->is_verifiable
			? array( 'all' => __( 'All', 'agrixpert' ), 'pending' => __( 'Pending', 'agrixpert' ), 'verified' => __( 'Verified', 'agrixpert' ), 'rejected' => __( 'Rejected', 'agrixpert' ) )
			: array( 'all' => __( 'All', 'agrixpert' ), 'new' => __( 'New', 'agrixpert' ), 'in_progress' => __( 'In Progress', 'agrixpert' ), 'resolved' => __( 'Resolved', 'agrixpert' ), 'archived' => __( 'Archived', 'agrixpert' ) );
		echo '<div class="alignleft actions">';
		echo '<select name="agx_status">';
		foreach ( $options as $value => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $value ), selected( $current, $value, false ), esc_html( $label ) );
		}
		echo '</select>';
		submit_button( __( 'Filter', 'agrixpert' ), '', 'filter_action', false );
		echo ' <button type="submit" name="agx_export" value="csv" class="button">' . esc_html__( 'Export CSV', 'agrixpert' ) . '</button>';
		echo '</div>';
	}

	public function prepare_items() {
		$per_page = 20;
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$args = array(
			'search'        => sanitize_text_field( $_REQUEST['s'] ?? '' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only list filter
			'status_filter' => sanitize_key( $_REQUEST['agx_status'] ?? 'all' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'orderby'       => sanitize_key( $_REQUEST['orderby'] ?? 'created_at' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'order'         => sanitize_key( $_REQUEST['order'] ?? 'desc' ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'paged'         => $this->get_pagenum(),
			'per_page'      => $per_page,
		);

		$result       = AgriXpert_Verification::get_entities( $this->entity_type, $args );
		$this->items  = $result['items'];

		$this->set_pagination_args( array(
			'total_items' => $result['total'],
			'per_page'    => $per_page,
			'total_pages' => (int) ceil( $result['total'] / $per_page ),
		) );
	}
}
