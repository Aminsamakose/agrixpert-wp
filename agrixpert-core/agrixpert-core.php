<?php
/**
 * Plugin Name: AgriXpert Core
 * Plugin URI: https://agrixpert.samakose.com
 * Description: Agricultural value-chain integration platform (farmer/buyer/supplier/cooperative registration, verification, marketplace, CRM, notifications) rebuilt natively for WordPress, per the AgriXpert Master Software Reconstruction specification.
 * Version: 2.2.0
 * Author: Samakose Accelerator Lab
 * Text Domain: agrixpert
 * Requires at least: 6.4
 * Requires PHP: 8.0
 *
 * This replaces the earlier `agrixpert` plugin (Version 1.0.0-rc1) as a
 * ground-up rebuild into the module structure the master spec's Part 4.3
 * calls for, reorganizing the audited 1.0.0-rc1 codebase into
 * includes/{database,authentication,permissions,stakeholders,marketplace,
 * crm,followups,notifications,admin,portal,content}/ rather than the
 * previous data/public/admin split. Two deliberate deviations from the
 * spec's literal folder list, documented in
 * agrixpert-core-rebuild-phase2-architecture.md (project doc):
 *   - farmers/buyers/suppliers/cooperatives stay unified under
 *     includes/stakeholders/ (one config-driven set of classes, not four
 *     near-duplicate ones) — this is how the audited 1.0.0-rc1 code
 *     already worked, and splitting it would fragment a DRY design for no
 *     functional gain.
 *   - equipment/storage/bookings stay unified under includes/marketplace/
 *     for the same reason.
 * Text Domain is kept as 'agrixpert' (not 'agrixpert-core') to avoid a
 * purely cosmetic find/replace across every translatable string in the
 * plugin for zero functional benefit.
 *
 * New in this rebuild (not present in agrixpert 1.0.0-rc1 — see
 * agrixpert-core-rebuild-phase1-audit.md for the verification that the
 * live Next.js/Supabase app never did this either):
 *   - includes/authentication/class-registration-auth.php — immediate
 *     account creation + auto-login + Portal redirect on stakeholder
 *     registration (master spec Part 7).
 *   - AgriXpert_Roles::CAP_EXPORT_DATA — bulk CSV export scoped to Admin,
 *     not Staff (closes code-audit finding #1).
 *   - AgriXpert_Settings::GATES_OPTION — configurable, per-action
 *     verification gating on marketplace write actions (master spec Part
 *     7.4), defaulting to "not required" so an upgrade never silently
 *     blocks an existing site's unverified actors.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'AGRIXPERT_VERSION', '2.2.0' );
define( 'AGRIXPERT_PLUGIN_FILE', __FILE__ );
define( 'AGRIXPERT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGRIXPERT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGRIXPERT_DB_VERSION', '1.0.0' ); // bump + extend agrixpert_run_migrations() for future schema changes. Schema is unchanged from agrixpert 1.0.0-rc1, so this stays 1.0.0 — see class-schema.php.

require_once AGRIXPERT_PLUGIN_DIR . 'includes/database/class-schema.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/permissions/class-roles.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/database/class-activation.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/stakeholders/class-registrations.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/stakeholders/class-form-engine.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/stakeholders/class-registration-forms.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/stakeholders/class-verification.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/crm/class-crm.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/stakeholders/class-actors.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/marketplace/class-marketplace.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/stakeholders/class-cooperative-members.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/marketplace/class-marketplace-shortcode.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/portal/class-portal-shortcode.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/admin/class-settings.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/notifications/class-mailer.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/notifications/class-email-templates.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/notifications/class-notifications.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-content.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-content-shortcodes.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-images.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-page-library.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-page-library-shortcodes.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-insight-routing.php';

// New in this rebuild — see the file header above and the class's own docblock.
require_once AGRIXPERT_PLUGIN_DIR . 'includes/authentication/class-registration-auth.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/api/class-rest-api.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/migration/class-supabase-migration-cli.php'; // no-ops outside WP-CLI; see the file header for usage.

add_action( 'plugins_loaded', function () {
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/content/class-content-admin.php';
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/admin/class-verification-list-table.php';
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/admin/class-admin-menu.php';
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/crm/class-crm-page.php';
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/followups/class-followups-page.php';
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/admin/class-marketplace-admin.php';
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/admin/class-images-admin.php';
	require_once AGRIXPERT_PLUGIN_DIR . 'includes/reporting/class-reporting-page.php';
}, 20 );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style( 'agrixpert-forms', AGRIXPERT_PLUGIN_URL . 'assets/css/forms.css', array(), AGRIXPERT_VERSION );
	wp_enqueue_style( 'agrixpert-page-library', AGRIXPERT_PLUGIN_URL . 'assets/css/page-library.css', array(), AGRIXPERT_VERSION );
	wp_enqueue_script( 'agrixpert-marketplace-geo', AGRIXPERT_PLUGIN_URL . 'assets/js/marketplace-geo.js', array(), AGRIXPERT_VERSION, true );
} );

/**
 * Activation: create/upgrade all tables, register roles and capabilities.
 * Per WP convention, this never deletes data — reactivating or updating
 * the plugin always re-runs dbDelta(), which is additive/idempotent.
 */
function agrixpert_activate() {
	AgriXpert_Activation::run();
}
register_activation_hook( AGRIXPERT_PLUGIN_FILE, 'agrixpert_activate' );

/**
 * Deactivation: intentionally does nothing destructive. Tables, roles and
 * options are left in place so deactivate/reactivate is always safe and
 * never loses data.
 */
function agrixpert_deactivate() {
	do_action( 'agrixpert_deactivated' );
	// Clean up the /insights/{slug} rewrite rule registered in
	// includes/content/class-insight-routing.php — standard WP practice,
	// not destructive (unlike uninstall.php, this never touches data).
	flush_rewrite_rules();
}
register_deactivation_hook( AGRIXPERT_PLUGIN_FILE, 'agrixpert_deactivate' );

/**
 * Uninstall (full removal + "delete data") is handled in uninstall.php,
 * which WordPress only runs when the plugin is deleted from the Plugins
 * screen with the "delete data" confirmation — never on deactivate.
 */

add_action( 'plugins_loaded', 'agrixpert_check_db_version' );
function agrixpert_check_db_version() {
	$installed = get_option( 'agrixpert_db_version', '' );
	if ( $installed !== AGRIXPERT_DB_VERSION ) {
		AgriXpert_Schema::install_or_upgrade();
		update_option( 'agrixpert_db_version', AGRIXPERT_DB_VERSION );
	}
}

// Note: the wp_login -> claim_by_email() hook that links a newly
// logged-in WP user to any not-yet-claimed registration row sharing their
// email is registered inside includes/stakeholders/class-actors.php
// itself (unchanged from agrixpert 1.0.0-rc1), not here — no separate
// wiring needed in this bootstrap.
