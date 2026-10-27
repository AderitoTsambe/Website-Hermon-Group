<?php
/**
 * POSIMYTH Analytics tracker — Sticky Header Effects for Elementor (SHE).
 *
 * Requires class-posimyth-tracker-base.php to be loaded first.
 * Boot from the main plugin file:  Posimyth_Tracker_SHE::init();
 *
 * Verified against Sticky Header Effects for Elementor 2.2.0 in this repo:
 *   - version constant : SHE_HEADER_VERSION
 *   - Pro marker       : SHE_PRO_VERSION (defined by the separate Pro plugin)
 *   - licence signal   : the `she_pro_is_licensed` filter Pro answers — the free build stores no
 *                        licence data of its own (see license())
 *   - onboarding       : she_onboarding_setup, written as 'hide' by
 *                        She_Dashboard_Ajax::she_onboarding_setup()
 *   - effect usage     : scanned from _elementor_data by she_tracked_effects() below — SHE has no
 *                        widgets and no global settings screen, so adoption can only be counted
 *                        from the Elementor controls the transparent module registers
 *
 * Consent is deliberately SHE's OWN — posimyth_she_share_analytics, under suite_key `she_suite`.
 * Nexter Extension and Nexter Blocks share one answer between them because they are one brand with
 * one dashboard; Sticky Header Effects is a separate product with its own dashboard and its own
 * audience, so it asks and stores separately — the same reasoning The Plus Addons for Elementor
 * follows. See the OPT_IN_OPTION note below.
 *
 * @package POSIMYTH\Analytics\SDK
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Self-load the shared base so this subclass defines correctly regardless of require order.
if ( ! class_exists( 'Posimyth_Tracker_Base' ) ) {
	require_once __DIR__ . '/class-posimyth-tracker-base.php';
}

if ( ! class_exists( 'Posimyth_Tracker_SHE' ) && class_exists( 'Posimyth_Tracker_Base' ) ) {

	/**
	 * SHE's tracker: supplies the product-specific identity, version, Pro state and effect usage
	 * that the shared base assembles into a payload.
	 */
	class Posimyth_Tracker_SHE extends Posimyth_Tracker_Base {

		/**
		 * SHE's OWN consent option — deliberately NOT the one Nexter Extension and Nexter Blocks share,
		 * and not The Plus Addons for Elementor's either.
		 *
		 * Nexter is a single brand: Extension and Blocks are two halves of one product with one
		 * dashboard, so one answer covering both is what a user expects. Sticky Header Effects is a
		 * separate product on its own site (stickyheadereffects.com) with its own dashboard, so folding
		 * it into either of those answers would mean consenting for a plugin the user never had in mind.
		 * It keeps its own key and its own suite_key, which also gives it its own notice and its own
		 * Allow/Dismiss.
		 *
		 * Do NOT point this at posimyth_nexter_share_analytics or posimyth_tpae_share_analytics.
		 */
		const OPT_IN_OPTION = 'posimyth_she_share_analytics';

		/**
		 * Short internal id used for this product's own options (install time, usage cache).
		 *
		 * @return string
		 */
		protected static function id(): string {
			return 'she';
		}

		/**
		 * Plugin slug reported to the hub.
		 *
		 * Must match an entry in the hub's PLUGIN_SLUGS allowlist, or every ping is rejected.
		 *
		 * @return string
		 */
		protected static function slug(): string {
			return 'sticky-header-effects-for-elementor';
		}

		/**
		 * Name shown in WordPress's Privacy Policy suggestions and in the consent notice.
		 *
		 * @return string
		 */
		protected static function display_name(): string {
			return 'Sticky Header Effects for Elementor';
		}

		/**
		 * Option holding SHE's own sharing consent — see OPT_IN_OPTION.
		 *
		 * @return string
		 */
		protected static function opt_in_option(): string {
			return self::OPT_IN_OPTION;
		}

		/**
		 * Currently installed version of this plugin.
		 *
		 * @return string
		 */
		protected static function version(): string {
			return defined( 'SHE_HEADER_VERSION' ) ? SHE_HEADER_VERSION : '';
		}

		/**
		 * Whether the Pro build is active.
		 *
		 * Sticky Header Effects Pro is a separate plugin that defines SHE_PRO_VERSION at load time —
		 * the same marker modules/pro-upsell/module.php already uses to decide whether to show upsells.
		 *
		 * Deliberately NOT the `she_pro_is_licensed` filter: that answers whether the licence is
		 * currently valid, which is a different question and would report an expired Pro install as a
		 * Free one, hiding exactly the cohort that needs chasing. Licence state travels separately, in
		 * license() below.
		 *
		 * @return bool
		 */
		protected static function is_pro(): bool {
			return defined( 'SHE_PRO_VERSION' );
		}

		/**
		 * Licence status/plan for the Pro build.
		 *
		 * The free build stores no licence data of its own, so there is no option to read here — the
		 * only signal available is the `she_pro_is_licensed` filter that Pro answers, which
		 * includes/dashboard/class-she-wp-menu.php already relies on for the dashboard's `shed_pro`
		 * flag. A licensed install reports 'valid'; an installed-but-unlicensed or expired one reports
		 * 'invalid', which is distinguishable from the empty string a Free install sends.
		 *
		 * No licence key is ever read or sent, and nothing is invented: with Pro absent this is empty,
		 * not 'free'.
		 *
		 * @return array{status:string, plan:string}
		 */
		protected static function license(): array {
			if ( ! static::is_pro() ) {
				return array(
					'status' => '',
					'plan'   => '',
				);
			}

			return array(
				'status' => apply_filters( 'she_pro_is_licensed', false ) ? 'valid' : 'invalid',
				// SHE Pro is a single tier and exposes nothing that names a plan, so this stays empty
				// rather than inventing one.
				'plan'   => '',
			);
		}

		/**
		 * Whether the dashboard's setup step has been finished on this site.
		 *
		 * she_onboarding_setup is written as 'hide' by She_Dashboard_Ajax::she_onboarding_setup(). Read
		 * as truthy rather than compared to 'hide', so a future value change in that handler does not
		 * silently reset every site's onboarding figure to pending.
		 *
		 * @return string 'completed' or 'pending'.
		 */
		protected static function onboarding_status(): string {
			return get_option( 'she_onboarding_setup' ) ? 'completed' : 'pending';
		}

		/**
		 * Reports the real first-seen date instead of the date the SDK happened to be added.
		 *
		 * The base writes its own `posimyth_she_install_time` the first time it needs one, which on a
		 * site that has run this plugin for two years would have claimed the install was minutes old and
		 * then kept reporting that forever — install age is one of the few figures the hub cannot
		 * reconstruct later. This plugin has recorded its own install date in `she_header_install_time`
		 * since 1.7.3 (She_Loader::she_load()), so seed from that when it exists.
		 *
		 * The legacy value is written with current_time( 'mysql' ) — site-local time — while the SDK's
		 * field is UTC, hence the conversion. A malformed or empty legacy value falls through to the
		 * base's behaviour rather than reporting a bogus date.
		 *
		 * This is an override, not a second option: the base reads `posimyth_she_install_time` directly
		 * in build_payload(), so the value has to end up under that key.
		 *
		 * @return void
		 */
		protected static function record_install_time(): void {
			$key = 'posimyth_' . static::id() . '_install_time';
			if ( get_option( $key ) ) {
				return;
			}

			$legacy = get_option( 'she_header_install_time', '' );
			if ( is_string( $legacy ) && '' !== $legacy ) {
				$seeded = get_gmt_from_date( $legacy, 'Y-m-d H:i:s' );

				/*
				 * get_gmt_from_date() does not fail loudly: an unparseable string returns
				 * gmdate( $format, 0 ) — 1970-01-01 — so checking for an empty return would let a
				 * garbage legacy value through as a 56-year-old install. Anything at or before the
				 * epoch fallback is treated as no value and falls through to the base.
				 */
				if ( ! empty( $seeded ) && $seeded > '1970-01-02' ) {
					add_option( $key, $seeded, '', false );
					return;
				}
			}

			parent::record_install_time();
		}

		/**
		 * Page builder in use — corrected for a product that only works with Elementor.
		 *
		 * The shared detector scans the active-plugin folders for a known builder and, finding none,
		 * returns 'gutenberg'. For most products that is a reasonable default. For this one it is a
		 * false statement twice over:
		 *
		 *  1. It reported 'gutenberg' on installs where Elementor was simply not active. This plugin
		 *     does nothing at all without Elementor — she_header_load_plugin() returns early and shows
		 *     the "activate Elementor" notice — so those installs are not Gutenberg users, they are
		 *     installs where the plugin is inert. Filing them under a builder they are not using hid
		 *     the one number worth knowing: how many people installed this and never got it working.
		 *     They now report 'elementor-inactive', which is its own bucket on the hub and reads as
		 *     what it is.
		 *
		 *  2. It missed Elementor whenever the folder was not literally `elementor` — a renamed
		 *     directory, or a build installed from a GitHub zip as `elementor-master`. The constant is
		 *     the authoritative signal, so it is checked first and the folder scan is only a fallback.
		 *
		 * Another builder genuinely being active still wins over 'elementor-inactive': a site running
		 * Bricks or Divi with Elementor switched off is migrating, and that is worth seeing. Only the
		 * bare 'gutenberg' fallback is replaced.
		 *
		 * @param array $plugins Active plugin files, network-activated ones already merged in.
		 * @return string
		 */
		protected static function detect_page_builder( array $plugins ): string {
			// Authoritative: Elementor defines this at load, whatever its folder is called.
			if ( defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ) ) {
				return 'elementor';
			}

			$she_detected = parent::detect_page_builder( $plugins );

			// Elementor absent and no other builder found — say so, rather than claiming Gutenberg.
			if ( 'gutenberg' === $she_detected ) {
				return 'elementor-inactive';
			}

			return $she_detected;
		}

		/**
		 * Adds the Elementor build this install is running to the payload.
		 *
		 * `page_builder` answers "which builder", which for this product is nearly always the same
		 * answer and so carries almost no information. The version does: this is an Elementor-only
		 * addon, Elementor ships breaking editor changes regularly, and a conflict report that does
		 * not name the Elementor version cannot be reproduced. The same goes for Elementor Pro, whose
		 * own header/theme-builder features are what these effects most often collide with.
		 *
		 * It travels in `plugin_meta`, which the hub accepts as arbitrary per-product JSON (capped at
		 * 64 KB, see posimyth_analytics_plugin_meta_max_bytes). A new TOP-LEVEL key would have been
		 * dropped in silence — the ingest handler only reads the fields it knows.
		 *
		 * A thin override on purpose: the parent assembles the whole payload and this only appends to
		 * it, so none of that logic is duplicated here. Extras passed to do_request() are merged over
		 * the result afterwards and are unaffected.
		 *
		 * @param string $event One of activate|deactivate|heartbeat.
		 * @return array
		 */
		public static function build_payload( string $event ): array {
			$she_payload = parent::build_payload( $event );

			$she_payload['plugin_meta'] = array(
				'elementor_active'      => (int) ( defined( 'ELEMENTOR_VERSION' ) || did_action( 'elementor/loaded' ) ),
				'elementor_version'     => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
				'elementor_pro_version' => defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : '',
			);

			return $she_payload;
		}

		/**
		 * Which SHE features are ENABLED in settings — deliberately none.
		 *
		 * This is not an oversight. Sticky Header Effects has no site-wide feature switches at all:
		 * every effect is an Elementor control on the individual section/container (see
		 * modules/transparent/module.php), so "enabled" and "used" are the same fact for this product.
		 * Reporting that fact twice — once here as booleans and once in widget_usage as counts — would
		 * put the same information in two columns of the hub and give two places for it to disagree.
		 *
		 * So adoption travels through used_features() only. `enabled_widgets` stays empty, which is the
		 * mirror image of Nexter Extension, where extensions are toggle-only and `widget_usage` is the
		 * empty one. The hub already treats the two keys independently.
		 *
		 * @return array<string,bool>
		 */
		protected static function enabled_features(): array {
			return array();
		}

		/**
		 * The effect controls whose usage is counted.
		 *
		 * These are the switcher ids the transparent module registers, all of which return 'yes'.
		 * `transparent` is the master switch every other control is conditioned on, so it doubles as
		 * the "SHE is actually in use on this element" marker.
		 *
		 * Filterable so Sticky Header Effects Pro can add its own effects without this file needing to
		 * know about them. The returned list is sanitised in she_scan_effect_usage() — a filter cannot
		 * inject regex metacharacters into the scan.
		 *
		 * @return array<int,string>
		 */
		protected static function she_tracked_effects(): array {
			$she_effects = array(
				'transparent',
				'transparent_header_show',
				'background_show',
				'mobile_menu_toggle_animation',
				'bottom_border',
				'bottom_shadow',
				'shrink_header',
				'shrink_header_logo',
				'change_logo_color',
				'blur_bg',
				'hide_header',
			);

			/**
			 * Filters the SHE effect control ids reported as feature usage.
			 *
			 * @param array<int,string> $she_effects Control ids, each a switcher returning 'yes'.
			 */
			return (array) apply_filters( 'she_posimyth_tracked_effects', $she_effects );
		}

		/**
		 * Real effect usage counted from Elementor content.
		 *
		 * The base's scan_elementor_widgets() cannot be reused: it matches `"widgetType":"tp-…"`, and
		 * SHE ships no widgets — its features are settings written onto whatever section or container
		 * the user applied them to. So this scans for the control keys themselves.
		 *
		 * Bounded exactly like the base's scanners, for the same reasons: capped by the shared
		 * `posimyth_scan_post_cap` filter (default 2000 rows), 100 rows per batch because these are
		 * longtext blobs, and keyset pagination (meta_id > last, ORDER BY meta_id) rather than
		 * LIMIT/OFFSET, which with no defined order can repeat or skip rows between batches. The base
		 * only ever calls this on the weekly cron; activate / deactivate read the cached copy, so the
		 * admin never waits on it.
		 *
		 * @return array<string,int>
		 */
		protected static function used_features(): array {
			global $wpdb;

			// Sanitised here rather than trusted from the filter, so a third party cannot inject regex
			// metacharacters — or an empty list — into the pattern below.
			$she_effects = array();
			foreach ( static::she_tracked_effects() as $she_effect ) {
				if ( is_string( $she_effect ) && preg_match( '/^[a-z0-9_-]+$/', $she_effect ) ) {
					$she_effects[] = $she_effect;
				}
			}
			$she_effects = array_values( array_unique( $she_effects ) );

			if ( empty( $she_effects ) ) {
				return array();
			}

			$she_counts  = array();
			$she_cap     = (int) apply_filters( 'posimyth_scan_post_cap', 2000 );
			$she_batch   = 100;
			$she_last_id = 0;
			$she_scanned = 0;

			/*
			 * Row filter on the master switch, not on each effect in turn.
			 *
			 * Every effect control is registered with `'condition' => array( 'transparent!' => '' )`, so
			 * an element without `"transparent":"yes"` cannot have an active effect — its stale keys
			 * would still be sitting in the JSON from before the user switched SHE off, and counting
			 * those would report effects on elements where nothing renders. One LIKE also keeps this to
			 * a single indexable query instead of one per effect.
			 */
			$she_like  = '%' . $wpdb->esc_like( '"transparent":"yes"' ) . '%';
			$she_regex = '/"(' . implode( '|', array_map( static function ( $she_effect ) {
				return preg_quote( $she_effect, '/' );
			}, $she_effects ) ) . ')":"yes"/';

			while ( $she_scanned < $she_cap ) {
				// Clamp the batch to what the cap still allows, so `posimyth_scan_post_cap` is an exact
				// bound rather than a between-batches check that could overshoot by a whole batch.
				$she_take = min( $she_batch, $she_cap - $she_scanned );

				// Batched scan of Elementor's own meta blob; no core API can query inside it. Runs only on
				// the weekly cron and the result is cached in an option, so per-query caching would add a
				// second cache layer for a job that runs at most once a week.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$she_rows = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT meta_id, meta_value FROM {$wpdb->postmeta}
						 WHERE meta_key = '_elementor_data' AND meta_value LIKE %s AND meta_id > %d
						 ORDER BY meta_id ASC LIMIT %d",
						$she_like,
						$she_last_id,
						$she_take
					),
					ARRAY_A
				);

				if ( empty( $she_rows ) ) {
					break;
				}

				foreach ( $she_rows as $she_row ) {
					$she_last_id = (int) $she_row['meta_id'];
					if ( preg_match_all( $she_regex, $she_row['meta_value'], $she_matches ) ) {
						foreach ( $she_matches[1] as $she_key ) {
							$she_counts[ $she_key ] = ( $she_counts[ $she_key ] ?? 0 ) + 1;
						}
					}
				}

				$she_scanned += count( $she_rows );
				if ( count( $she_rows ) < $she_take ) {
					break;
				}
			}

			return $she_counts;
		}
	}
}
