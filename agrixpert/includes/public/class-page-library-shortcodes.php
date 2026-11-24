<?php
/**
 * Public shortcodes rendering the migrated value-chain and solution
 * content (see includes/data/class-page-library.php). An admin creates
 * an ordinary WP Page (e.g. /value-chains/maize) and drops one shortcode
 * into it — no custom page templates or routing required, consistent
 * with the "plain WP admin pages, not a React bundle" decision.
 *
 * - [agrixpert_value_chains]            listing/grid of all 5 value chains
 * - [agrixpert_value_chain slug="maize"] full detail page for one
 * - [agrixpert_solutions]               listing/grid of all 6 solutions
 * - [agrixpert_solution slug="..."]     full detail page for one
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AgriXpert_Page_Library_Shortcodes {

	public static function init() {
		add_shortcode( 'agrixpert_value_chains', array( __CLASS__, 'value_chains_list' ) );
		add_shortcode( 'agrixpert_value_chain', array( __CLASS__, 'value_chain_single' ) );
		add_shortcode( 'agrixpert_solutions', array( __CLASS__, 'solutions_list' ) );
		add_shortcode( 'agrixpert_solution', array( __CLASS__, 'solution_single' ) );
	}

	// ---- listings -----------------------------------------------------

	public static function value_chains_list() {
		$items = AgriXpert_Page_Library::all( AgriXpert_Page_Library::VALUE_CHAIN );
		return self::render_grid( $items, 'value-chains' );
	}

	public static function solutions_list() {
		$items = AgriXpert_Page_Library::all( AgriXpert_Page_Library::SOLUTION );
		return self::render_grid( $items, 'solutions' );
	}

	private static function render_grid( array $items, $base_path ) {
		ob_start();
		echo '<div class="agx-pagelib-grid">';
		foreach ( $items as $slug => $item ) {
			$href  = home_url( '/' . $base_path . '/' . $slug );
			$name  = $item['name'] ?? '';
			$tag   = $item['tagline'] ?? '';
			$image = AgriXpert_Page_Library::stock_image( $item['heroImageKey'] ?? '' );
			echo '<a class="agx-pagelib-card" href="' . esc_url( $href ) . '">';
			if ( $image ) {
				echo '<img src="' . esc_url( $image['src'] ) . '" alt="' . esc_attr( $image['alt'] ) . '" loading="lazy" />';
			}
			if ( isset( $item['number'] ) ) {
				echo '<span class="agx-pagelib-number">' . esc_html( $item['number'] ) . '</span>';
			}
			echo '<h3>' . esc_html( $name ) . '</h3>';
			echo '<p>' . esc_html( $tag ) . '</p>';
			echo '</a>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	// ---- single detail pages ------------------------------------------

	public static function value_chain_single( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts );
		$vc   = AgriXpert_Page_Library::get( AgriXpert_Page_Library::VALUE_CHAIN, sanitize_title( $atts['slug'] ) );
		if ( ! $vc ) {
			return '<p>' . esc_html__( 'Value chain not found.', 'agrixpert' ) . '</p>';
		}

		ob_start();
		echo '<div class="agx-pagelib-detail">';
		self::hero( $vc );

		self::section( __( 'Overview', 'agrixpert' ), $vc['overview']['intro'] ?? '', $vc['overview']['paragraphs'] ?? array(), null, $vc['overview']['proposition'] ?? '' );
		self::list_section( __( 'Sourcing', 'agrixpert' ), $vc['sourcing'] ?? array(), 'factors' );
		self::list_section( __( 'Farmer Network', 'agrixpert' ), $vc['farmerNetwork'] ?? array(), 'groups' );
		self::list_section( __( 'Production Coordination', 'agrixpert' ), $vc['coordination'] ?? array(), 'items' );
		self::list_section( __( 'Aggregation', 'agrixpert' ), $vc['aggregation'] ?? array(), 'items' );
		self::quality_section( $vc['quality'] ?? array() );
		self::market_section( $vc['markets'] ?? array() );
		self::list_section( __( 'Partnerships', 'agrixpert' ), $vc['partnerships'] ?? array(), 'items' );
		self::closing( $vc['closing'] ?? array() );
		self::value_chain_ctas( $vc['name'] ?? '' );
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * The three commodity-prefilled action buttons every value-chain page
	 * ends with, matching ValueChainCtas.tsx in the live app exactly (same
	 * three destinations, same commodity query param). Unlike a solution's
	 * `closing.ctas`, these are not stored in the JSON data — the live
	 * component generates them from the page's own commodity name, so this
	 * does the same rather than requiring every value-chains.json entry to
	 * repeat identical CTA data.
	 */
	private static function value_chain_ctas( $commodity ) {
		if ( ! $commodity ) {
			return;
		}
		$q = rawurlencode( $commodity );
		echo '<section class="agx-pagelib-section agx-pagelib-cta-row">';
		echo '<a class="agx-pagelib-cta agx-cta-primary" href="' . esc_url( home_url( '/for-farmers?commodity=' . $q ) . '#register' ) . '"><span>' . esc_html__( 'I Want to Produce', 'agrixpert' ) . '</span><small>' . esc_html( sprintf( __( 'Register as a %s farmer', 'agrixpert' ), strtolower( $commodity ) ) ) . '</small></a>';
		echo '<a class="agx-pagelib-cta agx-cta-gold" href="' . esc_url( home_url( '/for-buyers?commodity=' . $q ) . '#enquiry' ) . '"><span>' . esc_html__( 'I Want to Buy', 'agrixpert' ) . '</span><small>' . esc_html__( 'Submit a sourcing request', 'agrixpert' ) . '</small></a>';
		echo '<a class="agx-pagelib-cta agx-cta-outline" href="' . esc_url( home_url( '/contact?type=' . rawurlencode( 'Partnership & Investment' ) . '&commodity=' . $q ) . '#form' ) . '"><span>' . esc_html__( 'I Want to Partner', 'agrixpert' ) . '</span><small>' . esc_html__( 'Explore a commercial partnership', 'agrixpert' ) . '</small></a>';
		echo '</section>';
	}

	public static function solution_single( $atts ) {
		$atts = shortcode_atts( array( 'slug' => '' ), $atts );
		$sol  = AgriXpert_Page_Library::get( AgriXpert_Page_Library::SOLUTION, sanitize_title( $atts['slug'] ) );
		if ( ! $sol ) {
			return '<p>' . esc_html__( 'Solution not found.', 'agrixpert' ) . '</p>';
		}

		ob_start();
		echo '<div class="agx-pagelib-detail">';
		self::hero( $sol );

		self::section( __( 'Overview', 'agrixpert' ), $sol['overview']['intro'] ?? '', $sol['overview']['paragraphs'] ?? array(), null, $sol['overview']['proposition'] ?? '' );

		foreach ( (array) ( $sol['sections'] ?? array() ) as $section ) {
			self::list_section( $section['heading'] ?? '', $section, 'items' );
		}

		if ( ! empty( $sol['whoBenefits'] ) ) {
			echo '<section class="agx-pagelib-section"><h2>' . esc_html__( 'Who Benefits', 'agrixpert' ) . '</h2><div class="agx-pagelib-benefit-grid">';
			foreach ( $sol['whoBenefits'] as $b ) {
				echo '<div class="agx-pagelib-benefit"><strong>' . esc_html( $b['label'] ?? '' ) . '</strong><p>' . esc_html( $b['body'] ?? '' ) . '</p></div>';
			}
			echo '</div></section>';
		}

		self::list_section( __( 'Partnerships', 'agrixpert' ), $sol['partnerships'] ?? array(), 'items' );

		if ( ! empty( $sol['closing'] ) ) {
			$closing = $sol['closing'];
			echo '<section class="agx-pagelib-closing">';
			echo '<h2>' . esc_html( $closing['headline'] ?? '' ) . '</h2>';
			echo '<p>' . esc_html( $closing['body'] ?? '' ) . '</p>';
			if ( ! empty( $closing['ctas'] ) ) {
				echo '<div class="agx-pagelib-cta-row">';
				foreach ( $closing['ctas'] as $cta ) {
					echo '<a class="agx-pagelib-cta" href="' . esc_url( $cta['href'] ?? '#' ) . '"><span>' . esc_html( $cta['label'] ?? '' ) . '</span>';
					if ( ! empty( $cta['sub'] ) ) {
						echo '<small>' . esc_html( $cta['sub'] ) . '</small>';
					}
					echo '</a>';
				}
				echo '</div>';
			}
			echo '</section>';
		}
		echo '</div>';
		return ob_get_clean();
	}

	// ---- shared rendering helpers --------------------------------------

	private static function hero( array $item ) {
		$image = AgriXpert_Page_Library::stock_image( $item['heroImageKey'] ?? '' );
		echo '<header class="agx-pagelib-hero">';
		if ( $image ) {
			echo '<img src="' . esc_url( $image['src'] ) . '" alt="' . esc_attr( $image['alt'] ) . '" />';
		}
		echo '<h1>' . esc_html( $item['name'] ?? '' ) . '</h1>';
		echo '<p class="agx-pagelib-tagline">' . esc_html( $item['tagline'] ?? '' ) . '</p>';
		echo '</header>';
	}

	private static function section( $heading, $intro, array $paragraphs, $closing = null, $proposition = '' ) {
		if ( ! $heading && ! $intro && empty( $paragraphs ) ) {
			return;
		}
		echo '<section class="agx-pagelib-section">';
		if ( $heading ) {
			echo '<h2>' . esc_html( $heading ) . '</h2>';
		}
		if ( $intro ) {
			echo '<p class="agx-pagelib-intro">' . esc_html( $intro ) . '</p>';
		}
		foreach ( $paragraphs as $p ) {
			echo '<p>' . esc_html( $p ) . '</p>';
		}
		if ( $proposition ) {
			echo '<p class="agx-pagelib-proposition">' . esc_html( $proposition ) . '</p>';
		}
		if ( $closing ) {
			echo '<p class="agx-pagelib-closing-line">' . esc_html( $closing ) . '</p>';
		}
		echo '</section>';
	}

	/**
	 * Renders a {intro, <list_key>: string[], closing, note?} block —
	 * the shape shared by sourcing/farmerNetwork/coordination/aggregation/
	 * partnerships on value chains, and every item in a solution's
	 * `sections` array.
	 */
	private static function list_section( $heading, array $block, $list_key ) {
		if ( empty( $block ) ) {
			return;
		}
		echo '<section class="agx-pagelib-section">';
		if ( $heading ) {
			echo '<h2>' . esc_html( $heading ) . '</h2>';
		}
		if ( ! empty( $block['intro'] ) ) {
			echo '<p class="agx-pagelib-intro">' . esc_html( $block['intro'] ) . '</p>';
		}
		$list = $block[ $list_key ] ?? array();
		if ( ! empty( $list ) ) {
			echo '<ul>';
			foreach ( $list as $li ) {
				echo '<li>' . esc_html( $li ) . '</li>';
			}
			echo '</ul>';
		}
		if ( ! empty( $block['closing'] ) ) {
			echo '<p class="agx-pagelib-closing-line">' . esc_html( $block['closing'] ) . '</p>';
		}
		if ( ! empty( $block['note'] ) ) {
			echo '<p class="agx-pagelib-note">' . esc_html( $block['note'] ) . '</p>';
		}
		echo '</section>';
	}

	/** Value chain "quality" block: intro + grouped sub-lists + closing. */
	private static function quality_section( array $quality ) {
		if ( empty( $quality ) ) {
			return;
		}
		echo '<section class="agx-pagelib-section"><h2>' . esc_html__( 'Quality', 'agrixpert' ) . '</h2>';
		if ( ! empty( $quality['intro'] ) ) {
			echo '<p class="agx-pagelib-intro">' . esc_html( $quality['intro'] ) . '</p>';
		}
		foreach ( (array) ( $quality['groups'] ?? array() ) as $group ) {
			if ( ! empty( $group['label'] ) ) {
				echo '<h3>' . esc_html( $group['label'] ) . '</h3>';
			}
			echo '<ul>';
			foreach ( (array) ( $group['items'] ?? array() ) as $li ) {
				echo '<li>' . esc_html( $li ) . '</li>';
			}
			echo '</ul>';
		}
		if ( ! empty( $quality['closing'] ) ) {
			echo '<p class="agx-pagelib-closing-line">' . esc_html( $quality['closing'] ) . '</p>';
		}
		echo '</section>';
	}

	/** Value chain "markets" block: intro + {label, body} items + closing. */
	private static function market_section( array $markets ) {
		if ( empty( $markets ) ) {
			return;
		}
		echo '<section class="agx-pagelib-section"><h2>' . esc_html__( 'Markets', 'agrixpert' ) . '</h2>';
		if ( ! empty( $markets['intro'] ) ) {
			echo '<p class="agx-pagelib-intro">' . esc_html( $markets['intro'] ) . '</p>';
		}
		echo '<div class="agx-pagelib-benefit-grid">';
		foreach ( (array) ( $markets['items'] ?? array() ) as $item ) {
			echo '<div class="agx-pagelib-benefit"><strong>' . esc_html( $item['label'] ?? '' ) . '</strong><p>' . esc_html( $item['body'] ?? '' ) . '</p></div>';
		}
		echo '</div>';
		if ( ! empty( $markets['closing'] ) ) {
			echo '<p class="agx-pagelib-closing-line">' . esc_html( $markets['closing'] ) . '</p>';
		}
		echo '</section>';
	}

	/** Value chain top-level "closing" block: {headline, body}. */
	private static function closing( array $closing ) {
		if ( empty( $closing ) ) {
			return;
		}
		echo '<section class="agx-pagelib-closing">';
		echo '<h2>' . esc_html( $closing['headline'] ?? '' ) . '</h2>';
		echo '<p>' . esc_html( $closing['body'] ?? '' ) . '</p>';
		echo '</section>';
	}
}

AgriXpert_Page_Library_Shortcodes::init();
