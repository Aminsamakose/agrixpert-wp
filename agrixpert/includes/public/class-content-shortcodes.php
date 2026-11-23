<?php
/**
 * Public-facing output for admin-editable content: [agrixpert_content
 * key="..."] drops a single editable copy field inline in any page/post,
 * and [agrixpert_insights] lists published Insights — the WordPress
 * equivalent of the live site reading site_content/insights straight
 * from Supabase on every page render.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_shortcode( 'agrixpert_content', function ( $atts ) {
	$atts = shortcode_atts( array( 'key' => '' ), $atts );
	if ( ! $atts['key'] ) {
		return '';
	}
	return wp_kses_post( AgriXpert_Content::get_value( sanitize_key( $atts['key'] ) ) );
} );

add_shortcode( 'agrixpert_insights', function ( $atts ) {
	$atts = shortcode_atts( array( 'limit' => 6 ), $atts );
	$insights = AgriXpert_Content::get_published_insights( (int) $atts['limit'] );

	ob_start();
	echo '<div class="agx-insights-grid">';
	foreach ( $insights as $insight ) {
		echo '<article class="agx-insight-card">';
		if ( $insight->image_url ) {
			echo '<img src="' . esc_url( $insight->image_url ) . '" alt="" />';
		}
		echo '<span class="agx-insight-category">' . esc_html( $insight->category ) . '</span>';
		echo '<h3>' . esc_html( $insight->title ) . '</h3>';
		echo '<p>' . esc_html( $insight->excerpt ) . '</p>';
		echo '<p class="agx-insight-meta">' . esc_html( $insight->author ) . ' &middot; ' . esc_html( mysql2date( get_option( 'date_format' ), $insight->published_at ) ) . '</p>';
		echo '</article>';
	}
	if ( empty( $insights ) ) {
		echo '<p>' . esc_html__( 'No insights published yet.', 'agrixpert' ) . '</p>';
	}
	echo '</div>';
	return ob_get_clean();
} );
