<?php
/**
 * Pro Upsell Module (Free plugin).
 *
 * Injects a branded upgrade banner + a locked list of the 12 Pro features
 * into Free's existing "Sticky Header Effects" panel section. This is a
 * pure upsell — it renders nothing on the frontend and registers no Pro
 * functionality. It only shows users what Pro adds and links to pricing.
 *
 * IMPORTANT: this module's controls are registered ONLY when the Pro plugin
 * is NOT active. When Pro is active, the real Pro controls take over the
 * same panel and this upsell stays hidden (no duplicate / conflicting UI).
 *
 * @package she-header
 * @since   2.2.15
 */

namespace SheHeader\Modules\ProUpsell;

use Elementor\Controls_Manager;
use Elementor\Controls_Stack;
use SheHeader\Base\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Module.
 */
class Module extends Module_Base {

	/**
	 * Brand colors — matched to the Sticky Header Effects landing page.
	 */
	const BRAND_PRIMARY = '#E6017E'; // magenta accent.
	const BRAND_BRIGHT  = '#FF3DA0'; // gradient highlight.
	const BRAND_PRESS   = '#BE0167'; // pressed/hover.
	const BRAND_LIGHT   = '#FDE7F1'; // light tint background.
	const INK           = '#1C0F16'; // heading ink.
	const INK_MUTED     = '#7C6B74'; // muted ink.

	/**
	 * Pricing / upgrade destination.
	 */
	const PRICING_URL = 'https://stickyheadereffects.com/#pricing';

	/**
	 * User-meta key remembering that the current user permanently dismissed
	 * the "Pro is now live" announcement.
	 */
	const LIVE_NOTICE_META = 'she_pro_live_notice_dismissed';

