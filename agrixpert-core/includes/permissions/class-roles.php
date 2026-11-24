<?php
/**
 * WordPress roles/capabilities standing in for Supabase RLS + the
 * admin/staff tier that previously lived in the `admins` table.
 *
 * Design decision (flagged for Amin's sign-off, see project plan doc):
 * capability checks replace RLS policies throughout. Every admin screen
 * and REST route must call current_user_can() before touching data —
 * there is no database-level backstop the way Postgres RLS provided.
 * This is the single highest-risk area in the rewrite and is the
 * dedicated focus of Phase 8 (security testing).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Roles {

	const CAP_MANAGE_ALL   = 'agx_manage_all';      // full admin: content, staff list, everything staff can do
	const CAP_VERIFY       = 'agx_verify_entities';  // approve/reject registrations
	const CAP_MANAGE_CRM   = 'agx_manage_crm';       // pipeline, notes, tasks
	const CAP_MODERATE_MKT = 'agx_moderate_marketplace';
	const CAP_EXPORT_DATA  = 'agx_export_data';      // bulk PII export (CSV) — deliberately NOT granted to Staff by default; see the code-audit finding this closes

	/**
	 * The four stakeholder-facing roles from the master spec (Part 8).
	 * These carry only `read` — a stakeholder's access to their own data
	 * is enforced by ownership (user_id matching), exactly like the
	 * existing Portal/Marketplace ownership checks in
	 * includes/stakeholders/class-actors.php, not by a WordPress
	 * capability. The role exists for clarity in the Users list and for
	 * any future capability that genuinely should differ by stakeholder
	 * type — not because the current code checks it anywhere.
	 */
	const STAKEHOLDER_ROLES = array(
		'farmer'      => 'agx_farmer',
		'buyer'       => 'agx_buyer',
		'supplier'    => 'agx_supplier',
		'cooperative' => 'agx_cooperative_lead',
	);

	public static function register() {
		add_role(
			'agrixpert_staff',
			__( 'AgriXpert Staff', 'agrixpert' ),
			array(
				'read'                  => true,
				self::CAP_VERIFY        => true,
				self::CAP_MANAGE_CRM     => true,
				self::CAP_MODERATE_MKT   => true,
			)
		);

		add_role(
			'agrixpert_admin',
			__( 'AgriXpert Admin', 'agrixpert' ),
			array(
				'read'                  => true,
				self::CAP_VERIFY        => true,
				self::CAP_MANAGE_CRM     => true,
				self::CAP_MODERATE_MKT   => true,
				self::CAP_MANAGE_ALL    => true,
				self::CAP_EXPORT_DATA   => true,
			)
		);

		add_role( 'agx_farmer', __( 'Farmer', 'agrixpert' ), array( 'read' => true ) );
		add_role( 'agx_buyer', __( 'Buyer', 'agrixpert' ), array( 'read' => true ) );
		add_role( 'agx_supplier', __( 'Supplier', 'agrixpert' ), array( 'read' => true ) );
		add_role( 'agx_cooperative_lead', __( 'Cooperative Lead', 'agrixpert' ), array( 'read' => true ) );

		// Site administrators (manage_options) always get full AgriXpert
		// access too, so the person who installs the plugin is never locked
		// out of it.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::CAP_MANAGE_ALL );
			$administrator->add_cap( self::CAP_VERIFY );
			$administrator->add_cap( self::CAP_MANAGE_CRM );
			$administrator->add_cap( self::CAP_MODERATE_MKT );
			$administrator->add_cap( self::CAP_EXPORT_DATA );
		}
	}

	/** Called from uninstall.php only — never on plain deactivate. */
	public static function remove() {
		remove_role( 'agrixpert_staff' );
		remove_role( 'agrixpert_admin' );
		foreach ( self::STAKEHOLDER_ROLES as $role ) {
			remove_role( $role );
		}
	}

	public static function current_user_is_staff_or_admin() {
		return current_user_can( self::CAP_VERIFY ) || current_user_can( self::CAP_MANAGE_ALL );
	}
}
