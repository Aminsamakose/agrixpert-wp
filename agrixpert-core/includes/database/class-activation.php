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

		require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-content.php';
		// Real copy migrated from the live Next.js app's page files and
		// src/lib/site-data.ts (see docs/CONTENT-MIGRATION.md), not
		// placeholder text — these are the actual hero headline/description
		// pairs an admin drops into each WP Page with
		// [agrixpert_content key="..."], one per field, matching the dotted
		// key-per-page convention the live app already used for the same
		// site_content table (home.hero.title, about.hero.title, etc.) so
		// the key names carry over unchanged if this ever needs to read
		// from the same data again.
		AgriXpert_Content::seed_defaults( array(
			'home.hero.title'                 => array( 'group' => 'Home', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Connecting Agricultural Production to Profitable Markets.' ),
			'home.hero.description'           => array( 'group' => 'Home', 'label' => 'Hero Description', 'type' => 'textarea', 'sort_order' => 2, 'value' => 'AgriXpert builds commercially sustainable agricultural supply chains by connecting farmers, agricultural service providers, processors and buyers through coordinated production, aggregation, value addition and market access.' ),
			'about.hero.title'                => array( 'group' => 'About', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Connecting Agricultural Production to Profitable Markets' ),
			'about.hero.description'          => array( 'group' => 'About', 'label' => 'Hero Description', 'type' => 'textarea', 'sort_order' => 2, 'value' => 'AgriXpert is a market-led agricultural value chain integration company focused on connecting farmers, agricultural businesses, service providers, processors, commodity suppliers and commercial buyers.' ),
			'what-we-do.hero.title'           => array( 'group' => 'What We Do', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Production, Aggregation, Mechanisation and Supply Chain Solutions' ),
			'what-we-do.hero.description'     => array( 'group' => 'What We Do', 'label' => 'Hero Description', 'type' => 'textarea', 'sort_order' => 2, 'value' => 'AgriXpert connects every stage of the agricultural value chain into one coordinated commercial system — from farm to firm.' ),
			'for-farmers.hero.title'          => array( 'group' => 'For Farmers', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Farmer registration, production support and market access' ),
			'for-buyers.hero.title'           => array( 'group' => 'For Buyers', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Commodity sourcing, procurement and supply agreements' ),
			'for-cooperatives.hero.title'     => array( 'group' => 'For Cooperatives', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Cooperative registration, group market access and member management' ),
			'partnerships.hero.title'         => array( 'group' => 'Partnerships', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Institutional, commercial and investment partnerships' ),
			'contact.hero.title'              => array( 'group' => 'Contact', 'label' => 'Hero Title', 'type' => 'text', 'sort_order' => 1, 'value' => 'Enquiries and business contacts' ),
			'contact.email'                   => array( 'group' => 'Contact', 'label' => 'Contact Email', 'type' => 'text', 'sort_order' => 2, 'value' => 'agrixpert@samakose.com' ),
			'contact.phone'                   => array( 'group' => 'Contact', 'label' => 'Contact Phone', 'type' => 'text', 'sort_order' => 3, 'value' => '+233 (0) 55-858-9254' ),
			'contact.address'                 => array( 'group' => 'Contact', 'label' => 'Contact Address', 'type' => 'text', 'sort_order' => 4, 'value' => 'Tamale, Northern Ghana' ),
		) );

		// Create the real, migrated WordPress Pages (see
		// includes/content/class-page-seeder.php and docs/CONTENT-MIGRATION.md)
		// so activation leaves a working site rather than an empty shell.
		// Idempotent — only creates a page if nothing already exists at
		// that slug, same as every other seeding step here.
		require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-page-seeder.php';
		AgriXpert_Page_Seeder::run();

		// The /insights/{slug} rewrite rule (class-insight-routing.php) was
		// already registered on 'init' earlier in this same request, so
		// flushing now picks it up immediately — without this, individual
		// insight pages 404 until something else happens to flush rewrite
		// rules (e.g. visiting Settings → Permalinks).
		flush_rewrite_rules();
	}
}
