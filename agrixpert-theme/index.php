<?php
/**
 * Fallback template (archives, search results, anything without a more
 * specific template). AgriXpert has no blog in its own right, so this
 * stays minimal rather than building out a full post-archive design.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main class="agx-page-content">
	<?php if ( have_posts() ) : ?>
		<?php while ( have_posts() ) : the_post(); ?>
			<article <?php post_class(); ?>>
				<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
				<?php the_excerpt(); ?>
			</article>
		<?php endwhile; ?>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing found.', 'agrixpert-theme' ); ?></p>
	<?php endif; ?>
</main>

<?php
get_footer();
