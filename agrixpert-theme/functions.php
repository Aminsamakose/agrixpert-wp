<?php
/**
 * AgriXpert theme bootstrap.
 *
 * This theme is deliberately thin: all of AgriXpert's data, forms,
 * marketplace, portal and auth logic lives in the AgriXpert plugin (see
 * ../agrixpert/). This file only sets up the visual shell — fonts, nav
 * menu, widget areas, and a little markup glue around the plugin's
 * shortcode output so it sits inside a consistent header/footer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AGRIXPERT_THEME_VERSION', '1.0.0' );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'custom-logo', array(
		'height'      => 72,
		'width'       => 240,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'agrixpert-theme' ),
		'footer'  => __( 'Footer Menu', 'agrixpert-theme' ),
	) );
} );

add_action( 'wp_enqueue_scripts', function () {
	wp_enqueue_style(
		'agrixpert-theme-fonts',
		'https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,400..600;1,9..144,400..600&family=Work+Sans:wght@400;500;600;700&display=swap',
		array(),
		null
	);
	wp_enqueue_style( 'agrixpert-theme-style', get_stylesheet_uri(), array(), AGRIXPERT_THEME_VERSION );
	wp_enqueue_script( 'agrixpert-theme-nav', get_template_directory_uri() . '/assets/js/nav.js', array(), AGRIXPERT_THEME_VERSION, true );
} );

add_action( 'widgets_init', function () {
	register_sidebar( array(
		'name'          => __( 'Footer Column', 'agrixpert-theme' ),
		'id'            => 'footer-1',
		'before_widget' => '<div class="agx-footer-widget">',
		'after_widget'  => '</div>',
		'before_title'  => '<h4>',
		'after_title'   => '</h4>',
	) );
} );

/**
 * Pages built mainly to host a plugin shortcode (Marketplace, Portal,
 * value-chain/solution library pages, registration/contact forms) read
 * better full-width, without the front page's hero/section chrome. This
 * wraps their content in the same `.agx-page-content` container
 * page.php already uses — nothing WordPress-specific to configure per
 * page, it just needs *a* page template, which page.php already is.
 */
function agrixpert_theme_is_plugin_shortcode_page( $post_id ) {
	$content = get_post_field( 'post_content', $post_id );
	if ( ! $content ) {
		return false;
	}
	foreach ( array( 'agrixpert_portal', 'agrixpert_marketplace', 'agrixpert_value_chain', 'agrixpert_solution' ) as $tag ) {
		if ( has_shortcode( $content, $tag ) ) {
			return true;
		}
	}
	return false;
}

add_filter( 'body_class', function ( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'agx-home';
	}
	if ( is_page() && agrixpert_theme_is_plugin_shortcode_page( get_the_ID() ) ) {
		$classes[] = 'agx-app-page';
	}
	return $classes;
} );
