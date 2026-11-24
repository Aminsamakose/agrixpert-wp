<?php
/**
 * The "whole system" one-page home layout: hero -> problem -> how it
 * works -> value chains -> solutions -> audiences -> impact -> portal
 * CTA. Structure and section order follow the System design canvas
 * (System.dc.html) built earlier this session — static/WP-buildable by
 * design, rather than the live app's React single-page scroll.
 *
 * Value chains and solutions are pulled live from the plugin's own data
 * ([agrixpert_value_chains] / [agrixpert_solutions]) rather than
 * duplicated here, so editing agrixpert/data/value-chains.json or
 * solutions.json (or a future admin screen over the same data) updates
 * the home page automatically.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();
?>

<main>

	<section class="agx-hero">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Agricultural Value-Chain Integration', 'agrixpert-theme' ); ?></span>
			<h1><?php esc_html_e( 'One platform connecting every actor in the agricultural value chain', 'agrixpert-theme' ); ?></h1>
			<p><?php esc_html_e( 'AgriXpert links farmers, buyers, equipment and storage suppliers, and cooperatives so commodities move efficiently from production to market — with verification, bookings and a shared marketplace in one place.', 'agrixpert-theme' ); ?></p>
			<div class="agx-cta-row">
				<a class="agx-btn agx-btn-gold" href="<?php echo esc_url( home_url( '/for-farmers' ) ); ?>"><?php esc_html_e( 'I Want to Produce', 'agrixpert-theme' ); ?></a>
				<a class="agx-btn agx-btn-outline" style="border-color:#fff;color:#fff;" href="<?php echo esc_url( home_url( '/for-buyers' ) ); ?>"><?php esc_html_e( 'I Want to Buy', 'agrixpert-theme' ); ?></a>
				<a class="agx-btn agx-btn-outline" style="border-color:#fff;color:#fff;" href="<?php echo esc_url( home_url( '/marketplace' ) ); ?>"><?php esc_html_e( 'Browse the Marketplace', 'agrixpert-theme' ); ?></a>
			</div>
		</div>
	</section>

	<section class="agx-section agx-section-warm">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'The Problem', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( "Ghana's agricultural value chains are fragmented", 'agrixpert-theme' ); ?></h2>
			<div class="agx-grid">
				<div class="agx-card">
					<h3><?php esc_html_e( 'Farmers can\'t reliably find buyers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Commodities go unsold or are sold below fair value because supply and demand rarely meet directly.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-card">
					<h3><?php esc_html_e( 'Buyers struggle to source at scale', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Aggregating quality-consistent volume from scattered smallholders takes time most buyers don\'t have.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-card">
					<h3><?php esc_html_e( 'Equipment and storage sit idle', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Mechanisation and warehousing providers can\'t easily reach the farmers and cooperatives who need their capacity.', 'agrixpert-theme' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="agx-section">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'How It Works', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'From registration to a completed transaction', 'agrixpert-theme' ); ?></h2>
			<div class="agx-grid">
				<div class="agx-card">
					<div class="agx-step-number">01</div>
					<h3><?php esc_html_e( 'Register', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Farmers, buyers, suppliers and cooperatives register with the details relevant to their role.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-card">
					<div class="agx-step-number">02</div>
					<h3><?php esc_html_e( 'Get Verified', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'The AgriXpert team reviews every registration before it can transact on the platform.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-card">
					<div class="agx-step-number">03</div>
					<h3><?php esc_html_e( 'List or Search', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Post what you have or need in the Marketplace — commodity, equipment or storage.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-card">
					<div class="agx-step-number">04</div>
					<h3><?php esc_html_e( 'Connect and Transact', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Express interest, book equipment or storage, and manage it all from your own Portal.', 'agrixpert-theme' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="agx-section agx-section-sage">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Value Chains', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'Commodities AgriXpert supports', 'agrixpert-theme' ); ?></h2>
			<?php echo do_shortcode( '[agrixpert_value_chains]' ); ?>
		</div>
	</section>

	<section class="agx-section">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Solutions', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'What AgriXpert provides', 'agrixpert-theme' ); ?></h2>
			<?php echo do_shortcode( '[agrixpert_solutions]' ); ?>
		</div>
	</section>

	<section class="agx-section agx-section-warm">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Who It\'s For', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'Built for every actor in the chain', 'agrixpert-theme' ); ?></h2>
			<div class="agx-grid">
				<div class="agx-card">
					<h3><?php esc_html_e( 'Farmers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'List what you grow, connect to verified buyers, and manage bookings from your Portal.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/for-farmers' ) ); ?>"><?php esc_html_e( 'Register as a Farmer →', 'agrixpert-theme' ); ?></a></p>
				</div>
				<div class="agx-card">
					<h3><?php esc_html_e( 'Buyers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Source consistent volume and quality from a network of verified producers.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/for-buyers' ) ); ?>"><?php esc_html_e( 'Register as a Buyer →', 'agrixpert-theme' ); ?></a></p>
				</div>
				<div class="agx-card">
					<h3><?php esc_html_e( 'Suppliers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Offer mechanisation, storage, logistics or input-supply services to the whole network.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/marketplace' ) ); ?>"><?php esc_html_e( 'List Your Services →', 'agrixpert-theme' ); ?></a></p>
				</div>
				<div class="agx-card">
					<h3><?php esc_html_e( 'Cooperatives', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Aggregate your members\' production and manage the group from one account.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>"><?php esc_html_e( 'Get in Touch →', 'agrixpert-theme' ); ?></a></p>
				</div>
			</div>
		</div>
	</section>

	<section class="agx-section agx-section-dark">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Impact', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'Built for scale across Northern Ghana', 'agrixpert-theme' ); ?></h2>
			<div class="agx-stats-grid">
				<div><div class="agx-stat-number">5</div><div class="agx-stat-label"><?php esc_html_e( 'Value chains supported', 'agrixpert-theme' ); ?></div></div>
				<div><div class="agx-stat-number">6</div><div class="agx-stat-label"><?php esc_html_e( 'Platform solutions', 'agrixpert-theme' ); ?></div></div>
				<div><div class="agx-stat-number">4</div><div class="agx-stat-label"><?php esc_html_e( 'Actor types connected', 'agrixpert-theme' ); ?></div></div>
				<div><div class="agx-stat-number">1</div><div class="agx-stat-label"><?php esc_html_e( 'Shared marketplace', 'agrixpert-theme' ); ?></div></div>
			</div>
		</div>
	</section>

	<section class="agx-section agx-section-sage" style="text-align:center;">
		<div class="agx-container">
			<h2><?php esc_html_e( 'Already registered?', 'agrixpert-theme' ); ?></h2>
			<p><?php esc_html_e( 'Sign in to your Portal to check your verification status, manage your listings and bookings, and update your details.', 'agrixpert-theme' ); ?></p>
			<a class="agx-btn agx-btn-primary" href="<?php echo esc_url( home_url( '/portal' ) ); ?>"><?php esc_html_e( 'Go to My Portal', 'agrixpert-theme' ); ?></a>
		</div>
	</section>

</main>

<?php
get_footer();
