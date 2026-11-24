<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
	<footer class="agx-site-footer">
		<div class="agx-container">
			<div class="agx-footer-grid">
				<div>
					<h4><?php bloginfo( 'name' ); ?></h4>
					<p style="color: var(--agx-sage); font-size: 0.85rem; margin: 0;">
						<?php esc_html_e( 'Connecting farmers, buyers, suppliers and cooperatives across the agricultural value chain.', 'agrixpert-theme' ); ?>
					</p>
				</div>
				<div>
					<h4><?php esc_html_e( 'Platform', 'agrixpert-theme' ); ?></h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/value-chains' ) ); ?>"><?php esc_html_e( 'Value Chains', 'agrixpert-theme' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/solutions' ) ); ?>"><?php esc_html_e( 'Solutions', 'agrixpert-theme' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/marketplace' ) ); ?>"><?php esc_html_e( 'Marketplace', 'agrixpert-theme' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/insights' ) ); ?>"><?php esc_html_e( 'Insights', 'agrixpert-theme' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/portal' ) ); ?>"><?php esc_html_e( 'Portal', 'agrixpert-theme' ); ?></a></li>
					</ul>
				</div>
				<div>
					<h4><?php esc_html_e( 'Get Involved', 'agrixpert-theme' ); ?></h4>
					<ul>
						<li><a href="<?php echo esc_url( home_url( '/for-farmers' ) ); ?>"><?php esc_html_e( 'For Farmers', 'agrixpert-theme' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/for-buyers' ) ); ?>"><?php esc_html_e( 'For Buyers', 'agrixpert-theme' ); ?></a></li>
						<li><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>"><?php esc_html_e( 'Contact', 'agrixpert-theme' ); ?></a></li>
					</ul>
				</div>
				<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
					<div><?php dynamic_sidebar( 'footer-1' ); ?></div>
				<?php endif; ?>
			</div>
			<div class="agx-footer-bottom">
				<span>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?>. <?php esc_html_e( 'All rights reserved.', 'agrixpert-theme' ); ?></span>
				<span><?php esc_html_e( 'A Samakose Accelerator Lab platform.', 'agrixpert-theme' ); ?></span>
			</div>
		</div>
	</footer>

<?php wp_footer(); ?>
</body>
</html>
