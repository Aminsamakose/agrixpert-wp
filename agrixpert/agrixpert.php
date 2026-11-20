<?php
/**
 * Plugin Name: AgriXpert
 * Plugin URI: https://agrixpert.samakose.com
 * Description: Agricultural value-chain integration platform (farmer/buyer/supplier/cooperative registration, verification, marketplace, CRM, notifications) rebuilt natively for WordPress.
 * Version: 0.1.0-phase1
 * Author: Samakose Accelerator Lab
 * Text Domain: agrixpert
 * Requires at least: 6.4
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'AGRIXPERT_VERSION', '0.1.0-phase1' );
define( 'AGRIXPERT_PLUGIN_FILE', __FILE__ );
define( 'AGRIXPERT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'AGRIXPERT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'AGRIXPERT_DB_VERSION', '1.0.0' ); // bump + extend agrixpert_run_migrations() for future schema changes.

require_once AGRIXPERT_PLUGIN_DIR . 'includes/class-schema.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/class-roles.php';
require_once AGRIXPERT_PLUGIN_DIR . 'includes/class-activation.php';

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
 * never loses data (brief section 3.10).
 */
function agrixpert_deactivate() {
	do_action( 'agrixpert_deactivated' );
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
