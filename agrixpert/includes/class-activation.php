<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Activation {

	public static function run() {
		AgriXpert_Schema::install_or_upgrade();
		update_option( 'agrixpert_db_version', AGRIXPERT_DB_VERSION );

		AgriXpert_Roles::register();

		// Seed the installing admin's own email into the agx_admins table,
		// mirroring the Supabase `admins` table's bootstrap behaviour, so
		// there is always at least one full admin after activation.
		global $wpdb;
		$admins_table = AgriXpert_Schema::table( 'admins' );
		$current_user = wp_get_current_user();
		if ( $current_user && $current_user->user_email ) {
			$wpdb->query(
				$wpdb->prepare(
					"INSERT IGNORE INTO {$admins_table} (email, role) VALUES (%s, 'admin')",
					$current_user->user_email
				)
			);
			$current_user->add_role( 'agrixpert_admin' );
		}

		if ( ! get_option( 'agrixpert_activated_at' ) ) {
			update_option( 'agrixpert_activated_at', current_time( 'mysql' ) );
		}

		require_once AGRIXPERT_PLUGIN_DIR . 'includes/data/class-content.php';
		AgriXpert_Content::seed_defaults( array(
			'hero_heading'    => array( 'group' => 'Home', 'label' => 'Hero Heading', 'type' => 'text', 'sort_order' => 1, 'value' => 'Transforming Agribusiness Across Ghana' ),
			'hero_subheading' => array( 'group' => 'Home', 'label' => 'Hero Subheading', 'type' => 'textarea', 'sort_order' => 2, 'value' => 'AgriXpert connects farmers, buyers, suppliers and cooperatives into one trusted value chain.' ),
			'contact_email'   => array( 'group' => 'Contact', 'label' => 'Contact Email', 'type' => 'text', 'sort_order' => 1, 'value' => get_option( 'admin_email' ) ),
			'contact_phone'   => array( 'group' => 'Contact', 'label' => 'Contact Phone', 'type' => 'text', 'sort_order' => 2, 'value' => '' ),
		) );
	}
}
