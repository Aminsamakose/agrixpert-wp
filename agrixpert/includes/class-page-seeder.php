<?php
/**
 * Creates the real, migrated WordPress Pages this plugin needs — so
 * activating the plugin leaves a working site, not an empty shell the
 * admin has to assemble by hand from docs/CONTENT-MIGRATION.md.
 *
 * Every page here is idempotent: it is only created if no page already
 * exists at that slug (checked via get_page_by_path()), so re-running
 * this on every activation (including a plugin update) never clobbers
 * a page Amin has since edited himself. This mirrors the dbDelta()
 * convention used everywhere else in this plugin — additive, never
 * destructive, safe to re-run.
 *
 * Content is the real copy migrated from the live Next.js app's source
 * (see docs/CONTENT-MIGRATION.md for exactly where each block came
 * from), not placeholder text. The three legal pages are copied
 * verbatim from the live app, with "Phase 1 draft, needs legal review"
 * notices carried over unchanged rather than silently dropped.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Page_Seeder {

	const CONTACT_EMAIL = 'agrixpert@samakose.com';

	public static function run() {
		$top_level_ids = array();
		foreach ( self::pages() as $slug => $page ) {
			$id = self::create_if_missing( $slug, $page['title'], $page['content'], 0 );
			$top_level_ids[ $slug ] = $id ?: self::existing_id( $slug );
		}

		foreach ( self::value_chain_pages() as $slug => $page ) {
			self::create_if_missing( $slug, $page['title'], $page['content'], $top_level_ids['value-chains'] );
		}
		foreach ( self::solution_pages() as $slug => $page ) {
			self::create_if_missing( $slug, $page['title'], $page['content'], $top_level_ids['solutions'] );
		}
	}

	private static function existing_id( $slug ) {
		$page = get_page_by_path( $slug );
		return $page ? $page->ID : 0;
	}

	/**
	 * $slug is a single path segment (WordPress post_name/slug does not
	 * support slashes); hierarchy for child pages (value-chains/maize,
	 * solutions/production-coordination) is expressed via $parent_id and
	 * get_page_by_path(), not via a slash in the slug itself — building
	 * the page as 'value-chains/maize' here would have produced a page
	 * whose actual front-end URL never resolves.
	 */
	private static function create_if_missing( $slug, $title, $content, $parent_id ) {
		if ( get_page_by_path( $slug, OBJECT, 'page' ) ) {
			return 0;
		}
		return wp_insert_post( array(
			'post_title'   => $title,
			'post_name'    => $slug,
			'post_content' => $content,
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_parent'  => $parent_id,
		) );
	}

	private static function pages() {
		return array(
			'about'            => array( 'title' => 'About Us', 'content' => self::about() ),
			'what-we-do'       => array( 'title' => 'What We Do', 'content' => self::what_we_do() ),
			'solutions'        => array( 'title' => 'Solutions', 'content' => '<p class="agx-page-tagline">Our Core Business Solutions</p><p>Six coordinated functions, one agricultural value chain.</p>[agrixpert_solutions]' ),
			'value-chains'     => array( 'title' => 'Value Chains', 'content' => '<p class="agx-page-tagline">Our Priority Agricultural Value Chains</p><p>Additional value chains such as poultry and livestock can be introduced as dedicated expansion areas.</p>[agrixpert_value_chains]' ),
			'for-farmers'      => array( 'title' => 'For Farmers', 'content' => self::for_farmers() ),
			'for-buyers'       => array( 'title' => 'For Buyers', 'content' => self::for_buyers() ),
			'for-cooperatives' => array( 'title' => 'For Cooperatives', 'content' => self::for_cooperatives() ),
			'partnerships'     => array( 'title' => 'Partnerships', 'content' => self::partnerships() ),
			'contact'          => array( 'title' => 'Contact', 'content' => self::contact() ),
			'marketplace'      => array( 'title' => 'Marketplace', 'content' => '[agrixpert_marketplace]' ),
			'portal'           => array( 'title' => 'My Account', 'content' => '[agrixpert_portal]' ),
			'privacy-policy'   => array( 'title' => 'Privacy Policy', 'content' => self::privacy_policy() ),
			'cookie-policy'    => array( 'title' => 'Cookie Policy', 'content' => self::cookie_policy() ),
			'terms-of-service' => array( 'title' => 'Terms of Service', 'content' => self::terms_of_service() ),
		);
	}

	private static function value_chain_pages() {
		$pages = array();
		foreach ( array(
			'maize'      => 'Maize',
			'soybeans'   => 'Soybeans',
			'rice'       => 'Rice',
			'groundnuts' => 'Groundnuts',
			'vegetables' => 'Vegetables',
		) as $slug => $name ) {
			$pages[ $slug ] = array(
				'title'   => $name,
				'content' => '[agrixpert_value_chain slug="' . $slug . '"]',
			);
		}
		return $pages;
	}

	private static function solution_pages() {
		$pages = array();
		foreach ( array(
			'production-coordination'    => 'Production Coordination',
			'aggregation-procurement'    => 'Aggregation & Procurement',
			'mechanisation-farm-services' => 'Mechanisation & Farm Services',
			'storage-supply-chain'       => 'Storage & Supply Chain Management',
			'processing-value-addition'  => 'Processing & Value Addition',
			'market-access-trade'       => 'Market Access & Commercial Trade',
		) as $slug => $name ) {
			$pages[ $slug ] = array(
				'title'   => $name,
				'content' => '[agrixpert_solution slug="' . $slug . '"]',
			);
		}
		return $pages;
	}

	// ---- long-form pages, real copy migrated from the live app --------

	private static function about() {
		return '<p>We work to address the structural challenges that limit agricultural productivity, market access and commercial growth by coordinating the resources, services, infrastructure and partnerships required to make agricultural value chains more efficient and commercially viable.</p>'
		. '<p>Through production coordination, aggregation and procurement, mechanisation, storage and logistics, processing, and market access, AgriXpert seeks to create stronger connections between agricultural production and commercial demand.</p>'
		. '<p>Our ambition is to contribute to a more organised, productive, inclusive and commercially sustainable agricultural sector in Ghana and across Africa.</p>'
		. '<blockquote>We believe agriculture becomes more profitable when the entire value chain works together.</blockquote>'
		. '<h2 id="story">Agriculture Has Potential. The Challenge Is Connecting the Value Chain.</h2>'
		. '<p>Agriculture remains one of Africa&rsquo;s most important economic sectors, supporting millions of livelihoods, providing raw materials for industries and creating opportunities for enterprise development, investment and employment.</p>'
		. '<p>Yet, significant opportunities remain untapped.</p>'
		. '<p>Across agricultural communities, farmers often produce without reliable access to structured markets. Buyers and processors struggle to secure consistent supplies of quality commodities. Equipment owners have difficulty reaching farmers who need mechanisation services. Storage and transportation gaps contribute to post-harvest losses, while limited processing capacity restricts opportunities to create additional value from agricultural commodities.</p>'
		. '<p>These challenges are interconnected.</p>'
		. '<p>A farmer may have the capacity to produce, but without reliable buyers, the commercial opportunity remains uncertain. A processor may have the capacity to manufacture, but without dependable raw material supplies, production becomes difficult to sustain. Similarly, an investor may recognise an attractive agricultural opportunity but struggle to identify the right producers, infrastructure and commercial partners.</p>'
		. '<p>The problem is therefore not simply agricultural production. It is the lack of effective coordination across the entire agricultural value chain.</p>'
		. '<h3>Why AgriXpert Exists</h3>'
		. '<p>AgriXpert was conceived to help close this gap.</p>'
		. '<p>We recognise that sustainable agricultural growth requires more than individual interventions. It requires a coordinated approach that connects production to aggregation, aggregation to processing, and processing to markets.</p>'
		. '<p>Our model is built around the belief that farmers should not operate in isolation, buyers should not struggle unnecessarily to find reliable suppliers, and agricultural businesses should have better access to the services and partnerships required to grow.</p>'
		. '<p>We bring these different actors together through an integrated, market-led approach. Rather than looking at agriculture as disconnected activities, we see it as an interconnected commercial ecosystem in which every participant has a role to play.</p>'
		. '<h3>Where We Operate</h3>'
		. '<p>AgriXpert is rooted in Ghana, with an ambition to develop commercially viable agricultural value chains across the country and progressively expand into other African markets.</p>'
		. '<p>Our geographical focus recognises the significant agricultural opportunities within Northern Ghana, particularly across the Northern, Savannah, North East, Upper East and Upper West regions. These areas offer opportunities across several strategic agricultural commodities, including Maize, Soybeans, Rice, Groundnuts and Vegetables.</p>'
		. '<p>We see opportunities to connect farming communities, agricultural service providers, commodity aggregators, processors and commercial buyers through coordinated sourcing, production and market development arrangements. Our longer-term ambition is to establish a scalable agricultural value chain integration model that can be replicated across other regions of Ghana and selected African markets.</p>'
		. '<h3>What Makes AgriXpert Different?</h3>'
		. '<p>We approach agriculture from a value-chain integration perspective. Our focus is not limited to helping farmers produce more. We are equally concerned with what happens before production, during production and after harvesting. Our approach brings together six interconnected business solutions:</p>'
		. '<ul>'
		. '<li><strong>Production Coordination</strong> &ndash; Organising farmers and aligning production with market demand.</li>'
		. '<li><strong>Aggregation &amp; Procurement</strong> &ndash; Connecting fragmented agricultural supply to commercial buyers.</li>'
		. '<li><strong>Mechanisation &amp; Farm Services</strong> &ndash; Facilitating access to equipment and essential agricultural services.</li>'
		. '<li><strong>Storage &amp; Supply Chain Management</strong> &ndash; Coordinating commodity handling, storage and transportation.</li>'
		. '<li><strong>Processing &amp; Value Addition</strong> &ndash; Connecting agricultural commodities to processing infrastructure and higher-value markets.</li>'
		. '<li><strong>Market Access &amp; Commercial Trade</strong> &ndash; Developing buyer relationships, offtake opportunities and commercial supply arrangements.</li>'
		. '</ul>'
		. '<p>Together, these solutions provide a foundation for building stronger agricultural businesses and more reliable supply chains.</p>'
		. '<h3>Our Belief</h3>'
		. '<p>We believe the future of African agriculture depends on moving beyond fragmented production towards organised, market-oriented and commercially connected agricultural systems.</p>'
		. '<p>We believe farmers deserve better market opportunities, businesses need dependable supply chains, investors need commercially viable opportunities, and agricultural communities deserve greater participation in the value created across the sector.</p>'
		. '<blockquote>This is the opportunity AgriXpert exists to pursue.</blockquote>'
		. '<h2>Our Mission &amp; Vision</h2>'
		. '<p><strong>Our Mission:</strong> To integrate agricultural value chains by connecting farmers, businesses, service providers, processors and buyers through market-led solutions, strategic partnerships and coordinated agricultural services that improve productivity, strengthen market access and create sustainable commercial opportunities.</p>'
		. '<p><strong>Our Vision:</strong> To become a leading agricultural value chain integration company in Africa, transforming how agricultural commodities are produced, sourced, aggregated, processed and connected to profitable markets.</p>'
		. '<p><strong>Our Core Purpose:</strong> Making Agriculture Work Better for Everyone Across the Value Chain.</p>'
		. '<h2>Our Strategic Objectives</h2>'
		. '<p>We pursue our mission through six strategic priorities:</p>'
		. '<ul>'
		. '<li><strong>Strengthening Agricultural Production</strong> &ndash; Promote organised, market-informed agricultural production through farmer mobilisation, production coordination and structured supply arrangements.</li>'
		. '<li><strong>Improving Market Connectivity</strong> &ndash; Connect farmers and agricultural businesses to reliable buyers, processors, distributors and institutional markets.</li>'
		. '<li><strong>Building Efficient Supply Chains</strong> &ndash; Improve agricultural commodity sourcing, aggregation, storage, handling and transportation through coordinated supply-chain solutions.</li>'
		. '<li><strong>Expanding Access to Agricultural Services</strong> &ndash; Facilitate access to mechanisation, irrigation, equipment, technical expertise and other essential agricultural services.</li>'
		. '<li><strong>Promoting Agricultural Value Addition</strong> &ndash; Support stronger connections between raw agricultural commodity production, processing infrastructure, product development and commercial markets.</li>'
		. '<li><strong>Mobilising Strategic Partnerships and Investment</strong> &ndash; Build partnerships with financial institutions, investors, agricultural businesses, development organisations and technology providers to unlock commercially viable agricultural opportunities.</li>'
		. '</ul>'
		. '<h2>Our Core Values</h2>'
		. '<ul>'
		. '<li><strong>Market Orientation</strong> &ndash; We believe agricultural activities should respond to real market demand and commercially viable opportunities.</li>'
		. '<li><strong>Integrity &amp; Transparency</strong> &ndash; We promote honest business relationships, accountability and transparency in our engagements and transactions.</li>'
		. '<li><strong>Collaboration</strong> &ndash; We believe sustainable agricultural development requires strong partnerships across the entire value chain.</li>'
		. '<li><strong>Innovation</strong> &ndash; We embrace practical solutions, appropriate technologies and new business models that improve agricultural efficiency and competitiveness.</li>'
		. '<li><strong>Sustainability</strong> &ndash; We promote responsible agricultural practices, resource efficiency and commercially sustainable business relationships.</li>'
		. '<li><strong>Value Creation</strong> &ndash; We focus on creating measurable economic value for farmers, businesses, partners, investors and the communities we serve.</li>'
		. '</ul>'
		. '<h2 id="leadership">Our Leadership</h2>'
		. '<p><strong>Amin Yahaya</strong> &ndash; Chief Executive Officer | AgriXpert</p>'
		. '<p>Amin Yahaya is an organisational development and enterprise development professional with over eight years of experience supporting businesses, entrepreneurs and organisations to strengthen their operations, improve performance and pursue sustainable growth.</p>'
		. '<p>His professional experience spans business development, enterprise acceleration, organisational transformation, strategic planning, market development and access to finance.</p>'
		. '<p>Through his work with agribusinesses, startups and development programmes, he has supported more than 50 agribusinesses and contributed to strengthening entrepreneurial capacity, business systems and commercial opportunities.</p>'
		. '<p>At AgriXpert, his leadership focuses on developing an integrated agricultural value chain business model that connects agricultural production to commercial markets while building strategic partnerships across the agricultural ecosystem.</p>'
		. '<blockquote>&ldquo;Strong agricultural businesses are not built through production alone. They are built through the right systems, the right partnerships and access to markets that reward value creation.&rdquo;</blockquote>'
		. '<h3>Our Growing Network of Expertise</h3>'
		. '<p>AgriXpert recognises that transforming agricultural value chains requires diverse expertise. We are building relationships with professionals, businesses and institutions across:</p>'
		. '<ul>'
		. '<li>Agricultural production and agronomy</li><li>Agricultural engineering and mechanisation</li><li>Commodity sourcing and procurement</li>'
		. '<li>Supply chain and logistics management</li><li>Food processing and value addition</li><li>Commercial trade and market development</li>'
		. '<li>Finance and investment</li><li>Technology and digital agriculture</li>'
		. '</ul>'
		. '<h2>Our Commitment</h2>'
		. '<p>We are committed to developing agricultural value chains where farmers have stronger market opportunities, buyers can access dependable supplies, businesses can grow through value addition, and investors can participate in commercially viable opportunities.</p>'
		. '<p>We recognise that meaningful transformation requires patience, partnerships, strong execution and a clear understanding of market realities. Our journey is about creating these connections, strengthening the businesses within them and contributing to a more competitive agricultural sector.</p>'
		. '<p><strong>AgriXpert &ndash; Market-Led Agricultural Value Chain Integrator. Connecting Agricultural Production to Profitable Markets.</strong></p>';
	}

	private static function what_we_do() {
		return '<p class="agx-page-tagline">Six Coordinated Functions, One Agricultural Value Chain.</p>'
		. '<p>We do not operate as a single-function service provider. AgriXpert organises farmers, agricultural service providers, aggregators, processors, logistics partners and buyers into a connected commercial system so that production reliably reaches profitable markets.</p>'
		. '[agrixpert_solutions]'
		. '<h2>Our Shared Commitment</h2>'
		. '<p>Every function is designed to work together, not in isolation. Mechanisation supports production. Aggregation depends on organised farmers. Processing depends on reliable supply. Markets depend on quality and consistency. We coordinate across all six functions so that value created at one stage is not lost at the next.</p>'
		. '<ul>'
		. '<li><strong>For Farmers</strong> &ndash; Access structured production opportunities, agricultural services and potential market linkages.</li>'
		. '<li><strong>For Buyers</strong> &ndash; Source agricultural commodities through coordinated supplier and aggregation networks.</li>'
		. '<li><strong>For Investors</strong> &ndash; Explore commercially viable opportunities across production, aggregation, mechanisation, storage and processing.</li>'
		. '</ul>';
	}

	private static function for_farmers() {
		return '<p class="agx-page-tagline">Farmer Registration, Production Support and Market Access</p>'
		. '<h2>Benefits</h2><ul>'
		. '<li>Access to structured markets.</li><li>Production planning and technical support.</li>'
		. '<li>Mechanisation and agricultural services.</li><li>Commodity aggregation.</li>'
		. '<li>Quality and post-harvest support.</li><li>Potential access to finance through financial partners.</li>'
		. '</ul>'
		. '<h2>Eligibility</h2><p>Eligibility criteria pending confirmation.</p>'
		. '<h2>Registration</h2><p>Register below to be considered for AgriXpert&rsquo;s production coordination, aggregation and market access programmes. A member of our team will follow up to verify your details.</p>'
		. '[agrixpert_farmer_form]';
	}

	private static function for_buyers() {
		return '<p class="agx-page-tagline">Commodity Sourcing, Procurement and Supply Agreements</p>'
		. '<h2>What You Can Do</h2><ul>'
		. '<li>Submit commodity sourcing requests.</li><li>Specify commodity, quantity and quality.</li>'
		. '<li>Access aggregated supply opportunities.</li><li>Establish procurement relationships.</li>'
		. '<li>Explore long-term supply arrangements.</li>'
		. '</ul>'
		. '<h2>Submit a Buyer Enquiry</h2><p>Tell us what you&rsquo;re sourcing and we&rsquo;ll connect you with available aggregated supply.</p>'
		. '[agrixpert_buyer_form]';
	}

	private static function for_cooperatives() {
		return '<p class="agx-page-tagline">Cooperative Registration, Group Market Access and Member Management</p>'
		. '<h2>Benefits</h2><ul>'
		. '<li>Register your cooperative, VSLA, farmer group or association once, as a single entity.</li>'
		. '<li>Access clustered-demand opportunities sized for group rather than individual volumes.</li>'
		. '<li>Post and manage marketplace listings on behalf of your members.</li>'
		. '<li>Maintain a membership record linked to AgriXpert&rsquo;s farmer registry.</li>'
		. '<li>Strengthen your group&rsquo;s standing with buyers, suppliers and development partners.</li>'
		. '</ul>'
		. '<h2>Eligibility</h2><p>Open to registered and informal cooperatives, VSLAs, farmer-based organisations and associations. Eligibility criteria pending confirmation.</p>'
		. '<h2>Registration</h2><p>Register your cooperative below to be considered for AgriXpert&rsquo;s clustered-demand and group market access programmes. A member of our team will follow up to verify your details and guide you through adding your members.</p>'
		. '[agrixpert_cooperative_form]';
	}

	private static function partnerships() {
		return '<ul>'
		. '<li><strong>Institutional Partnerships</strong> &ndash; Collaboration with development organisations and government programmes.</li>'
		. '<li><strong>Commercial Partnerships</strong> &ndash; Offtake agreements, processing collaborations and logistics partnerships.</li>'
		. '<li><strong>Investment Partnerships</strong> &ndash; Capital and co-investment opportunities in supply-chain infrastructure.</li>'
		. '</ul>'
		. '<p><a href="/contact">Start a Partnership Conversation</a></p>'
		. '<h2>Register as a Supplier Partner</h2><p>Mechanisation, input, logistics, storage and processing-equipment providers can register below to be considered as AgriXpert supplier partners.</p>'
		. '[agrixpert_supplier_form]';
	}

	private static function contact() {
		return '<p class="agx-page-tagline">Enquiries and Business Contacts</p>'
		. '<p><strong>Email:</strong> <a href="mailto:' . self::CONTACT_EMAIL . '">' . self::CONTACT_EMAIL . '</a></p>'
		. '<p><strong>Phone:</strong> +233 (0) 55-858-9254</p>'
		. '<p><strong>Location:</strong> Tamale, Northern Ghana</p>'
		. '<p>All enquiries submitted below, or sent directly, are routed to ' . self::CONTACT_EMAIL . '.</p>'
		. '[agrixpert_contact_form]';
	}

	// ---- legal pages, verbatim from the live app -----------------------

	private static function legal_note( $topic ) {
		return '<p><em>Note: This ' . esc_html( $topic ) . ' is provided as a Phase 1 draft to support the AgriXpert website launch and should be reviewed and finalised by qualified legal counsel before the platform relies on it at scale.</em></p>';
	}

	private static function privacy_policy() {
		return '<p><em>Last updated: 2026</em></p>'
		. '<h2>1. Introduction</h2><p>AgriXpert (&ldquo;AgriXpert&rdquo;, &ldquo;we&rdquo;, &ldquo;us&rdquo; or &ldquo;our&rdquo;), a platform operated by Samakose, is committed to protecting the privacy of farmers, buyers, suppliers, partners and other visitors (&ldquo;you&rdquo;) who use our website and platform. This Privacy Policy explains what personal data we collect, why we collect it, how we use and protect it, and the rights you have over it. It is intended to be consistent with Ghana&rsquo;s Data Protection Act, 2012 (Act 843) and with generally accepted international data protection principles.</p>'
		. '<h2>2. Information We Collect</h2><p>We may collect the following categories of information:</p><ul>'
		. '<li><strong>Identity and contact information:</strong> name, phone number, email address, physical or farm location, and business or organisation name.</li>'
		. '<li><strong>Registration information:</strong> farmer, buyer or supplier registration details, including commodity types, farm size, production volumes, and certification or compliance documentation you choose to submit.</li>'
		. '<li><strong>Transactional information:</strong> records of enquiries, sourcing requests, offtake agreements and any communications made through our contact forms or portal.</li>'
		. '<li><strong>Technical information:</strong> IP address, browser type, device information and usage data collected automatically when you visit our website, for security and analytics purposes.</li>'
		. '</ul>'
		. '<h2>3. How We Use Your Information</h2><p>We use the information we collect to:</p><ul>'
		. '<li>Facilitate registration, verification and onboarding of farmers, buyers and suppliers.</li>'
		. '<li>Connect farmers with buyers and support agricultural commodity sourcing and transactions.</li>'
		. '<li>Respond to enquiries submitted through our contact and registration forms.</li>'
		. '<li>Provide mechanisation, aggregation, training and other agribusiness support services.</li>'
		. '<li>Improve our website, platform and services, and communicate relevant updates.</li>'
		. '<li>Comply with applicable legal, regulatory and reporting obligations.</li>'
		. '</ul>'
		. '<h2>4. Legal Basis for Processing</h2><p>We process personal data on the basis of your consent, the performance of a contract or service you have requested, our legitimate business interests in operating an agricultural value-chain platform, and compliance with applicable law.</p>'
		. '<h2>5. Sharing of Information</h2><p>We do not sell personal data. We may share information with: buyers or farmers for the purpose of facilitating a transaction you have initiated; service providers who support our operations (such as hosting, payment or logistics providers) under confidentiality obligations; development partners and funders where required for programme reporting, in aggregated or anonymised form where practicable; and regulatory or government authorities where required by law.</p>'
		. '<h2>6. Data Security</h2><p>We implement reasonable administrative, technical and physical safeguards designed to protect personal data against unauthorised access, loss, misuse or alteration. No system is completely secure, and we encourage you to use strong passwords and protect your account credentials.</p>'
		. '<h2>7. Data Retention</h2><p>We retain personal data for as long as necessary to fulfil the purposes described in this policy, to comply with legal obligations, and to resolve disputes, after which it is securely deleted or anonymised.</p>'
		. '<h2>8. Your Rights</h2><p>Subject to applicable law, you have the right to access, correct or request deletion of your personal data, to object to or restrict certain processing, and to withdraw consent at any time. To exercise any of these rights, contact us using the details below.</p>'
		. '<h2>9. Children&rsquo;s Privacy</h2><p>Our platform is intended for business use by farmers, buyers, suppliers and partners who are 18 years of age or older. We do not knowingly collect personal data from children.</p>'
		. '<h2>10. Changes to This Policy</h2><p>We may update this Privacy Policy from time to time to reflect changes in our practices or legal requirements. We will post the updated version on this page with a revised &ldquo;last updated&rdquo; date.</p>'
		. '<h2>11. Contact Us</h2><p>If you have questions about this Privacy Policy or how your personal data is handled, please contact us at <a href="mailto:' . self::CONTACT_EMAIL . '">' . self::CONTACT_EMAIL . '</a>.</p>'
		. self::legal_note( 'policy' );
	}

	private static function cookie_policy() {
		return '<p><em>Last updated: 2026</em></p>'
		. '<h2>1. What Are Cookies</h2><p>Cookies are small text files placed on your device when you visit a website. They help the website function properly, remember your preferences, and understand how visitors use the site. This Cookie Policy explains how AgriXpert, a platform operated by Samakose, uses cookies and similar technologies on our website.</p>'
		. '<h2>2. Types of Cookies We Use</h2><ul>'
		. '<li><strong>Strictly necessary cookies:</strong> required for the website to function, such as enabling page navigation and secure access. These cannot be switched off.</li>'
		. '<li><strong>Performance and analytics cookies:</strong> help us understand how visitors interact with the website, such as which pages are visited most, so we can improve content and usability.</li>'
		. '<li><strong>Functionality cookies:</strong> remember choices you make, such as display preferences, to provide a more personalised experience.</li>'
		. '</ul><p>AgriXpert does not currently use third-party advertising or cross-site tracking cookies.</p>'
		. '<h2>3. Why We Use Cookies</h2><p>We use cookies to keep our website secure and functioning correctly, to understand and improve how the site is used by farmers, buyers, suppliers and partners, and to remember basic preferences across your visits.</p>'
		. '<h2>4. Managing Cookies</h2><p>Most web browsers allow you to control cookies through their settings, including blocking or deleting cookies. Please note that disabling certain cookies may affect the functionality of the AgriXpert website. Guidance on managing cookies is generally available in your browser&rsquo;s help documentation.</p>'
		. '<h2>5. Changes to This Policy</h2><p>We may update this Cookie Policy from time to time to reflect changes in our practices or applicable law. Any changes will be posted on this page with a revised &ldquo;last updated&rdquo; date.</p>'
		. '<h2>6. Contact Us</h2><p>If you have questions about this Cookie Policy, please contact us at <a href="mailto:' . self::CONTACT_EMAIL . '">' . self::CONTACT_EMAIL . '</a>.</p>'
		. self::legal_note( 'policy' )
		. '<p><em>Note: no cookie-consent mechanism exists yet on this site &mdash; implement one before relying on non-essential cookies at scale.</em></p>';
	}

	private static function terms_of_service() {
		return '<p><em>Last updated: 2026</em></p>'
		. '<h2>1. Acceptance of Terms</h2><p>These Terms of Service (&ldquo;Terms&rdquo;) govern your access to and use of the AgriXpert website and platform, operated by Samakose (&ldquo;AgriXpert&rdquo;, &ldquo;we&rdquo;, &ldquo;us&rdquo; or &ldquo;our&rdquo;). By accessing or using our website, registering as a farmer, buyer or supplier, or otherwise engaging with our services, you agree to be bound by these Terms. If you do not agree, please do not use our website or services.</p>'
		. '<h2>2. Description of Services</h2><p>AgriXpert provides an agricultural value-chain platform connecting farmers, buyers, suppliers and development partners, offering services that may include farmer registration and aggregation, mechanisation and farm services, commodity sourcing and procurement support, market and value-chain information, training and capacity building, and partnership facilitation. Not all services described on this website may be available in every location or at every stage of our rollout.</p>'
		. '<h2>3. Eligibility and Registration</h2><p>To register as a farmer, buyer or supplier, you must be at least 18 years old and able to enter into legally binding agreements. You agree to provide accurate, current and complete information during registration and to update it as necessary. You are responsible for maintaining the confidentiality of any account credentials and for all activity under your account.</p>'
		. '<h2>4. Role of AgriXpert</h2><p>AgriXpert facilitates connections, information flow and support services between farmers, buyers and other participants in the agricultural value chain. Unless expressly stated, AgriXpert is not a party to, and does not guarantee the performance of, any commercial agreement entered into between farmers and buyers through our platform. Each party remains responsible for conducting its own due diligence and for fulfilling its contractual obligations.</p>'
		. '<h2>5. User Conduct</h2><p>You agree not to:</p><ul>'
		. '<li>Provide false, misleading or fraudulent information.</li>'
		. '<li>Use the platform for any unlawful purpose or in violation of these Terms.</li>'
		. '<li>Interfere with or disrupt the security or proper functioning of the website or platform.</li>'
		. '<li>Attempt to gain unauthorised access to other users&rsquo; accounts or to any AgriXpert systems.</li>'
		. '<li>Misrepresent your affiliation with AgriXpert, a farmer group, cooperative or business.</li>'
		. '</ul>'
		. '<h2>6. Fees</h2><p>Access to the AgriXpert website is currently free. Fees, if any, for specific services such as mechanisation, aggregation or facilitated transactions will be communicated and agreed with you separately before those services are provided.</p>'
		. '<h2>7. Intellectual Property</h2><p>The AgriXpert name, logo, website content, design and underlying software are the property of Samakose and its licensors and are protected by applicable intellectual property laws. You may not reproduce, distribute or create derivative works from our content without prior written permission, except as necessary to use the platform for its intended purpose.</p>'
		. '<h2>8. Disclaimers</h2><p>The AgriXpert website and platform are provided on an &ldquo;as is&rdquo; and &ldquo;as available&rdquo; basis. While we strive for accuracy, we do not warrant that market information, pricing or content on this website is complete, current or error-free. AgriXpert does not guarantee specific agricultural yields, prices, sales volumes or business outcomes for any farmer, buyer or supplier using the platform.</p>'
		. '<h2>9. Limitation of Liability</h2><p>To the fullest extent permitted by applicable law, AgriXpert shall not be liable for any indirect, incidental, special or consequential damages arising from your use of, or inability to use, the website or platform, or from any transaction or agreement entered into between users of the platform.</p>'
		. '<h2>10. Termination</h2><p>We may suspend or terminate your access to the platform at our discretion if we reasonably believe you have violated these Terms or engaged in conduct harmful to AgriXpert, other users, or the integrity of the platform.</p>'
		. '<h2>11. Governing Law</h2><p>These Terms are governed by the laws of the Republic of Ghana, without regard to conflict-of-law principles. Any disputes arising under these Terms shall be subject to the exclusive jurisdiction of the courts of Ghana, unless otherwise agreed in writing.</p>'
		. '<h2>12. Changes to These Terms</h2><p>We may revise these Terms from time to time. Continued use of the website or platform after changes are posted constitutes acceptance of the revised Terms.</p>'
		. '<h2>13. Contact Us</h2><p>Questions about these Terms should be directed to <a href="mailto:' . self::CONTACT_EMAIL . '">' . self::CONTACT_EMAIL . '</a>.</p>'
		. self::legal_note( 'Terms of Service document' );
	}
}
