<?php
/**
 * One-time data migration: pulls rows out of the live Supabase/Postgres
 * database and inserts them into agrixpert-core's MySQL tables (spec
 * Phase 11 — Supabase-to-MySQL data migration).
 *
 * This is a WP-CLI command, not an admin-UI button: it is a one-time
 * operational task run by whoever controls both the Supabase service
 * role key and the target WordPress install, not something to expose to
 * a browser session or leave reachable without a shell. It never runs
 * unless WP-CLI itself is present (`defined('WP_CLI')`), so it adds zero
 * surface to a normal web request.
 *
 * USAGE (from the WordPress root, with WP-CLI installed):
 *
 *   wp agrixpert migrate-supabase --url=https://xxxx.supabase.co --key=SERVICE_ROLE_KEY
 *
 *   (dry run by default — reports what it WOULD do without writing
 *   anything. Add --execute to actually write.)
 *
 *   wp agrixpert migrate-supabase --url=... --key=... --execute
 *   wp agrixpert migrate-supabase --url=... --key=... --execute --only=farmers,suppliers
 *   wp agrixpert migrate-supabase --url=... --key=... --execute --include-content
 *
 * You can also set AGRIXPERT_SUPABASE_URL / AGRIXPERT_SUPABASE_SERVICE_KEY
 * as environment variables instead of passing --url/--key, so the service
 * role key never has to sit in your shell history.
 *
 * WHAT THIS DOES AND DOES NOT DO — read before running with --execute:
 *
 * 1. It is idempotent and additive, never destructive: for every row it
 *    fetches, it checks whether a row with that same primary key already
 *    exists in the MySQL table and skips it if so (use --overwrite to
 *    force-update instead — off by default). It never deletes or
 *    truncates anything.
 *
 * 2. Content tables (site_content, page_content, insights) are SKIPPED
 *    BY DEFAULT. The agrixpert-core plugin's own activation hook
 *    (class-activation.php) already seeds these with the real migrated
 *    copy as PHP defaults — running this script against them too risks
 *    overwriting content you've already edited through wp-admin with
 *    stale Supabase values. Pass --include-content only if you
 *    specifically know you want the raw Supabase content instead of
 *    what the plugin already seeded, and check the result afterward.
 *
 * 3. Any column that referenced a Supabase auth.users(id) in Postgres
 *    (user_id, verified_by, responder_id, requester_id, created_by,
 *    updated_by, changed_by, performed_by, recorded_by, reviewer_id,
 *    and chain_events/accountability_events.actor_id) is migrated as
 *    NULL. There is no WordPress user that corresponds to a Supabase
 *    auth user — these are two separate identity systems and the whole
 *    point of class-registration-auth.php / class-actors.php is that
 *    linkage now happens by matching EMAIL at WordPress login time
 *    (claim_by_email()), not by carrying over an old auth id. This
 *    script prints every dropped reference it finds (table, row id,
 *    column, original Supabase UUID) so nothing is silently lost — if
 *    you need to know who verified a specific registration historically,
 *    check that output (or query Supabase directly) before it's
 *    migrated, since the MySQL side will show NULL / "system" for it.
 *
 * 4. Postgres TEXT[] array columns (commodities, commodities_needed,
 *    services, primary_commodities, commodity_compatibility) are
 *    re-encoded as JSON strings, matching how class-schema.php stores
 *    them (TEXT columns, JSON-encoded at the PHP boundary) and how
 *    class-content.php / the registration forms already read them back.
 *
 * 5. Timestamps: Postgres `timestamptz` columns come back from the
 *    Supabase REST API as UTC ISO-8601 strings; they are converted to
 *    plain `Y-m-d H:i:s` for MySQL DATETIME columns, in UTC. Be aware
 *    this differs from new rows WordPress itself creates going forward,
 *    which use `current_time('mysql')` — SITE-LOCAL time, not UTC. If
 *    your site's timezone (Settings -> General) is not UTC, migrated
 *    historical timestamps and newly-created ones will be in different
 *    zones in the raw database. This has no functional effect on this
 *    plugin's own logic (nothing compares across that boundary), but
 *    would affect a hand-written report that sorts/filters by exact
 *    time across old and new rows. Flagging it rather than silently
 *    picking one; ask if you want this normalized to site-local instead.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

class AgriXpert_Supabase_Migration_Cli {

	/**
	 * Tables NOT in this list (chain_events, accountability_events,
	 * equipment, equipment_bookings, storage_facilities, storage_bookings,
	 * listing_reviews, cooperatives, cooperative_members, crm_notes,
	 * crm_tasks, enquiries) are still migrated using the generic path
	 * below — this map only needs entries where something is NOT a
	 * straight 1:1 column copy (a renamed column, a composite key, or an
	 * auth.users reference to null out and log).
	 */
	private static function table_defs() {
		$auth_ref = 'AUTH_REF'; // marker: null this column, log the original value

		return array(
			'enquiries'             => array( 'pk' => array( 'id' ) ),
			'farmers'               => array( 'pk' => array( 'id' ), 'json' => array( 'commodities' ), 'null_and_log' => array( 'user_id' => $auth_ref, 'verified_by' => $auth_ref ) ),
			'buyers'                => array( 'pk' => array( 'id' ), 'json' => array( 'commodities_needed' ), 'null_and_log' => array( 'user_id' => $auth_ref, 'verified_by' => $auth_ref ) ),
			'suppliers'             => array( 'pk' => array( 'id' ), 'json' => array( 'services' ), 'null_and_log' => array( 'user_id' => $auth_ref, 'verified_by' => $auth_ref ) ),
			'cooperatives'          => array( 'pk' => array( 'id' ), 'json' => array( 'primary_commodities' ), 'null_and_log' => array( 'user_id' => $auth_ref, 'verified_by' => $auth_ref ) ),
			'cooperative_members'   => array( 'pk' => array( 'id' ) ),
			'admins'                => array( 'pk' => array( 'email' ) ),
			'site_content'          => array( 'pk' => array( 'content_key' ), 'rename' => array( 'key' => 'content_key' ), 'is_content' => true ),
			'page_content'          => array( 'pk' => array( 'entity_type', 'slug' ), 'is_content' => true, 'json' => array( 'data' ) ),
			'insights'              => array( 'pk' => array( 'id' ), 'is_content' => true, 'bool_to_int' => array( 'published' ) ),
			'activity_log'          => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'performed_by' => $auth_ref ), 'json' => array( 'before_state', 'after_state' ) ), // actor_id here is already a stakeholder/system id, not an auth ref — left as-is
			'status_history'        => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'changed_by' => $auth_ref ) ),
			'listings'              => array( 'pk' => array( 'id' ) ), // actor_id is a stakeholder-table id, not an auth ref — kept as-is
			'listing_interests'     => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'responder_id' => $auth_ref ) ),
			'equipment'             => array( 'pk' => array( 'id' ) ),
			'equipment_bookings'    => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'requester_id' => $auth_ref ) ),
			'storage_facilities'    => array( 'pk' => array( 'id' ), 'json' => array( 'commodity_compatibility' ) ),
			'storage_bookings'      => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'requester_id' => $auth_ref ) ),
			'chain_events'          => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'actor_id' => $auth_ref ) ), // here actor_id IS an auth ref per the Supabase FK — unlike listings.actor_id
			'accountability_events' => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'actor_id' => $auth_ref, 'recorded_by' => $auth_ref ) ),
			'listing_reviews'       => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'subject_id' => $auth_ref, 'reviewer_id' => $auth_ref ) ),
			'crm_pipeline'          => array( 'pk' => array( 'entity_type', 'entity_id' ), 'null_and_log' => array( 'updated_by' => $auth_ref ) ),
			'crm_notes'             => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'created_by' => $auth_ref ) ),
			'crm_tasks'             => array( 'pk' => array( 'id' ), 'null_and_log' => array( 'created_by' => $auth_ref ) ), // assigned_to is a plain email string in both schemas — not touched
		);
	}

	/**
	 * @when invoke
	 */
	public function __invoke( $args, $assoc_args ) {
		$url = $assoc_args['url'] ?? getenv( 'AGRIXPERT_SUPABASE_URL' );
		$key = $assoc_args['key'] ?? getenv( 'AGRIXPERT_SUPABASE_SERVICE_KEY' );
		if ( ! $url || ! $key ) {
			WP_CLI::error( 'Pass --url and --key, or set AGRIXPERT_SUPABASE_URL / AGRIXPERT_SUPABASE_SERVICE_KEY.' );
		}
		$url = rtrim( $url, '/' );

		$execute          = isset( $assoc_args['execute'] );
		$overwrite        = isset( $assoc_args['overwrite'] );
		$include_content  = isset( $assoc_args['include-content'] );
		$only             = isset( $assoc_args['only'] ) ? array_map( 'trim', explode( ',', $assoc_args['only'] ) ) : null;

		if ( ! $execute ) {
			WP_CLI::log( WP_CLI::colorize( '%YDRY RUN%n — nothing will be written. Pass --execute to actually migrate.' ) );
		}

		$dropped_refs = array();
		$totals       = array();

		foreach ( self::table_defs() as $short_name => $def ) {
			if ( $only && ! in_array( $short_name, $only, true ) ) {
				continue;
			}
			if ( ! empty( $def['is_content'] ) && ! $include_content ) {
				WP_CLI::log( "- {$short_name}: skipped (content table — pass --include-content to migrate it anyway)." );
				continue;
			}

			$rows = self::fetch_all( $url, $key, $short_name );
			if ( is_wp_error( $rows ) ) {
				WP_CLI::warning( "- {$short_name}: fetch failed — " . $rows->get_error_message() );
				continue;
			}

			$inserted = 0;
			$skipped  = 0;
			foreach ( $rows as $row ) {
				$row = self::transform_row( $short_name, $def, $row, $dropped_refs );
				$result = self::upsert_row( $short_name, $def, $row, $execute, $overwrite );
				if ( $result === 'inserted' ) {
					++$inserted;
				} else {
					++$skipped;
				}
			}

			$totals[ $short_name ] = array( 'fetched' => count( $rows ), 'inserted' => $inserted, 'skipped_existing' => $skipped );
			WP_CLI::log( sprintf( '- %s: %d fetched, %d %s, %d already present (skipped)', $short_name, count( $rows ), $inserted, $execute ? 'inserted' : 'would insert', $skipped ) );
		}

		if ( $dropped_refs ) {
			WP_CLI::log( '' );
			WP_CLI::log( WP_CLI::colorize( '%YAuth-user references dropped (migrated as NULL) — review if any of these matter to you:%n' ) );
			foreach ( $dropped_refs as $ref ) {
				WP_CLI::log( sprintf( '  %s.%s (row %s): Supabase auth uid %s', $ref['table'], $ref['column'], $ref['row_id'], $ref['value'] ) );
			}
		}

		WP_CLI::success( $execute ? 'Migration complete.' : 'Dry run complete — re-run with --execute to write.' );
	}

	// ---- Supabase REST fetch (PostgREST), paginated ------------------------

	private static function fetch_all( $url, $key, $table ) {
		$all     = array();
		$page    = 0;
		$size    = 1000;
		do {
			$from = $page * $size;
			$to   = $from + $size - 1;
			$response = wp_remote_get( "{$url}/rest/v1/{$table}?select=*", array(
				'headers' => array(
					'apikey'        => $key,
					'Authorization' => 'Bearer ' . $key,
					'Range-Unit'    => 'items',
					'Range'         => "{$from}-{$to}",
				),
				'timeout' => 30,
			) );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( $code >= 400 ) {
				return new WP_Error( 'agx_migration_http_error', "HTTP {$code}: " . wp_remote_retrieve_body( $response ) );
			}
			$batch = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! is_array( $batch ) ) {
				return new WP_Error( 'agx_migration_bad_response', 'Unexpected response body for ' . $table );
			}
			$all = array_merge( $all, $batch );
			$page++;
		} while ( count( $batch ) === $size ); // another full page means there may be more

		return $all;
	}

	// ---- Row transform -------------------------------------------------------

	private static function transform_row( $short_name, array $def, array $row, array &$dropped_refs ) {
		// Column rename (currently only site_content.key -> content_key).
		foreach ( $def['rename'] ?? array() as $from => $to ) {
			if ( array_key_exists( $from, $row ) ) {
				$row[ $to ] = $row[ $from ];
				unset( $row[ $from ] );
			}
		}

		// Arrays/jsonb -> JSON string, matching class-schema.php's TEXT-JSON
		// columns. A genuinely null jsonb value (e.g. activity_log's
		// nullable before_state/after_state) stays null rather than being
		// forced into an empty-array placeholder.
		foreach ( $def['json'] ?? array() as $col ) {
			if ( array_key_exists( $col, $row ) && $row[ $col ] !== null ) {
				$row[ $col ] = wp_json_encode( $row[ $col ] );
			}
		}

		// Postgres boolean -> MySQL TINYINT(1).
		foreach ( $def['bool_to_int'] ?? array() as $col ) {
			if ( array_key_exists( $col, $row ) ) {
				$row[ $col ] = $row[ $col ] ? 1 : 0;
			}
		}

		// auth.users references: null out, log the original value.
		foreach ( $def['null_and_log'] ?? array() as $col => $marker ) {
			if ( ! empty( $row[ $col ] ) ) {
				$dropped_refs[] = array(
					'table'  => $short_name,
					'column' => $col,
					'row_id' => $row['id'] ?? ( $row['entity_id'] ?? '(no id column)' ),
					'value'  => $row[ $col ],
				);
			}
			$row[ $col ] = null;
		}

		// Postgres timestamptz ISO-8601 -> MySQL DATETIME ('Y-m-d H:i:s'), UTC.
		foreach ( $row as $col => $val ) {
			if ( is_string( $val ) && preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $val ) ) {
				$ts = strtotime( $val );
				if ( $ts !== false ) {
					$row[ $col ] = gmdate( 'Y-m-d H:i:s', $ts );
				}
			}
		}

		return $row;
	}

	// ---- Upsert (insert-if-absent) -------------------------------------------

	private static function upsert_row( $short_name, array $def, array $row, $execute, $overwrite ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( $short_name );
		$pk    = $def['pk'];

		$where = array();
		foreach ( $pk as $col ) {
			$where[ $col ] = $row[ $col ] ?? null;
		}
		$exists_sql    = 'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . implode( ' AND ', array_map( fn( $c ) => "{$c} = %s", array_keys( $where ) ) );
		$already_there = (int) $wpdb->get_var( $wpdb->prepare( $exists_sql, array_values( $where ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( $already_there && ! $overwrite ) {
			return 'skipped';
		}
		if ( ! $execute ) {
			return 'inserted'; // dry run: report what would happen
		}

		if ( $already_there && $overwrite ) {
			$wpdb->update( $table, $row, $where );
		} else {
			$wpdb->insert( $table, $row );
		}
		return 'inserted';
	}
}

WP_CLI::add_command( 'agrixpert migrate-supabase', 'AgriXpert_Supabase_Migration_Cli' );
