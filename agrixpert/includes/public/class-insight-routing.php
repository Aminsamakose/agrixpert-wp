<?php
/**
 * Public single-insight view at /insights/{slug} — the WordPress
 * equivalent of the live app's src/app/insights/[slug]/page.tsx.
 *
 * Unlike value chains and solutions (class-page-seeder.php's
 * value_chain_pages()/solution_pages()), insights are not a fixed list
 * known at activation time — admins create, edit and unpublish them at
 * any time through the Insights admin screen (class-content-admin.php).
 * A static child-page-per-slug approach (seeded once, same as value
 * chains) would go stale the moment an admin added a new insight, so
 * this instead resolves /insights/{slug} dynamically on every request
 * via a rewrite rule + query var, matching the live app's own dynamic
 * [slug] route rather than WordPress's normal "page must already exist"
 * model.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Insight_Routing {

	const QUERY_VAR = 'agx_insight_slug';

	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'add_query_var' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_render_single' ) );
	}

	public static function add_rewrite_rule() {
		add_rewrite_rule(
			'^insights/([^/]+)/?$',
			'index.php?' . self::QUERY_VAR . '=$matches[1]',
			'top'
		);
	}

	public static function add_query_var( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Runs on every front-end request; does nothing unless the rewrite
	 * rule above actually matched. When it did, this renders the single
	 * insight directly — inside the theme's normal header/footer, same
	 * as any other page — and exits, bypassing WordPress's normal
	 * "is there a Page at this path" lookup entirely (there isn't one;
	 * that's the point).
	 */
	public static function maybe_render_single() {
		$slug = get_query_var( self::QUERY_VAR );
		if ( ! $slug ) {
			return;
		}

		$insight = AgriXpert_Content::get_insight_by_slug( sanitize_title( $slug ) );
		if ( ! $insight || ! $insight->published ) {
			global $wp_query;
			$wp_query->set_404();
			status_header( 404 );
			get_header();
			echo '<main class="agx-page-content"><h1>' . esc_html__( 'Insight Not Found', 'agrixpert' ) . '</h1><p>' . esc_html__( 'This insight may have been unpublished or the link is incorrect.', 'agrixpert' ) . '</p></main>';
			get_footer();
			exit;
		}

		// A real title tag/<title> for this URL, since there is no WP
		// Page object backing it for wp_title()/document_title to read.
		add_filter( 'pre_get_document_title', function () use ( $insight ) {
			return $insight->title . ' – ' . get_bloginfo( 'name' );
		} );

		get_header();
		echo '<main class="agx-page-content agx-insight-single">';
		echo '<p><a href="' . esc_url( home_url( '/insights' ) ) . '">&larr; ' . esc_html__( 'All Insights', 'agrixpert' ) . '</a></p>';
		echo '<span class="agx-insight-category">' . esc_html( $insight->category ) . '</span>';
		echo '<h1>' . esc_html( $insight->title ) . '</h1>';
		echo '<p class="agx-insight-meta">' . esc_html( $insight->author ) . ' &middot; ' . esc_html( mysql2date( get_option( 'date_format' ), $insight->published_at ) ) . '</p>';
		if ( $insight->image_url ) {
			echo '<img src="' . esc_url( $insight->image_url ) . '" alt="" />';
		}
		echo '<div class="agx-insight-body">' . wp_kses_post( wpautop( $insight->body ) ) . '</div>';
		echo '</main>';
		get_footer();
		exit;
	}
}

AgriXpert_Insight_Routing::init();
