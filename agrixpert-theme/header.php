<?php
/**
 * Site header — logo/title, primary nav, and a "Join AgriXpert" CTA that
 * always points at the Portal (where a visitor signs in or registers).
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<header class="agx-site-header">
	<div class="agx-header-inner">
		<a class="agx-site-branding" href="<?php echo esc_url( home_url( '/' ) ); ?>">
			<?php if ( has_custom_logo() ) : ?>
				<?php the_custom_logo(); ?>
			<?php else : ?>
				<span class="agx-site-title"><?php bloginfo( 'name' ); ?></span>
			<?php endif; ?>
		</a>

		<button class="agx-menu-toggle" aria-expanded="false" aria-controls="agx-primary-nav">&#9776; <?php esc_html_e( 'Menu', 'agrixpert-theme' ); ?></button>

		<nav class="agx-primary-nav" id="agx-primary-nav" aria-label="<?php esc_attr_e( 'Primary', 'agrixpert-theme' ); ?>">
			<?php
			if ( has_nav_menu( 'primary' ) ) {
				wp_nav_menu( array(
					'theme_location' => 'primary',
					'container'      => false,
					'items_wrap'     => '<ul>%3$s</ul>',
				) );
			} else {
				// Sensible default so the site is navigable before anyone
				// has built a menu in Appearance → Menus.
				echo '<ul>';
				echo '<li><a href="' . esc_url( home_url( '/value-chains' ) ) . '">' . esc_html__( 'Value Chains', 'agrixpert-theme' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/solutions' ) ) . '">' . esc_html__( 'Solutions', 'agrixpert-theme' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/marketplace' ) ) . '">' . esc_html__( 'Marketplace', 'agrixpert-theme' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/for-farmers' ) ) . '">' . esc_html__( 'For Farmers', 'agrixpert-theme' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/for-buyers' ) ) . '">' . esc_html__( 'For Buyers', 'agrixpert-theme' ) . '</a></li>';
				echo '<li><a href="' . esc_url( home_url( '/contact' ) ) . '">' . esc_html__( 'Contact', 'agrixpert-theme' ) . '</a></li>';
				echo '<li><a class="agx-nav-cta" href="' . esc_url( home_url( '/portal' ) ) . '">' . esc_html__( 'Portal', 'agrixpert-theme' ) . '</a></li>';
				echo '</ul>';
			}
			?>
		</nav>
	</div>
</header>
