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
		<div class="agx-container agx-hero-inner">
			<div class="agx-hero-copy">
				<span class="agx-eyebrow"><?php esc_html_e( 'Agricultural value-chain integration', 'agrixpert-theme' ); ?></span>
				<h1><?php esc_html_e( 'Every actor in the agricultural value chain, on one platform', 'agrixpert-theme' ); ?></h1>
				<p><?php esc_html_e( 'AgriXpert links farmers, buyers, equipment and storage suppliers, and cooperatives so commodities move efficiently from production to market, with verification, bookings and a shared marketplace in one place.', 'agrixpert-theme' ); ?></p>
				<div class="agx-cta-row">
					<a class="agx-btn agx-btn-gold" href="<?php echo esc_url( home_url( '/for-farmers' ) ); ?>"><?php esc_html_e( 'I want to produce', 'agrixpert-theme' ); ?></a>
					<a class="agx-btn agx-btn-outline" href="<?php echo esc_url( home_url( '/for-buyers' ) ); ?>"><?php esc_html_e( 'I want to buy', 'agrixpert-theme' ); ?></a>
					<a class="agx-btn agx-btn-outline" href="<?php echo esc_url( home_url( '/marketplace' ) ); ?>"><?php esc_html_e( 'Browse the marketplace', 'agrixpert-theme' ); ?></a>
				</div>
			</div>
			<div class="agx-hero-weave" role="presentation" aria-hidden="true"></div>
		</div>
	</section>

	<section class="agx-section-tight agx-partners">
		<div class="agx-container">
			<p class="agx-partners-label"><?php esc_html_e( 'Built alongside Northern Ghana\'s agricultural development partners', 'agrixpert-theme' ); ?></p>
			<ul class="agx-partners-row">
				<li><?php esc_html_e( 'GIZ', 'agrixpert-theme' ); ?></li>
				<li><?php esc_html_e( 'UNDP', 'agrixpert-theme' ); ?></li>
				<li><?php esc_html_e( 'Mastercard Foundation', 'agrixpert-theme' ); ?></li>
				<li><?php esc_html_e( 'Global Shea Alliance', 'agrixpert-theme' ); ?></li>
				<li><?php esc_html_e( 'iValley Ghana', 'agrixpert-theme' ); ?></li>
				<li><?php esc_html_e( 'Kosmos Innovation Centre', 'agrixpert-theme' ); ?></li>
			</ul>
		</div>
	</section>

	<section class="agx-section agx-section-warm">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'The problem', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( "Ghana's agricultural value chains are fragmented", 'agrixpert-theme' ); ?></h2>
			<div class="agx-ledger">
				<div class="agx-ledger-row agx-ledger-row--sorghum">
					<h3><?php esc_html_e( 'Farmers can\'t reliably find buyers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Commodities go unsold or are sold below fair value because supply and demand rarely meet directly.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-ledger-row agx-ledger-row--clay">
					<h3><?php esc_html_e( 'Buyers struggle to source at scale', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Aggregating quality-consistent volume from scattered smallholders takes time most buyers don\'t have.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-ledger-row agx-ledger-row--indigo">
					<h3><?php esc_html_e( 'Equipment and storage sit idle', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Mechanisation and warehousing providers can\'t easily reach the farmers and cooperatives who need their capacity.', 'agrixpert-theme' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="agx-section">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'How it works', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'From registration to a completed transaction', 'agrixpert-theme' ); ?></h2>
			<div class="agx-process">
				<div class="agx-process-step">
					<span class="agx-step-number">01</span>
					<h3><?php esc_html_e( 'Register', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Farmers, buyers, suppliers and cooperatives register with the details relevant to their role.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-process-step">
					<span class="agx-step-number">02</span>
					<h3><?php esc_html_e( 'Get verified', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'The AgriXpert team reviews every registration before it can transact on the platform.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-process-step">
					<span class="agx-step-number">03</span>
					<h3><?php esc_html_e( 'List or search', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Post what you have or need in the marketplace: commodity, equipment or storage.', 'agrixpert-theme' ); ?></p>
				</div>
				<div class="agx-process-step">
					<span class="agx-step-number">04</span>
					<h3><?php esc_html_e( 'Connect and transact', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Express interest, book equipment or storage, and manage it all from your own Portal.', 'agrixpert-theme' ); ?></p>
				</div>
			</div>
		</div>
	</section>

	<section class="agx-section agx-section-sage">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Value chains', 'agrixpert-theme' ); ?></span>
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
			<span class="agx-eyebrow"><?php esc_html_e( 'Who it\'s for', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'Built for every actor in the chain', 'agrixpert-theme' ); ?></h2>
			<div class="agx-grid">
				<div class="agx-role-card agx-role-card--farmer">
					<h3><?php esc_html_e( 'Farmers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'List what you grow, connect to verified buyers, and manage bookings from your Portal.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/for-farmers' ) ); ?>"><?php esc_html_e( 'Register as a farmer', 'agrixpert-theme' ); ?></a></p>
				</div>
				<div class="agx-role-card agx-role-card--buyer">
					<h3><?php esc_html_e( 'Buyers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Source consistent volume and quality from a network of verified producers.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/for-buyers' ) ); ?>"><?php esc_html_e( 'Register as a buyer', 'agrixpert-theme' ); ?></a></p>
				</div>
				<div class="agx-role-card agx-role-card--supplier">
					<h3><?php esc_html_e( 'Suppliers', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Offer mechanisation, storage, logistics or input-supply services to the whole network.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/marketplace' ) ); ?>"><?php esc_html_e( 'List your services', 'agrixpert-theme' ); ?></a></p>
				</div>
				<div class="agx-role-card agx-role-card--cooperative">
					<h3><?php esc_html_e( 'Cooperatives', 'agrixpert-theme' ); ?></h3>
					<p><?php esc_html_e( 'Aggregate your members\' production and manage the group from one account.', 'agrixpert-theme' ); ?></p>
					<p><a href="<?php echo esc_url( home_url( '/contact' ) ); ?>"><?php esc_html_e( 'Get in touch', 'agrixpert-theme' ); ?></a></p>
				</div>
			</div>
		</div>
	</section>

	<section class="agx-section agx-section-dark">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Built for scale', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'One platform, every part of the chain', 'agrixpert-theme' ); ?></h2>
			<div class="agx-stats-band">
				<div class="agx-stat">
					<span class="agx-stat-number">5</span>
					<span class="agx-stat-label"><?php esc_html_e( 'Value chains supported', 'agrixpert-theme' ); ?></span>
				</div>
				<div class="agx-stat">
					<span class="agx-stat-number">6</span>
					<span class="agx-stat-label"><?php esc_html_e( 'Platform solutions', 'agrixpert-theme' ); ?></span>
				</div>
				<div class="agx-stat">
					<span class="agx-stat-number">4</span>
					<span class="agx-stat-label"><?php esc_html_e( 'Actor types connected', 'agrixpert-theme' ); ?></span>
				</div>
				<div class="agx-stat">
					<span class="agx-stat-number">1</span>
					<span class="agx-stat-label"><?php esc_html_e( 'Shared marketplace', 'agrixpert-theme' ); ?></span>
				</div>
			</div>
		</div>
	</section>

	<section class="agx-section">
		<div class="agx-container">
			<span class="agx-eyebrow"><?php esc_html_e( 'Common questions', 'agrixpert-theme' ); ?></span>
			<h2><?php esc_html_e( 'Before you register', 'agrixpert-theme' ); ?></h2>
			<div class="agx-faq">
				<details class="agx-faq-item">
					<summary><?php esc_html_e( 'Is it free to join AgriXpert?', 'agrixpert-theme' ); ?></summary>
					<p><?php esc_html_e( 'Yes. Access to the platform is currently free. Fees for specific facilitated services, such as mechanisation or aggregation, are agreed with you separately before that service is provided.', 'agrixpert-theme' ); ?></p>
				</details>
				<details class="agx-faq-item">
					<summary><?php esc_html_e( 'How do I know the people I\'m dealing with are genuine?', 'agrixpert-theme' ); ?></summary>
					<p><?php esc_html_e( 'The AgriXpert team reviews every registration before it is verified and able to transact on the platform, whether that\'s a farmer, buyer, supplier or cooperative.', 'agrixpert-theme' ); ?></p>
				</details>
				<details class="agx-faq-item">
					<summary><?php esc_html_e( 'Does AgriXpert handle payment between buyers and sellers?', 'agrixpert-theme' ); ?></summary>
					<p><?php esc_html_e( 'No. AgriXpert connects you and lets you express interest or book equipment and storage directly. Payment is arranged between the parties themselves, outside the platform.', 'agrixpert-theme' ); ?></p>
				</details>
				<details class="agx-faq-item">
					<summary><?php esc_html_e( 'What happens after I register?', 'agrixpert-theme' ); ?></summary>
					<p><?php esc_html_e( 'Your registration is reviewed for verification. Once verified, you can list or search the Marketplace and manage everything from your own Portal.', 'agrixpert-theme' ); ?></p>
				</details>
			</div>
		</div>
	</section>

	<section class="agx-section agx-section-sage" style="text-align:center;">
		<div class="agx-container">
			<h2><?php esc_html_e( 'Already registered?', 'agrixpert-theme' ); ?></h2>
			<p style="margin-left:auto;margin-right:auto;"><?php esc_html_e( 'Sign in to your Portal to check your verification status, manage your listings and bookings, and update your details.', 'agrixpert-theme' ); ?></p>
			<a class="agx-btn agx-btn-primary" href="<?php echo esc_url( home_url( '/portal' ) ); ?>"><?php esc_html_e( 'Go to My Portal', 'agrixpert-theme' ); ?></a>
		</div>
	</section>

</main>

<?php
get_footer();
