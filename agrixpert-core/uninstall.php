<?php
/**
 * Runs ONLY when the plugin is deleted from the Plugins screen with the
 * "delete data" confirmation — WordPress never calls this file on a plain
 * deactivation (see class-activation.php's note on that distinction).
 *
 * This is deliberately destructive: it drops every AgriXpert table,
 * removes plugin options, and removes the custom roles. A site owner who
 * only wants to pause the plugin should deactivate, not delete — this
 * file exists for someone who genuinely wants the data gone (brief
 * section 3.10: "support future updates without destroying existing
 * data" on upgrade, but uninstall is the one explicit exception WordPress
 * itself defines for that rule).
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/database/class-schema.php';
require_once __DIR__ . '/includes/permissions/class-roles.php';

global $wpdb;
foreach ( AgriXpert_Schema::all_table_short_names() as $short_name ) {
	$table = AgriXpert_Schema::table( $short_name );
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name is our own fixed namespace, not user input
}

delete_option( 'agrixpert_db_version' );
delete_option( 'agrixpert_activated_at' );
delete_option( 'agrixpert_settings' ); // matches AgriXpert_Settings::OPTION — not loaded here to avoid that class's admin-menu side effects.
delete_option( 'agrixpert_verification_gates' ); // matches AgriXpert_Settings::GATES_OPTION, added in this rebuild.
wp_clear_scheduled_hook( 'agrixpert_followup_digest_cron' );

AgriXpert_Roles::remove();
