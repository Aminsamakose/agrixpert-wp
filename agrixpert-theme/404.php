<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main class="agx-page-content" style="text-align:center;">
	<h1><?php esc_html_e( "Page Not Found", 'agrixpert-theme' ); ?></h1>
	<p><?php esc_html_e( "The page you're looking for doesn't exist or has moved.", 'agrixpert-theme' ); ?></p>
	<a class="agx-btn agx-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Back to Home', 'agrixpert-theme' ); ?></a>
</main>

<?php
get_footer();
