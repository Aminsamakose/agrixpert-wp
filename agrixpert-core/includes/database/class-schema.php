<?php
/**
 * Database schema for the AgriXpert plugin.
 *
 * This is a deliberate, table-for-table translation of the existing
 * Supabase/Postgres schema (22 tables) into WordPress/MySQL conventions:
 *
 *  - Postgres `uuid` primary/foreign keys -> MySQL CHAR(36), populated with
 *    wp_generate_uuid4() on insert. Kept as UUIDs (not AUTO_INCREMENT ids)
 *    so existing Supabase data can be migrated in without renumbering
 *    every foreign key across 22 tables.
 *  - `auth.users(id)` references -> BIGINT UNSIGNED referencing wp_users(ID).
 *    Any UUID actor id originally pointing at a Supabase auth user now
 *    points at a WordPress user ID instead; migration script in Phase 4
 *    handles the remap.
 *  - Row Level Security (Postgres) has no MySQL/WordPress equivalent.
 *    It is replaced by: (a) WordPress capability checks in every admin
 *    screen and REST endpoint (see class-roles.php), and (b) query-time
 *    ownership filters (WHERE user_id = get_current_user_id()) inside
 *    every data-access function in includes/data/. There is no database
 *    engine enforcing this the way Postgres RLS did — it is enforced in
 *    PHP, on every code path, which is why Phase 8 (security testing)
 *    treats this as the top risk area.
 *  - Postgres CHECK constraints -> enforced in PHP at the point of
 *    insert/update (sanitize-and-validate layer), not at the DB level;
 *    MySQL's CHECK support is inconsistent across hosting providers'
 *    MySQL/MariaDB versions, so correctness cannot depend on it.
 *  - jsonb columns -> MySQL JSON (5.7+/MariaDB 10.2+) or LONGTEXT with
 *    json_encode/json_decode at the PHP boundary on older hosts.
 *
 * All tables are prefixed agx_ and created via dbDelta(), which is
 * idempotent and additive — safe to call on every plugin update.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Schema {

	/**
	 * Returns the full table name with the site's WP table prefix applied.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'agx_' . $name;
	}

	public static function install_or_upgrade() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		foreach ( self::table_definitions( $charset_collate ) as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * One CREATE TABLE statement per array entry. dbDelta() is picky about
	 * formatting (two spaces after PRIMARY KEY, etc.) — these follow the
	 * WordPress Codex's documented dbDelta formatting rules exactly.
	 */
	private static function table_definitions( $charset_collate ) {
		$t = array();

		// ---- Registrations -------------------------------------------------
		$t[] = "CREATE TABLE " . self::table( 'enquiries' ) . " (
			id CHAR(36) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			name VARCHAR(255) NOT NULL,
			email VARCHAR(255) NOT NULL,
			enquiry_type VARCHAR(64) NOT NULL DEFAULT 'General Enquiry',
			message TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			PRIMARY KEY  (id),
			KEY status (status)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'farmers' ) . " (
			id CHAR(36) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			full_name VARCHAR(255) NOT NULL,
			phone VARCHAR(32) NOT NULL,
			email VARCHAR(255) NULL,
			location VARCHAR(255) NULL,
			farm_size_hectares DECIMAL(10,2) NULL,
			commodities TEXT NULL COMMENT 'JSON array',
			cooperative_or_group VARCHAR(255) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY verification_status (verification_status)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'buyers' ) . " (
			id CHAR(36) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			company_name VARCHAR(255) NOT NULL,
			contact_name VARCHAR(255) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(32) NULL,
			commodities_needed TEXT NULL COMMENT 'JSON array',
			quantity_required VARCHAR(255) NULL,
			quality_requirements TEXT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY verification_status (verification_status)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'suppliers' ) . " (
			id CHAR(36) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			company_name VARCHAR(255) NOT NULL,
			contact_name VARCHAR(255) NOT NULL,
			email VARCHAR(255) NOT NULL,
			phone VARCHAR(32) NULL,
			services TEXT NULL COMMENT 'JSON array',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			notes TEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY verification_status (verification_status)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'cooperatives' ) . " (
			id CHAR(36) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			name VARCHAR(255) NOT NULL,
			lead_contact_name VARCHAR(255) NOT NULL,
			email VARCHAR(255) NULL,
			phone VARCHAR(32) NULL,
			location_label VARCHAR(255) NULL,
			primary_commodities TEXT NULL COMMENT 'JSON array',
			member_count_estimate INT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			notes TEXT NULL,
			user_id BIGINT UNSIGNED NULL,
			verification_status VARCHAR(20) NOT NULL DEFAULT 'pending',
			verified_by BIGINT UNSIGNED NULL,
			verified_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY verification_status (verification_status)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'cooperative_members' ) . " (
			id CHAR(36) NOT NULL,
			cooperative_id CHAR(36) NOT NULL,
			farmer_id CHAR(36) NULL,
			member_name VARCHAR(255) NULL,
			role VARCHAR(20) NOT NULL DEFAULT 'member',
			member_status VARCHAR(20) NOT NULL DEFAULT 'active',
			joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY cooperative_id (cooperative_id),
			KEY farmer_id (farmer_id)
		) $charset_collate;";

		// ---- Staff / admin tier (parity with existing admins-by-email table)
		$t[] = "CREATE TABLE " . self::table( 'admins' ) . " (
			email VARCHAR(255) NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			role VARCHAR(20) NOT NULL DEFAULT 'admin',
			PRIMARY KEY  (email)
		) $charset_collate;";

		// ---- Site content (admin-editable copy/images) ---------------------
		$t[] = "CREATE TABLE " . self::table( 'site_content' ) . " (
			content_key VARCHAR(191) NOT NULL,
			group_name VARCHAR(100) NOT NULL,
			label VARCHAR(255) NOT NULL,
			type VARCHAR(20) NOT NULL DEFAULT 'text',
			value LONGTEXT NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (content_key)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'page_content' ) . " (
			entity_type VARCHAR(20) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			data LONGTEXT NOT NULL COMMENT 'JSON',
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (entity_type, slug)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'insights' ) . " (
			id CHAR(36) NOT NULL,
			title VARCHAR(255) NOT NULL,
			slug VARCHAR(191) NOT NULL,
			excerpt TEXT NOT NULL,
			body LONGTEXT NOT NULL,
			category VARCHAR(100) NOT NULL DEFAULT 'News',
			image_url VARCHAR(500) NOT NULL DEFAULT '',
			author VARCHAR(255) NOT NULL DEFAULT 'AgriXpert',
			published TINYINT(1) NOT NULL DEFAULT 0,
			published_at DATETIME NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) $charset_collate;";

		// ---- Audit trail -----------------------------------------------------
		$t[] = "CREATE TABLE " . self::table( 'activity_log' ) . " (
			id CHAR(36) NOT NULL,
			actor_id BIGINT UNSIGNED NULL,
			action VARCHAR(100) NOT NULL,
			entity_type VARCHAR(20) NOT NULL,
			entity_id CHAR(36) NOT NULL,
			performed_by BIGINT UNSIGNED NULL,
			before_state LONGTEXT NULL COMMENT 'JSON',
			after_state LONGTEXT NULL COMMENT 'JSON',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'status_history' ) . " (
			id CHAR(36) NOT NULL,
			entity_type VARCHAR(20) NOT NULL,
			entity_id CHAR(36) NOT NULL,
			old_status VARCHAR(20) NULL,
			new_status VARCHAR(20) NOT NULL,
			changed_by BIGINT UNSIGNED NULL,
			changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id)
		) $charset_collate;";

		// ---- Marketplace -------------------------------------------------
		$t[] = "CREATE TABLE " . self::table( 'listings' ) . " (
			id CHAR(36) NOT NULL,
			actor_id CHAR(36) NOT NULL,
			actor_type VARCHAR(20) NOT NULL,
			listing_type VARCHAR(20) NOT NULL,
			commodity VARCHAR(255) NULL,
			quantity DECIMAL(12,2) NULL,
			unit VARCHAR(50) NULL,
			price_indication VARCHAR(255) NULL,
			quality_grade VARCHAR(100) NULL,
			latitude DOUBLE NULL,
			longitude DOUBLE NULL,
			location_label VARCHAR(255) NULL,
			availability_start DATETIME NULL,
			availability_end DATETIME NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			batch_id CHAR(36) NULL,
			PRIMARY KEY  (id),
			KEY actor (actor_type, actor_id),
			KEY status (status)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'listing_interests' ) . " (
			id CHAR(36) NOT NULL,
			listing_id CHAR(36) NOT NULL,
			responder_id BIGINT UNSIGNED NOT NULL,
			message TEXT NULL,
			quantity_requested DECIMAL(12,2) NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			batch_id CHAR(36) NULL,
			PRIMARY KEY  (id),
			KEY listing_id (listing_id),
			KEY responder_id (responder_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'equipment' ) . " (
			id CHAR(36) NOT NULL,
			listing_id CHAR(36) NOT NULL,
			equipment_type VARCHAR(100) NOT NULL,
			capacity_spec VARCHAR(255) NULL,
			coverage_area VARCHAR(255) NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY listing_id (listing_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'equipment_bookings' ) . " (
			id CHAR(36) NOT NULL,
			equipment_id CHAR(36) NOT NULL,
			requester_id BIGINT UNSIGNED NOT NULL,
			listing_interest_id CHAR(36) NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'requested',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY equipment_id (equipment_id),
			KEY dates (start_date, end_date)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'storage_facilities' ) . " (
			id CHAR(36) NOT NULL,
			listing_id CHAR(36) NOT NULL,
			total_capacity_tonnes DECIMAL(12,2) NOT NULL,
			commodity_compatibility TEXT NULL COMMENT 'JSON array',
			location_label VARCHAR(255) NULL,
			latitude DOUBLE NULL,
			longitude DOUBLE NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY listing_id (listing_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'storage_bookings' ) . " (
			id CHAR(36) NOT NULL,
			facility_id CHAR(36) NOT NULL,
			requester_id BIGINT UNSIGNED NOT NULL,
			listing_interest_id CHAR(36) NULL,
			tonnage DECIMAL(12,2) NOT NULL,
			start_date DATE NOT NULL,
			end_date DATE NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'requested',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY facility_id (facility_id),
			KEY dates (start_date, end_date)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'chain_events' ) . " (
			id CHAR(36) NOT NULL,
			batch_id CHAR(36) NOT NULL,
			event_type VARCHAR(20) NOT NULL,
			actor_id BIGINT UNSIGNED NOT NULL,
			location VARCHAR(255) NULL,
			occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY batch_id (batch_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'accountability_events' ) . " (
			id CHAR(36) NOT NULL,
			actor_id CHAR(36) NOT NULL,
			event_type VARCHAR(20) NOT NULL,
			booking_table VARCHAR(30) NOT NULL,
			booking_id CHAR(36) NOT NULL,
			recorded_by BIGINT UNSIGNED NULL,
			notes TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY actor_id (actor_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'listing_reviews' ) . " (
			id CHAR(36) NOT NULL,
			subject_id CHAR(36) NOT NULL,
			reviewer_id BIGINT UNSIGNED NOT NULL,
			booking_table VARCHAR(30) NULL,
			booking_id CHAR(36) NULL,
			rating TINYINT NOT NULL,
			comment TEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY subject_id (subject_id)
		) $charset_collate;";

		// ---- CRM -----------------------------------------------------------
		$t[] = "CREATE TABLE " . self::table( 'crm_pipeline' ) . " (
			entity_type VARCHAR(20) NOT NULL,
			entity_id CHAR(36) NOT NULL,
			stage VARCHAR(20) NOT NULL DEFAULT 'new',
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_by BIGINT UNSIGNED NULL,
			PRIMARY KEY  (entity_type, entity_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'crm_notes' ) . " (
			id CHAR(36) NOT NULL,
			entity_type VARCHAR(20) NOT NULL,
			entity_id CHAR(36) NOT NULL,
			body TEXT NOT NULL,
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id)
		) $charset_collate;";

		$t[] = "CREATE TABLE " . self::table( 'crm_tasks' ) . " (
			id CHAR(36) NOT NULL,
			entity_type VARCHAR(20) NOT NULL,
			entity_id CHAR(36) NOT NULL,
			entity_label VARCHAR(255) NOT NULL,
			title VARCHAR(255) NOT NULL,
			due_date DATE NULL,
			status VARCHAR(10) NOT NULL DEFAULT 'open',
			created_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at DATETIME NULL,
			assigned_to VARCHAR(255) NULL,
			PRIMARY KEY  (id),
			KEY entity (entity_type, entity_id),
			KEY assigned_to (assigned_to),
			KEY due_date (due_date)
		) $charset_collate;";

		return $t;
	}

	/** All logical table names, for use by uninstall.php and data classes. */
	public static function all_table_short_names() {
		return array(
			'enquiries', 'farmers', 'buyers', 'suppliers', 'cooperatives',
			'cooperative_members', 'admins', 'site_content', 'page_content',
			'insights', 'activity_log', 'status_history', 'listings',
			'listing_interests', 'equipment', 'equipment_bookings',
			'storage_facilities', 'storage_bookings', 'chain_events',
			'accountability_events', 'listing_reviews', 'crm_pipeline',
			'crm_notes', 'crm_tasks',
		);
	}
}
