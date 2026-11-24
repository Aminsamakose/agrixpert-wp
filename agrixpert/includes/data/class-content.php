<?php
/**
 * Editable site copy/images (site_content), structured page content
 * (page_content, JSON per value-chain/solution slug), and the Insights
 * CMS — the data layer behind the live admin dashboard's content-
 * management tabs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Content {

	// ---- site_content: flat editable key/value copy -----------------------

	public static function get_all_site_content() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . AgriXpert_Schema::table( 'site_content' ) . ' ORDER BY group_name, sort_order' );
	}

	public static function get_value( $key, $default = '' ) {
		global $wpdb;
		$value = $wpdb->get_var( $wpdb->prepare( 'SELECT value FROM ' . AgriXpert_Schema::table( 'site_content' ) . ' WHERE content_key = %s', $key ) );
		return $value !== null ? $value : $default;
	}

	public static function set_value( $key, $value ) {
		global $wpdb;
		$wpdb->update( AgriXpert_Schema::table( 'site_content' ), array( 'value' => $value, 'updated_at' => current_time( 'mysql' ) ), array( 'content_key' => $key ) );
		return true;
	}

	public static function seed_defaults( array $defaults ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'site_content' );
		foreach ( $defaults as $key => $d ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT content_key FROM {$table} WHERE content_key = %s", $key ) );
			if ( ! $exists ) {
				$wpdb->insert( $table, array(
					'content_key' => $key,
					'group_name'  => $d['group'],
					'label'       => $d['label'],
					'type'        => $d['type'] ?? 'text',
					'value'       => $d['value'] ?? '',
					'sort_order'  => $d['sort_order'] ?? 0,
				) );
			}
		}
	}

	// ---- page_content: JSON blob per (entity_type, slug) -------------------

	public static function get_page_content( $entity_type, $slug ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT data FROM ' . AgriXpert_Schema::table( 'page_content' ) . ' WHERE entity_type = %s AND slug = %s', $entity_type, $slug ) );
		return $row ? json_decode( $row->data, true ) : null;
	}

	public static function set_page_content( $entity_type, $slug, array $data ) {
		global $wpdb;
		$wpdb->replace( AgriXpert_Schema::table( 'page_content' ), array(
			'entity_type' => $entity_type,
			'slug'        => $slug,
			'data'        => wp_json_encode( $data ),
		) );
		return true;
	}

	// ---- Insights (simple CMS) ---------------------------------------------

	public static function get_published_insights( $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM ' . AgriXpert_Schema::table( 'insights' ) . ' WHERE published = 1 ORDER BY published_at DESC LIMIT %d', $limit
		) );
	}

	public static function get_all_insights() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . AgriXpert_Schema::table( 'insights' ) . ' ORDER BY created_at DESC' );
	}

	public static function get_insight( $id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AgriXpert_Schema::table( 'insights' ) . ' WHERE id = %s', $id ) );
	}

	public static function get_insight_by_slug( $slug ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . AgriXpert_Schema::table( 'insights' ) . ' WHERE slug = %s', $slug ) );
	}

	public static function save_insight( array $v, $id = null ) {
		global $wpdb;
		$table = AgriXpert_Schema::table( 'insights' );
		$data  = array(
			'title'        => $v['title'],
			'slug'         => $v['slug'] ?: sanitize_title( $v['title'] ),
			'excerpt'      => $v['excerpt'] ?? '',
			'body'         => $v['body'] ?? '',
			'category'     => $v['category'] ?: 'News',
			'image_url'    => $v['image_url'] ?? '',
			'author'       => $v['author'] ?: 'AgriXpert',
			'published'    => ! empty( $v['published'] ) ? 1 : 0,
			'published_at' => ! empty( $v['published'] ) ? current_time( 'mysql' ) : null,
			'updated_at'   => current_time( 'mysql' ),
		);
		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		}
		$id = wp_generate_uuid4();
		$data['id'] = $id;
		$wpdb->insert( $table, $data );
		return $id;
	}

	public static function delete_insight( $id ) {
		global $wpdb;
		$wpdb->delete( AgriXpert_Schema::table( 'insights' ), array( 'id' => $id ) );
		return true;
	}
}
