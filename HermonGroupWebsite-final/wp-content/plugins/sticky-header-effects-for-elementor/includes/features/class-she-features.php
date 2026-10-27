<?php
/**
 * Feature manager — the single source of truth for the dashboard
 * "Features" tab (enable / disable Free sub-features and Pro modules).
 *
 * Stores the on/off state in the `she_feature_status` option and enforces it:
 *  - Pro modules: hooks `she_is_pro_feature_active` so a disabled Pro module is
 *    never instantiated (its controls and frontend assets both disappear).
 *  - Free sub-features: the transparent module removes the disabled controls
 *    (see Transparent\Module::she_gate_free_features()), which also stops the
 *    effect on the frontend once Elementor regenerates its CSS.
 *
 * @link    https://posimyth.com/
 * @since   2.2.2
 *
 * @package she-header
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'She_Features' ) ) {

	/**
	 * Class She_Features.
	 *
	 * @since 2.2.2
	 */
	class She_Features {

		/**
		 * Option name that stores the { slug => 'on' | 'off' } status map.
		 *
		 * @since 2.2.2
		 * @var string
		 */
		const OPTION = 'she_feature_status';

		/**
		 * Single instance.
		 *
		 * @since 2.2.2
		 * @var She_Features|null
		 */
		private static $instance = null;

		/**
		 * Get the single instance.
		 *
		 * @since 2.2.2
		 * @return She_Features
		 */
		public static function instance() {
			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Constructor. Registers the Pro-module gate.
		 *
		 * @since 2.2.2
		 */
		public function __construct() {
			// A disabled Pro feature reports is_active() === false, so the Pro
			// manager never instantiates it. The filter runs on both admin and
			// frontend because this class is loaded unconditionally.
			add_filter( 'she_is_pro_feature_active', array( $this, 'gate_pro_feature' ), 10, 2 );
		}

		/**
		 * Canonical feature list, grouped for the dashboard grid.
		 *
		 * `slug` for Free features is the Elementor control id in the
		 * transparent module; for Pro features it is the module slug used by
		 * the `she_is_pro_feature_active` filter.
		 *
		 * @since 2.2.2
		 * @return array
		 */
		public static function get_groups() {
			$demo = 'https://stickyheadereffects.com/demos/';
			$docs = 'https://stickyheadereffects.com/docs/';

			return array(
				array(
					'name'     => 'free',
					'label'    => __( 'Free Effects', 'she-header' ),
					'features' => array(
						array(
							'slug'  => 'transparent_header_show',
							'label' => __( 'Transparent Header', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'visionary-ai/',
							'docs'  => $docs . 'create-a-transparent-sticky-header-in-elementor/',
						),
						array(
							'slug'  => 'background_show',
							'label' => __( 'Background Color', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'inkspire/',
							'docs'  => $docs . 'change-elementor-sticky-header-background-color-on-scroll/',
						),
						array(
							'slug'  => 'shrink_header',
							'label' => __( 'Shrink Header', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'header-8/',
							'docs'  => $docs . 'how-to-shrink-elementor-sticky-header-on-scroll/',
						),
						array(
							'slug'  => 'shrink_header_logo',
							'label' => __( 'Shrink Logo', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'aurafurnish/',
							'docs'  => $docs . 'shrink-logo-in-elementor-sticky-header-on-scroll/',
						),
						array(
							'slug'  => 'change_logo_color',
							'label' => __( 'Change Logo Color', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'cashiq/',
							'docs'  => $docs . 'change-logo-color-in-elementor-sticky-header-on-scroll/',
						),
						array(
							'slug'  => 'blur_bg',
							'label' => __( 'Blur Background', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'zenflow/',
							'docs'  => $docs . 'blur-elementor-sticky-header-background-on-scroll/',
						),
						array(
							'slug'  => 'bottom_border',
							'label' => __( 'Bottom Border', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'luminora/',
							'docs'  => '',
						),
						array(
							'slug'  => 'bottom_shadow',
							'label' => __( 'Bottom Shadow', 'she-header' ),
							'tag'   => 'free',
							'demo'  => '',
							'docs'  => '',
						),
						array(
							'slug'  => 'hide_header',
							'label' => __( 'Hide on Scroll', 'she-header' ),
							'tag'   => 'free',
							'demo'  => $demo . 'ecoflux/',
							'docs'  => $docs . 'hide-elementor-sticky-header-on-scroll-down/',
						),
						array(
							'slug'  => 'mobile_menu_toggle_animation',
							'label' => __( 'Custom Menu Toggle', 'she-header' ),
							'tag'   => 'free',
							'demo'  => '',
							'docs'  => '',
						),
						array(
							'slug'  => 'she_totop_enable',
							'label' => __( 'Back to Top', 'she-header' ),
							'tag'   => 'free',
							'demo'  => '',
							'docs'  => $docs . 'add-a-back-to-top-button-to-elementor-sticky-header/',
						),
						array(
							'slug'  => 'she_anchor_offset',
							'label' => __( 'Fix Anchor Scroll', 'she-header' ),
							'tag'   => 'free',
							'demo'  => '',
							'docs'  => $docs . 'fix-anchor-link-scroll-offset-in-elementor-sticky-header/',
						),
					),
				),
				array(
					'name'     => 'pro',
					'label'    => __( 'Pro Effects', 'she-header' ),
					'features' => array(
						array(
							'slug'  => 'opacity',
							'label' => __( 'Header Opacity', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'flexify/',
							'docs'  => $docs . 'change-elementor-header-opacity-on-scroll/',
						),
						array(
							'slug'  => 'backdrop-filter-pro',
							'label' => __( 'Backdrop Filter', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'zenflow/',
							'docs'  => $docs . 'add-grayscale-brightness-contrast-effects-to-elementor-sticky-header/',
						),
						array(
							'slug'  => 'background-image',
							'label' => __( 'Background Image', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'ecoflux/',
							'docs'  => $docs . 'add-a-background-image-to-elementor-sticky-header-on-scroll/',
						),
						array(
							'slug'  => 'logo-swap',
							'label' => __( 'Logo Swap', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'luminora/',
							'docs'  => $docs . 'swap-logo-image-in-elementor-sticky-header-on-scroll/',
						),
						array(
							'slug'  => 'reveal',
							'label' => __( 'Reveal Animation', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'cashiq/',
							'docs'  => $docs . 'add-reveal-animation-to-elementor-sticky-header/',
						),
						array(
							'slug'  => 'logo-style',
							'label' => __( 'Logo Style', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'zenflow/',
							'docs'  => $docs . 'style-the-logo-in-elementor-sticky-header-on-scroll/',
						),
						array(
							'slug'  => 'menu-style',
							'label' => __( 'Menu Style', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'aurafurnish/',
							'docs'  => $docs . 'style-the-menu-in-elementor-sticky-header-on-scroll/',
						),
						array(
							'slug'  => 'sticky-until',
							'label' => __( 'Sticky Until', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'visionary-ai/',
							'docs'  => $docs . 'stop-elementor-sticky-header-at-a-specific-point-on-scroll/',
						),
						array(
							'slug'  => 'display-condition',
							'label' => __( 'Display Condition', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => 'https://stickyheadereffects.com/demo/display-conditions/',
							'docs'  => $docs . 'show-or-hide-elementor-sticky-header-with-display-conditions/',
						),
						array(
							'slug'  => 'header-replace',
							'label' => __( 'Header Replace', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => $demo . 'claude-ortiz/',
							'docs'  => $docs . 'replace-elementor-header-with-a-different-design-on-scroll/',
						),
						array(
							'slug'  => 'multi-sticky',
							'label' => __( 'Multi-Sticky', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => 'https://stickyheadereffects.com/demo/multi-sticky/',
							'docs'  => $docs . 'add-multiple-sticky-sections-on-one-page-in-elementor/',
						),
						array(
							'slug'  => 'progress-bar',
							'label' => __( 'Progress Bar', 'she-header' ),
							'tag'   => 'pro',
							'demo'  => '',
							'docs'  => '',
						),
					),
				),
			);
		}

		/**
		 * Flat list of every known feature slug.
		 *
		 * @since 2.2.2
		 * @return string[]
		 */
		public static function get_slugs() {
			$slugs = array();

			foreach ( self::get_groups() as $group ) {
				foreach ( $group['features'] as $feature ) {
					$slugs[] = $feature['slug'];
				}
			}

			return $slugs;
		}

		/**
		 * Saved status map merged with defaults (everything enabled by default).
		 *
		 * @since 2.2.2
		 * @return array { slug => 'on' | 'off' }
		 */
		public static function get_status() {
			$saved = get_option( self::OPTION, array() );

			if ( ! is_array( $saved ) ) {
				$saved = array();
			}

			$status = array();
			foreach ( self::get_slugs() as $slug ) {
				$status[ $slug ] = ( isset( $saved[ $slug ] ) && 'off' === $saved[ $slug ] ) ? 'off' : 'on';
			}

			return $status;
		}

		/**
		 * Whether a given feature is enabled. Unknown slugs default to enabled.
		 *
		 * @since 2.2.2
		 *
		 * @param string $slug Feature slug / control id.
		 * @return bool
		 */
		public static function is_enabled( $slug ) {
			$status = self::get_status();

			if ( ! isset( $status[ $slug ] ) ) {
				return true;
			}

			return 'on' === $status[ $slug ];
		}

		/**
		 * Persist a status map. Only known slugs are stored and values are
		 * clamped to 'on' | 'off'.
		 *
		 * @since 2.2.2
		 *
		 * @param array $map { slug => 'on' | 'off' } partial or full map.
		 * @return array The full, normalised status map after saving.
		 */
		public static function save_status( $map ) {
			$current = self::get_status();

			if ( is_array( $map ) ) {
				$known = self::get_slugs();
				foreach ( $map as $slug => $value ) {
					if ( in_array( $slug, $known, true ) ) {
						$current[ $slug ] = ( 'off' === $value ) ? 'off' : 'on';
					}
				}
			}

			update_option( self::OPTION, $current );

			return $current;
		}

		/**
		 * Gate Pro modules. Returns false when the Pro feature is disabled so
		 * the Pro manager skips instantiating it.
		 *
		 * @since 2.2.2
		 *
		 * @param bool   $active Current active state.
		 * @param string $slug   Pro module slug.
		 * @return bool
		 */
		public function gate_pro_feature( $active, $slug ) {
			if ( ! $active ) {
				return false;
			}

			return self::is_enabled( $slug );
		}
	}

	She_Features::instance();
}
