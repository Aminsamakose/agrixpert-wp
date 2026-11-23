<?php
/**
 * Loads the real, migrated marketing copy for value chains and solutions
 * — ported from the live Next.js app's src/lib/value-chain-content.ts and
 * src/lib/solutions-content.ts (and the stock-photo URLs in site-data.ts)
 * — and applies any admin override stored in the `page_content` table
 * (see AgriXpert_Content::get_page_content()).
 *
 * This mirrors the live app's own fallback architecture exactly:
 * getPageContentOverride() there returns null until an admin edits a
 * page, and the page renders its hardcoded default content until then.
 * The JSON files bundled here ARE that hardcoded default content, not
 * placeholders — they are the actual copy migrated from the live site
 * (see docs/CONTENT-MIGRATION.md for how and when this was done).
 *
 * The `page_content` table itself existed in the schema since Phase 1 but,
 * until this class, nothing in the plugin ever read from it — a second
 * instance of the same "table with no code path" gap the Phase 9.5 Portal
 * review found with cooperative_members/listing_reviews. This closes it
 * for value_chain/solution content the same way the Portal closed it for
 * self-service actor data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Page_Library {

	const VALUE_CHAIN = 'value_chain';
	const SOLUTION    = 'solution';

	private static $cache = array();

	private static function load_json( $filename ) {
		if ( isset( self::$cache[ $filename ] ) ) {
			return self::$cache[ $filename ];
		}
		$path = AGRIXPERT_PLUGIN_DIR . 'data/' . $filename;
		if ( ! file_exists( $path ) ) {
			return array();
		}
		$data = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- bundled plugin asset, not user input
		self::$cache[ $filename ] = is_array( $data ) ? $data : array();
		return self::$cache[ $filename ];
	}

	public static function stock_image( $key ) {
		$images = self::load_json( 'stock-images.json' );
		return $images[ $key ] ?? null;
	}

	/**
	 * All entries of a type, keyed by slug, each with any admin override
	 * from page_content already merged over the bundled default — same
	 * precedence as the live app's getAllPageContentOverrides() + fallback.
	 */
	public static function all( $entity_type ) {
		$defaults = self::load_json( self::filename_for( $entity_type ) );
		$out      = array();
		foreach ( $defaults as $slug => $default ) {
			$out[ $slug ] = self::merge_override( $entity_type, $slug, $default );
		}
		return $out;
	}

	public static function get( $entity_type, $slug ) {
		$defaults = self::load_json( self::filename_for( $entity_type ) );
		if ( ! isset( $defaults[ $slug ] ) ) {
			return null;
		}
		return self::merge_override( $entity_type, $slug, $defaults[ $slug ] );
	}

	private static function merge_override( $entity_type, $slug, $default ) {
		if ( ! class_exists( 'AgriXpert_Content' ) ) {
			return $default;
		}
		$override = AgriXpert_Content::get_page_content( $entity_type, $slug );
		// A shallow merge, matching the live app's `override ?? fallback`
		// behaviour at the field level isn't attempted there either — an
		// admin override is expected to be a complete replacement object,
		// not a partial patch, so a top-level array union is sufficient
		// and avoids guessing how to merge nested arrays of paragraphs.
		return is_array( $override ) ? array_merge( $default, $override ) : $default;
	}

	private static function filename_for( $entity_type ) {
		return $entity_type === self::VALUE_CHAIN ? 'value-chains.json' : 'solutions.json';
	}
}
