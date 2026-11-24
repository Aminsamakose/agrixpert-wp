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
			)
		);

		// Site administrators (manage_options) always get full AgriXpert
		// access too, so the person who installs the plugin is never locked
		// out of it.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( self::CAP_MANAGE_ALL );
			$administrator->add_cap( self::CAP_VERIFY );
			$administrator->add_cap( self::CAP_MANAGE_CRM );
			$administrator->add_cap( self::CAP_MODERATE_MKT );
		}
	}

	/** Called from uninstall.php only — never on plain deactivate. */
	public static function remove() {
		remove_role( 'agrixpert_staff' );
		remove_role( 'agrixpert_admin' );
	}

	public static function current_user_is_staff_or_admin() {
		return current_user_can( self::CAP_VERIFY ) || current_user_can( self::CAP_MANAGE_ALL );
	}
}
