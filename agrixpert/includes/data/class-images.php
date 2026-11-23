<?php
/**
 * Lets an admin replace any of the bundled Unsplash placeholder images
 * (data/stock-images.json) with AgriXpert's own photography, through the
 * WordPress media library, without touching code or a database migration.
 *
 * Overrides are a single wp_options row (small, fixed set of ~18 named
 * image slots — a full table would be overkill here, unlike site_content
 * or page_content, which are open-ended). Every call site that would
 * otherwise read data/stock-images.json directly reads through
 * AgriXpert_Page_Library::stock_image(), which defers to this class, so
 * replacing one image here updates it everywhere that slot is used (a
 * value-chain hero, a solution hero, the grid thumbnails, etc.) with no
 * other code changes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Images {

	const OPTION = 'agrixpert_image_overrides';

	/**
	 * All known image slots and their current image (override if set,
	 * else the bundled default from data/stock-images.json), for the
	 * admin screen to list.
	 */
	public static function all_slots() {
		$defaults  = self::defaults();
		$overrides = self::overrides();
		$slots     = array();
		foreach ( $defaults as $key => $default ) {
			$slots[ $key ] = array(
				'src'          => $overrides[ $key ]['src'] ?? $default['src'],
				'alt'          => $overrides[ $key ]['alt'] ?? $default['alt'],
				'is_override'  => isset( $overrides[ $key ] ),
				'default_src'  => $default['src'],
			);
		}
		return $slots;
	}

	/** The image for one slot, override-if-set else default — what every front-end renderer actually calls. */
	public static function get( $key ) {
		$overrides = self::overrides();
		if ( isset( $overrides[ $key ] ) ) {
			return $overrides[ $key ];
		}
		$defaults = self::defaults();
		return $defaults[ $key ] ?? null;
	}

	public static function set_override( $key, $src, $alt ) {
		if ( ! isset( self::defaults()[ $key ] ) ) {
			return false;
		}
		$overrides         = self::overrides();
		$overrides[ $key ] = array( 'src' => $src, 'alt' => $alt );
		update_option( self::OPTION, $overrides );
		return true;
	}

	public static function clear_override( $key ) {
		$overrides = self::overrides();
		unset( $overrides[ $key ] );
		update_option( self::OPTION, $overrides );
	}

	private static function overrides() {
		$stored = get_option( self::OPTION, array() );
		return is_array( $stored ) ? $stored : array();
	}

	private static function defaults() {
		static $cache = null;
		if ( $cache !== null ) {
			return $cache;
		}
		$path   = AGRIXPERT_PLUGIN_DIR . 'data/stock-images.json';
		$data   = file_exists( $path ) ? json_decode( file_get_contents( $path ), true ) : array(); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents -- bundled plugin asset, not user input
		$cache  = is_array( $data ) ? $data : array();
		return $cache;
	}
}