	/**
	 * AJAX action + nonce name for dismissing the announcement.
	 */
	const LIVE_NOTICE_ACTION = 'she_pro_dismiss_live_notice';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		$this->add_actions();
	}

	/**
	 * Module name (used by autoloader / module manager).
	 *
	 * @return string
	 */
	public function get_name() {
		return 'pro-upsell';
	}

	/**
	 * Whether this module should load.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return (bool) apply_filters( 'she_is_pro_feature_active', true, 'pro-upsell' );
	}

	/**
	 * Is the Pro plugin active? If so, the real Pro controls render and we
	 * must NOT show the upsell (avoids duplicate panel UI).
	 *
	 * The Pro main file defines SHE_PRO_VERSION at load time (before
	 * plugins_loaded), so by the time Elementor renders controls this is a
	 * reliable signal.
	 *
	 * @return bool
	 */
	private function is_pro_active() {
		return defined( 'SHE_PRO_VERSION' );
	}

	/**
	 * Has the current user dismissed the "Pro is now live" announcement?
	 *
	 * Stored per-user so each admin/editor manages their own dismissal.
	 *
	 * @return bool
	 */
	private function is_live_notice_dismissed() {
		return (bool) get_user_meta( get_current_user_id(), self::LIVE_NOTICE_META, true );
	}

	/**
	 * AJAX: persist the per-user dismissal of the announcement.
	 *
	 * @return void
	 */
	public function ajax_dismiss_live_notice() {
		if ( ! check_ajax_referer( self::LIVE_NOTICE_ACTION, 'nonce', false ) ) {
			wp_send_json_error();
		}
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error();
		}
		update_user_meta( get_current_user_id(), self::LIVE_NOTICE_META, 1 );
		wp_send_json_success();
	}

	/**
	 * Inline editor script: handle the announcement's close button — remove
	 * the banner and POST the dismissal so it never shows again.
	 *
	 * @return void
	 */
	public function enqueue_dismiss_script() {
		$config = wp_json_encode(
			array(
				'ajaxurl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( self::LIVE_NOTICE_ACTION ),
				'action'  => self::LIVE_NOTICE_ACTION,
			)
		);

		$js = 'window.SHEProNotice=' . $config . ';'
			. '(function(){document.addEventListener("click",function(e){'
			. 'var b=e.target.closest&&e.target.closest(".she-pro-live__close");if(!b){return;}'
			. 'e.preventDefault();'
			. 'var box=b.closest(".she-pro-live");if(box&&box.parentNode){box.parentNode.removeChild(box);}'
			. 'var d=new FormData();d.append("action",window.SHEProNotice.action);d.append("nonce",window.SHEProNotice.nonce);'
			. 'fetch(window.SHEProNotice.ajaxurl,{method:"POST",credentials:"same-origin",body:d});'
			. '});})();';

		wp_add_inline_script( 'elementor-editor', $js );
	}

	/**
	 * Inline editor script: make each locked Pro feature row open the pricing
	 * page on click. The switcher itself stays non-toggleable — its
	 * `pointer-events:none` lets the click fall through to the row wrapper,
	 * which this delegated handler catches. Clicks on the row's own Live Demo /
	 * Read Docs links are left alone so they navigate to their own URLs.
	 *
	 * @return void
	 */
	public function enqueue_upsell_click_script() {
		$url = add_query_arg(
			array(
				'ref'          => 'plugin',
				'utm_source'   => 'plugin',
				'utm_medium'   => 'admin',
				'utm_campaign' => 'gopro',
				'utm_content'  => 'elementor-locked',
			),
			self::PRICING_URL
		);

		$js = 'window.SHEProUpsellURL=' . wp_json_encode( $url ) . ';'
			. '(function(){document.addEventListener("click",function(e){'
			. 'if(!e.target.closest){return;}'
			// Let the row's own Demo/Docs links navigate normally.
			. 'if(e.target.closest(".she-pro-links")){return;}'
			. 'var row=e.target.closest(".she-pro-locked-ctrl");if(!row){return;}'
			. 'e.preventDefault();'
			. 'window.open(window.SHEProUpsellURL,"_blank","noopener");'
			. '});})();';

		wp_add_inline_script( 'elementor-editor', $js );
	}

	/**
	 * Build the "Pro is now live" announcement notice.
	 *
	 * A compact, brand-accented banner that sits near the top of the panel
	 * (just under "Import Presets") to announce that the Pro plugin is now
	 * available. Self-contained CSS so it reads natively in light & dark
	 * editor themes and does not depend on the promo card below.
	 *
	 * @return string
	 */
	private function build_live_notice_html() {
		$primary = self::BRAND_PRIMARY;
		$bright  = self::BRAND_BRIGHT;
		$press   = self::BRAND_PRESS;

		// UTM appended to the live-site (stickyheadereffects.com) pricing link.
		$url = add_query_arg(
			array(
				'ref'          => 'plugin',
				'utm_source'   => 'plugin',
				'utm_medium'   => 'admin',
				'utm_campaign' => 'gopro',
				'utm_content'  => 'elementor-notice',
			),
			self::PRICING_URL
		);

		$badge_label = __( 'Pro is Live', 'she-header' );
		$title       = __( 'Sticky Header Effects for Elementor Pro is live, 12 new effects', 'she-header' );
		$cta_label   = __( 'Get Pro', 'she-header' );
		$guarantee   = __( '60-day money-back guarantee', 'she-header' );

		// Highlight bullets: feature name + a short benefit.
		$features = array(
			array( __( 'Sticky Until', 'she-header' ), __( 'stop the header exactly where you want', 'she-header' ) ),
			array( __( 'Header Replace on Scroll', 'she-header' ), __( 'swap the whole header mid-scroll', 'she-header' ) ),
			array( __( 'Multi-Sticky + Auto-Hide', 'she-header' ), __( 'run more than one sticky header', 'she-header' ) ),
		);
		$more = __( '…and 9 more, plus 50+ ready templates.', 'she-header' );

		// Spark / rocket-style badge icon (inline SVG, white on gradient).
		$spark = '<svg width="11" height="11" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M13 2 4.5 13H11l-1 9 8.5-11H12l1-9Z"/></svg>';

		$css = '
		<style>
		.she-pro-live{position:relative;overflow:hidden;border:1px solid var(--e-a-border-color,rgba(230,1,126,.18));border-radius:8px;padding:14px 34px 14px 16px;margin:10px 0 6px;background:rgba(230,1,126,.05);}
		.she-pro-live::before{content:"";position:absolute;left:0;top:0;bottom:0;width:4px;background:linear-gradient(180deg,' . $bright . ',' . $primary . ');}
		.she-pro-live__close{position:absolute;top:8px;right:8px;width:22px;height:22px;display:inline-flex;align-items:center;justify-content:center;padding:0;border:0;background:transparent;color:var(--e-a-color-txt-muted,#7c6b74);cursor:pointer;border-radius:4px;line-height:0;transition:background .15s ease,color .15s ease;}
		.she-pro-live__close:hover{background:rgba(230,1,126,.12);color:var(--e-a-color-txt,#0c0d0e);}
		.she-pro-live__badge{display:inline-flex;align-items:center;gap:5px;background:linear-gradient(90deg,' . $bright . ',' . $primary . ');color:#fff;font-size:9.5px;font-weight:700;letter-spacing:.5px;text-transform:uppercase;padding:3px 9px;border-radius:999px;margin-bottom:10px;}
		.she-pro-live__badge svg{display:block;}
		.she-pro-live__title{display:block;font-size:13.5px;font-weight:700;line-height:1.35;margin:0 0 8px;color:var(--e-a-color-txt,#0c0d0e);}
		.she-pro-live__list{margin:0 0 8px;padding:0;list-style:none;}
		.she-pro-live__list li{position:relative;padding-left:13px;font-size:11.5px;line-height:1.5;margin:0 0 5px;color:var(--e-a-color-txt-muted,#7c6b74);}
		.she-pro-live__list li::before{content:"";position:absolute;left:0;top:6px;width:5px;height:5px;border-radius:50%;background:' . $primary . ';}
		.she-pro-live__list strong{color:var(--e-a-color-txt,#0c0d0e);font-weight:700;}
		.she-pro-live__text{font-size:11.5px;line-height:1.5;margin:0 0 13px;color:var(--e-a-color-txt-muted,#7c6b74);}
		.she-pro-live__btn{display:inline-block;background:' . $primary . ';color:#fff !important;font-size:12px;font-weight:700;text-decoration:none;padding:8px 18px;border-radius:5px;transition:background .15s ease;}
		.she-pro-live__btn:hover{background:' . $press . ';color:#fff !important;text-decoration:none;}
		.she-pro-live__guarantee{display:block;margin-top:8px;font-size:10px;line-height:1.4;color:var(--e-a-color-txt-muted,#7c6b74);}
		</style>';

		$close = '<button type="button" class="she-pro-live__close" aria-label="' . esc_attr__( 'Dismiss', 'she-header' ) . '"><svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg></button>';

		$bullets = '';
		foreach ( $features as $feature ) {
			$bullets .= '<li><strong>' . esc_html( $feature[0] ) . '</strong> — ' . esc_html( $feature[1] ) . '</li>';
		}

		$html = '<div class="she-pro-live">'
			. $close
			. '<span class="she-pro-live__badge">' . $spark . esc_html( $badge_label ) . '</span>'
			. '<strong class="she-pro-live__title">' . esc_html( $title ) . '</strong>'
			. '<ul class="she-pro-live__list">' . $bullets . '</ul>'
			. '<p class="she-pro-live__text">' . esc_html( $more ) . '</p>'
			. '<a class="she-pro-live__btn" href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $cta_label ) . '</a>'
			. '<span class="she-pro-live__guarantee">' . esc_html( $guarantee ) . '</span>'
			. '</div>'
			. $css;

		return $html;
	}

	/**
	 * Scoped CSS for the locked Pro feature controls and their Demo/Docs links.
	 *
	 * (The promo "Unlock all Pro features" card was removed; only the styling
	 * the locked controls rely on is output here.)
	 *
	 * @return string
	 */
	private function build_upsell_html() {
		$primary = self::BRAND_PRIMARY;

		return '
		<style>
		/* Locked Pro controls: PRO badge on the label, dimmed + non-toggleable switcher.
		   The whole row is clickable and opens the pricing page (see enqueue_upsell_click_script).
		   The switcher stays non-toggleable (pointer-events:none passes the click through to the
		   row wrapper, which the delegated handler catches). */
		.she-pro-locked-ctrl .elementor-control-title{display:flex;flex-wrap:wrap;align-items:center;column-gap:6px;row-gap:4px;pointer-events:auto;cursor:pointer;transition:color .15s ease;}
		.she-pro-locked-ctrl .elementor-control-title::after{content:"PRO";display:inline-block;padding:1px 5px;border-radius:3px;background:' . $primary . ';color:#fff;font-size:8px;font-weight:700;letter-spacing:.5px;line-height:1.7;}
		.she-pro-locked-ctrl .elementor-switch{opacity:.5;}
		.she-pro-locked-ctrl{cursor:pointer;}
		/* Highlight + embolden only the TITLE TEXT on hover (not the whole row).
		   pointer-events:auto is re-enabled on the title so its :hover fires; the
		   switch keeps pointer-events:none from .elementor-control-content below. */
		.she-pro-locked-ctrl .elementor-control-title:hover{color:' . $primary . ';font-weight:600;}
		.she-pro-locked-ctrl .elementor-control-content{pointer-events:none;}
		/* Demo / Docs text links beneath each feature description. */
		.she-pro-locked-ctrl .she-pro-desc{display:block;}
		.she-pro-locked-ctrl .she-pro-links{display:flex;align-items:center;gap:7px;margin-top:7px;font-size:11px;}
		.she-pro-locked-ctrl .she-pro-links a{pointer-events:auto;cursor:pointer;font-weight:600;text-decoration:none !important;border-block-end:0 !important;color:var(--e-a-color-txt,#1d2327) !important;transition:color .15s ease;}
		.she-pro-locked-ctrl .she-pro-links a:hover{color:' . $primary . ' !important;text-decoration:none !important;border-block-end:0 !important;}
		.she-pro-locked-ctrl .she-pro-links__sep{opacity:.4;}
		</style>';
	}

	/**
	 * Register the upsell controls inside Free's Sticky Header Effects panel.
	 *
	 * @param Controls_Stack $element Elementor section or container.
	 * @return void
	 */
	public function register_controls( Controls_Stack $element ) {
		// Never show the upsell when Pro is active.
		if ( $this->is_pro_active() ) {
			return;
		}

		// "Pro is now live" announcement — placed near the top of the panel,
		// right after the "Import Presets" button. Shows regardless of the
		// main "Enable" toggle so users discover Pro immediately. Skipped once
		// the current user has dismissed it via its close button.
		if ( ! $this->is_live_notice_dismissed() ) {
			$element->add_control(
				'she_pro_live_notice',
				array(
					'type'        => Controls_Manager::RAW_HTML,
					'raw'         => $this->build_live_notice_html(),
					'label_block' => true,
				),
				array(
					'position' => array(
						'of' => 'smart-preset-button',
						'at' => 'after',
					),
				)
			);
		}

		// Every Pro feature shown as a NATIVE switcher control with a PRO badge,
		// but LOCKED: the switcher can't be toggled on in Free (CSS disables
		// interaction; see build_upsell_html). Labels match the Pro plugin's
		// feature headings exactly. render_type 'none' so they never affect the
		// frontend.
		// Each feature: label, description, and its own Live Demo + Read Docs URLs.
		// Each feature links to its Live Demo and Read Docs pages.
		$pro_features = array(
			'display_condition' => array(
				'label' => __( 'Display Conditions', 'she-header' ),
				'desc'  => __( 'Show or hide the sticky header on specific pages, posts, roles & more.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demo/display-conditions/',
				'docs'  => 'https://stickyheadereffects.com/docs/show-or-hide-elementor-sticky-header-with-display-conditions/',
			),
			'sticky_until'      => array(
				'label' => __( 'Sticky Until', 'she-header' ),
				'desc'  => __( 'Stop sticking at a container, element, or scroll distance.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/visionary-ai/',
				'docs'  => 'https://stickyheadereffects.com/docs/stop-elementor-sticky-header-at-a-specific-point-on-scroll/',
			),
			'reveal'            => array(
				'label' => __( 'Reveal Animation', 'she-header' ),
				'desc'  => __( '9 entrance effects when the header becomes sticky.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/cashiq/',
				'docs'  => 'https://stickyheadereffects.com/docs/add-reveal-animation-to-elementor-sticky-header/',
			),
			'header_replace'    => array(
				'label' => __( 'Header Replace on Scroll', 'she-header' ),
				'desc'  => __( 'Swap to a different header design when sticky.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/claude-ortiz/',
				'docs'  => 'https://stickyheadereffects.com/docs/replace-elementor-header-with-a-different-design-on-scroll/',
			),
			'logo_swap'         => array(
				'label' => __( 'Logo Image Swap', 'she-header' ),
				'desc'  => __( 'Show a different logo (with retina) when sticky.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/luminora/',
				'docs'  => 'https://stickyheadereffects.com/docs/swap-logo-image-in-elementor-sticky-header-on-scroll/',
			),
			'menu_style'        => array(
				'label' => __( 'Sticky Menu Styling', 'she-header' ),
				'desc'  => __( 'Restyle nav menus on sticky, 5 widget types.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/aurafurnish/',
				'docs'  => 'https://stickyheadereffects.com/docs/style-the-menu-in-elementor-sticky-header-on-scroll/',
			),
			'backdrop_filter'   => array(
				'label' => __( 'Extended Backdrop Filters', 'she-header' ),
				'desc'  => __( 'Grayscale, brightness, contrast & hue effects.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/zenflow/',
				'docs'  => 'https://stickyheadereffects.com/docs/add-grayscale-brightness-contrast-effects-to-elementor-sticky-header/',
			),
			'background_image'  => array(
				'label' => __( 'Background Image on Sticky', 'she-header' ),
				'desc'  => __( 'Set a background image for the sticky state.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/ecoflux/',
				'docs'  => 'https://stickyheadereffects.com/docs/add-a-background-image-to-elementor-sticky-header-on-scroll/',
			),
			'opacity'           => array(
				'label' => __( 'Opacity Transition', 'she-header' ),
				'desc'  => __( 'Smooth 0–1 opacity fade on the sticky header.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/flexify/',
				'docs'  => 'https://stickyheadereffects.com/docs/change-elementor-header-opacity-on-scroll/',
			),
			'logo_style'        => array(
				'label' => __( 'Logo Styling on Sticky', 'she-header' ),
				'desc'  => __( 'Frame, border & shadow styling for the logo.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demos/zenflow/',
				'docs'  => 'https://stickyheadereffects.com/docs/style-the-logo-in-elementor-sticky-header-on-scroll/',
			),
			'multi_sticky'      => array(
				'label' => __( 'Multi-Sticky Coordination', 'she-header' ),
				'desc'  => __( 'Stack or sequence up to 5 sticky sections.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/demo/multi-sticky/',
				'docs'  => 'https://stickyheadereffects.com/docs/add-multiple-sticky-sections-on-one-page-in-elementor/',
			),
			'progress_bar'      => array(
				'label' => __( 'Scroll Progress Bar', 'she-header' ),
				'desc'  => __( 'Reading progress bar that fills as visitors scroll.', 'she-header' ),
				'demo'  => 'https://stickyheadereffects.com/sticky-header-for-elementor-templates/',
				'docs'  => 'https://stickyheadereffects.com/docs/add-a-scroll-progress-bar-to-elementor-sticky-header/',
			),
		);

		$demo_label = __( 'Live Demo', 'she-header' );
		$docs_label = __( 'Read Docs', 'she-header' );

		// UTM appended to the live-site (stickyheadereffects.com) resource links.
		$utm = array(
			'utm_source'   => 'wpbackend',
			'utm_medium'   => 'elementoreditor',
			'utm_campaign' => 'stickyheaderproupsell',
		);

		foreach ( $pro_features as $slug => $feature ) {
			// Demo / Docs text links beneath the description (Elementor renders
			// control descriptions as raw HTML). "Live Demo" uses the theme text
			// color; "Read Docs" uses the brand accent.
			$links = '<span class="she-pro-links">'
				. '<a class="she-link-demo" href="' . esc_url( add_query_arg( $utm, $feature['demo'] ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $demo_label ) . '</a>'
				. '<span class="she-pro-links__sep">|</span>'
				. '<a class="she-link-docs" href="' . esc_url( add_query_arg( $utm, $feature['docs'] ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $docs_label ) . '</a>'
				. '</span>';

			$description = '<span class="she-pro-desc">' . esc_html( $feature['desc'] ) . '</span>' . $links;

			// "Extended Backdrop Filters" extends Free's Blur Background, so show
			// its locked teaser right after the blur controls — mirroring where the
			// real Pro control sits when Pro is active. Other teasers append below.
			$control_options = array();
			if ( 'backdrop_filter' === $slug ) {
				$control_options['position'] = array(
					'of' => 'blur_bg_saturate_amount',
					'at' => 'after',
				);
			}

			$element->add_control(
				'she_pro_lock_' . $slug,
				array(
					'label'        => $feature['label'],
					'description'  => $description,
					'type'         => Controls_Manager::SWITCHER,
					'label_on'     => __( 'On', 'she-header' ),
					'label_off'    => __( 'Off', 'she-header' ),
					'return_value' => 'yes',
					'default'      => '',
					'classes'      => 'she-pro-locked-ctrl',
					'render_type'  => 'none',
					'separator'    => 'before',
					'condition'    => array(
						'transparent!' => '',
					),
				),
				$control_options
			);
		}

		$element->add_control(
			'she_pro_upsell_html',
			array(
				'type'        => Controls_Manager::RAW_HTML,
				'raw'         => $this->build_upsell_html(),
				'label_block' => true,
				// Only show once Free's main "Enable" switcher is on.
				'condition'   => array(
					'transparent!' => '',
				),
			)
		);
	}

	/**
	 * Hook everything.
	 *
	 * @return void
	 */
	private function add_actions() {
		// If Pro is active, do not even register the hooks.
		if ( $this->is_pro_active() ) {
			return;
		}

		// Inject into Free's existing Sticky Header Effects panel section,
		// for both Section and Container element types.
		add_action(
			'elementor/element/section/section_sticky_header_effect/before_section_end',
			array( $this, 'register_controls' )
		);
		add_action(
			'elementor/element/container/section_sticky_header_effect/before_section_end',
			array( $this, 'register_controls' )
		);

		// Make the locked Pro feature rows clickable → pricing page.
		add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_upsell_click_script' ) );

		// Persist dismissal of the "Pro is now live" announcement.
		add_action( 'wp_ajax_' . self::LIVE_NOTICE_ACTION, array( $this, 'ajax_dismiss_live_notice' ) );

		// Editor-side click handler for the announcement's close button
		// (only needed while the notice can still appear).
		if ( ! $this->is_live_notice_dismissed() ) {
			add_action( 'elementor/editor/after_enqueue_scripts', array( $this, 'enqueue_dismiss_script' ) );
		}
	}
}
