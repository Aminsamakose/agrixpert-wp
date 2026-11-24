<?php
/**
 * Default page template. Covers every WP Page created by the plugin's
 * page-seeder (For Farmers, For Buyers, Marketplace, Portal, Contact,
 * value-chain/solution library pages) as well as any ordinary page an
 * admin creates — all of them just need a title and the page's content
 * (almost always a plugin shortcode) inside the site chrome.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main class="agx-page-content">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<h1><?php the_title(); ?></h1>
		<div class="agx-page-body">
			<?php the_content(); ?>
		</div>
		<?php
	endwhile;
	?>
</main>

<?php
get_footer();
