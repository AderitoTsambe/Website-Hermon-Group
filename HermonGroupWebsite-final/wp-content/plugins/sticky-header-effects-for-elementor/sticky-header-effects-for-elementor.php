<?php
/**
 * Plugin Name:			Sticky Header Effects for Elementor
 * Plugin URI:			https://stickyheadereffects.com
 * Description:			Create stunning sticky headers with multiple scroll effects like shrink, fade, slide, and blur. Packed with 50+ ready-to-import templates and fully customizable using Elementor.
 * Version:				2.2.3
 * Author:				POSIMYTH
 * Author URI:			https://posimyth.com/
 * Requires at least:	6.3
 * Tested up to:		7.1
 * Requires PHP:		7.4
 * Elementor tested up to:		4.2
 * Elementor Pro tested up to:	4.1
 * License:				GPLv3
 * License URI:			https://opensource.org/licenses/GPL-3.0
 *
 * Text Domain: she-header
 * Domain Path: /languages/
 *
 * @package sticky-header-effects-for-elementor
 * @category Core
 * @author POSIMYTH
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

define( 'SHE_HEADER_VERSION', '2.2.3' );
define( 'SHE_HEADER_PREVIOUS_STABLE_VERSION', '2.2.2' );

define( 'SHE_HEADER__FILE__', __FILE__ );
define( 'SHE_HEADER_PLUGIN_BASE', plugin_basename( SHE_HEADER__FILE__ ) );
define( 'SHE_HEADER_PATH', plugin_dir_path( SHE_HEADER__FILE__ ) );
define( 'SHE_HEADER_MODULES_PATH', SHE_HEADER_PATH . 'modules/' );
define( 'SHE_HEADER_URL', plugins_url( '/', SHE_HEADER__FILE__ ) );
define( 'SHE_HEADER_ASSETS_URL', SHE_HEADER_URL . 'assets/' );
define( 'SHE_HEADER_MODULES_URL', SHE_HEADER_URL . 'modules/' );
define( 'SHE_WDKIT_URL', 'https://wdesignkit.com/' );
define( 'SHE_MENU_NOTIFICETIONS', '2' );
define( 'SHE_PBNAME', plugin_basename( __FILE__ ) );
define( 'SHE_HEADER_DOC_URL', 'https://stickyheadereffects.com/docs/' );

/*
 * POSIMYTH Analytics SDK — registered, not required.
 *
 * Direct requires behind class_exists guards would mean whichever POSIMYTH plugin loaded FIRST
 * supplied the shared classes to every sibling, so an outdated plugin could silently downgrade the
 * whole suite. Each plugin registers its bundled copy instead, and the loader requires the NEWEST one
 * at plugins_loaded priority 0. The SHE subclass loads inside the consumer callback below, after the
 * winning copy exists.
 *
 * Registered here at the top of the file rather than inside she_header_load_plugin(), which returns
 * early when Elementor is missing or too old. Reporting must not depend on Elementor: an install
 * sitting there with Elementor deactivated is exactly the install support wants to see in the hub.
 *
 * The shared files are The Plus Addons for Elementor's copy with the text domain changed to
 * she-header and one addition (see includes/posimyth-sdk/version.php). Keep the bundled version.php
 * ahead of the siblings' or an older copy wins the loader.
 */
require_once SHE_HEADER_PATH . 'includes/posimyth-sdk/posimyth-sdk-loader.php';
posimyth_sdk_register( SHE_HEADER_PATH . 'includes/posimyth-sdk' );

/**
 * White label: a rebranded install must never surface POSIMYTH-branded UI AND must never phone
 * api.posimyth.com.
 *
 * Testable BEFORE the tracker boots — that ordering is the whole point. In an earlier sibling
 * integration this check sat below the tracker's init(), which hid the UI but left the activate /
 * deactivate / heartbeat pings running on a rebranded site whose consent was already on.
 *
 * The free build ships no white-label screen, so on a plain install this always returns false and
 * costs one option read. It exists because Sticky Header Effects Pro can rebrand, and because the
 * gate has to be in place before that arrives rather than retrofitted afterwards. Two contracts:
 *
 *  - `she_white_label` — an array written by Pro's white-label screen. Only the REBRANDING fields
 *    count. That distinction matters: The Plus Addons for Elementor stores plain UI preferences
 *    ("hide help links", "hide news") in the same option as its rebranding fields, and treating any
 *    non-empty value as rebranding meant a user who merely hid help links silently lost the tracker,
 *    the consent notice and the deactivation survey with nothing to indicate why. Add a new
 *    rebranding field to that screen and it must be added here too.
 *  - `she_posimyth_white_label` — a filter, for a Pro build that stores its rebranding somewhere
 *    other than that option. Returning true suppresses everything below.
 *
 * No Pro-constant gate: the stored values are the evidence and they outlive Pro, so deactivating Pro
 * on a rebranded install must not bring POSIMYTH branding back or resume the pings.
 *
 * @since 2.2.1
 *
 * @return bool
 */
function she_posimyth_is_white_labelled() {
	$she_wl = get_option( 'she_white_label' );

	if ( ! empty( $she_wl ) && is_array( $she_wl ) ) {
		$she_wl_brand_keys = array(
			'she_plugin_name',
			'she_plugin_desc',
			'she_author_name',
			'she_author_uri',
			'she_plugin_logo',
		);

		foreach ( $she_wl_brand_keys as $she_wl_key ) {
			if ( ! isset( $she_wl[ $she_wl_key ] ) || ! is_scalar( $she_wl[ $she_wl_key ] ) ) {
				continue;
			}
			if ( '' !== trim( (string) $she_wl[ $she_wl_key ] ) ) {
				return true;
			}
		}
	}

	/**
	 * Filters whether this install is white labelled, and so must not phone home or show
	 * POSIMYTH-branded UI.
	 *
	 * Only reached when the `she_white_label` option has NOT already answered yes — that case returns
	 * above. So this filter can declare an install rebranded; it cannot un-declare one that the stored
	 * rebranding fields already prove, which would let a filter switch the pings back on behind a
	 * rebranded site's back.
	 *
	 * @since 2.2.1
	 *
	 * @param bool $she_is_white_labelled Always false at this point; return true to suppress.
	 */
	return (bool) apply_filters( 'she_posimyth_white_label', false );
}

/**
 * Boots the analytics tracker, the consent notice and the deactivation survey.
 *
 * Its own plugins_loaded callback, separate from she_header_load_plugin(): that one bails out when
 * Elementor is absent or outdated, and the three surfaces here have nothing to do with Elementor.
 *
 * @since 2.2.1
 *
 * @return void
 */
function she_posimyth_analytics_boot() {
	// Nothing below may run on a rebranded install — tracker included.
	if ( she_posimyth_is_white_labelled() ) {
		return;
	}

	// Shared base already loaded by the SDK loader at priority 0; the subclass is ours alone.
	require_once SHE_HEADER_PATH . 'includes/posimyth-sdk/class-posimyth-tracker-she.php';
	if ( ! class_exists( 'Posimyth_Tracker_SHE' ) ) {
		return;
	}

	// Registers activate / deactivate / weekly-heartbeat hooks + cron (all consent-gated).
	Posimyth_Tracker_SHE::init();

	if ( ! is_admin() ) {
		return;
	}

	/*
	 * Consent notice — on SHE's OWN key and its OWN suite.
	 *
	 * Nexter Extension and Nexter Blocks share one answer because they are one brand with one
	 * dashboard. Sticky Header Effects is a separate product, so it asks separately and stores
	 * separately. A site running it next to a Nexter product or The Plus Addons for Elementor will see
	 * one notice per product — that is the intent, not a bug: consenting to share one product's data is
	 * not consenting to share another's.
	 *
	 * suite_key `she_suite` gives it its own answer flag, its own Dismiss snooze and its own
	 * post-install quiet period. The SDK's contract check requires everyone under one suite_key to pass
	 * the same opt_in_option — SHE is the only member of this one, so that holds.
	 *
	 * Guarded on class_exists, like the tracker above. The SDK loader requires the three shared files
	 * as a set, gated on Posimyth_Tracker_Base alone — and the SHE subclass self-requires that base. So
	 * any path that defines the base without the loader having run the full set (a sibling requiring it
	 * directly, or an older SDK revision) leaves these two classes undefined, and an unguarded `new`
	 * would fatal on every admin page load.
	 */
	if ( class_exists( 'Posimyth_Consent_Notice' ) ) {
		new Posimyth_Consent_Notice(
			array(
				'plugin_name'      => 'Sticky Header Effects for Elementor',
				'plugin_slug'      => 'sticky-header-effects-for-elementor',
				'opt_in_option'    => 'posimyth_she_share_analytics',
				'ajax_action'      => 'posimyth_consent_she',
				'installed_option' => 'posimyth_she_first_use_at',
				'tracker_cb'       => array( 'Posimyth_Tracker_SHE', 'send_first_ping' ),
				// The SDK's suite_name default is 'Nexter', and it is what the notice prints instead of
				// plugin_name once more than one product registers. Unset, a site running this plugin next
				// to a Nexter product showed SHE's own notice as "Help make Nexter faster and more stable"
				// while storing consent under posimyth_she_share_analytics. SHE is its own suite of one, so
				// its suite name is simply its own name.
				'suite_name'       => 'Sticky Header Effects for Elementor',
				/*
				 * SHE's own docs, not nexterwp.com — a Nexter link here would send these users to the
				 * wrong product's documentation.
				 *
				 * The campaign is built here rather than into SHE_HEADER_DOC_URL because the constant is
				 * also handed to the dashboard as `shed_docs_url`, and settings.jsx appends its own
				 * `?utm_source=…&utm_medium=dashboard&utm_campaign=datasharing`. A query string on the
				 * constant would give that link two "?" in one URL. Same reason the two surfaces carry
				 * different utm_medium values: this one is the admin notice, that one the dashboard.
				 *
				 * utm_medium/utm_campaign match The Plus Addons' notice exactly, so the two products'
				 * data-sharing traffic lands in one comparable bucket.
				 *
				 * Still the docs root, not `data-sharing/`: that page returned 404 when last checked on
				 * 2026-08-12. Insert `data-sharing/` before the "?" once it is published — the index at
				 * least loads, whereas the specific page would be a dead link today.
				 */
				'docs_url'         => SHE_HEADER_DOC_URL . 'data-sharing/?utm_source=wpbackend&utm_medium=admin&utm_campaign=datasharingnotice',
				'suite_key'        => 'she_suite',
				/*
				 * This product's own class prefix and accent, so the notice stops shipping a sibling's
				 * `nxt-*` classes and Nexter's blue into this plugin's admin markup.
				 *
				 * Both keys are read by the bundled consent notice; both default to the Nexter values, so
				 * a sibling that passes nothing renders byte-identically to before. NOTE that the 2.15.0
				 * re-sync dropped `css_prefix` and the notice reverted to `nxt-*` in silence — an
				 * unrecognised key does nothing and nothing errors. After any SDK sync, confirm both keys
				 * still exist in class-posimyth-consent-notice.php.
				 */
				'css_prefix'       => 'she',
				'accent'           => '#9D1A4F',
			)
		);
	}

	/*
	 * "Why are you leaving?" survey. Submitting with a reason is itself the consent for that one
	 * submission; Skip sends nothing. Its own ajax action and slug, so the hub records this churn
	 * against SHE rather than a sibling.
	 *
	 * The config — identity, logo, accent and the eight reason cards with their inline icons — lives in
	 * She_Deactivate_Survey::args(), in includes/user-experience/ — outside includes/posimyth-sdk/ on
	 * purpose, for two reasons both of which were demonstrated rather than assumed.
	 *
	 * The loader keeps ONE copy of the shared files across every active POSIMYTH plugin, the highest
	 * posimyth-sdk/version.php winning, and skips the rest. A config appended to the shared survey file
	 * therefore loaded only while this plugin held the highest version — and The Plus Addons shipped
	 * 2.16.0 against this plugin's 2.15.0, so the block stopped being included at all and the dialog
	 * quietly dropped to the SDK's generic seven reasons with no logo. That directory is also a synced
	 * copy that gets replaced wholesale, which is how the consent notice lost its `css_prefix` key.
	 * Here the config loads unconditionally and survives both. The sibling product keeps its own
	 * equivalent outside that directory for the same reasons.
	 *
	 * This is the ONLY thing bound to the Deactivate link. The legacy dialog
	 * (includes/notices/class-she-deactivate-feedback.php, posting to the she/v2 endpoint) was deleted —
	 * two handlers on that one link stack two dialogs, which is the bug Nexter Extension and The Plus
	 * Addons for Elementor both shipped until their legacy popups were deleted. Do not reintroduce a
	 * second one.
	 */
	if ( class_exists( 'Posimyth_Deactivation_Survey' ) ) {
		/*
		 * Required here rather than at the top of the file: the eight reason icons are inline SVG and run
		 * to roughly 11 KB, and this block already sits behind the is_admin() gate above, so a front-end
		 * request never compiles any of it.
		 */
		require_once SHE_HEADER_PATH . 'includes/user-experience/class-she-deactivate-survey.php';

		new Posimyth_Deactivation_Survey( She_Deactivate_Survey::args() );
	}

	add_action( 'admin_enqueue_scripts', 'she_posimyth_brand_styles', 11 );
}
add_action( 'plugins_loaded', 'she_posimyth_analytics_boot' );

/**
 * Repaints the SDK's consent notice and deactivation dialog in this plugin's own colour.
 *
 * NOT the primary mechanism. Both surfaces now take their accent as the `--posi-accent` custom property
 * set inline per instance from the `accent` config key, and this plugin passes #9D1A4F to each, so both
 * paint themselves correctly before this function runs. What is left here is a safety net plus the
 * handful of dialog details the survey exposes no key for.
 *
 * Worth keeping because that config key has gone missing once already: the re-sync to SDK 2.15.0 dropped
 * `css_prefix` outright and the notice reverted to a sibling's prefix and blue accent in total silence —
 * an unrecognised config key does nothing and nothing errors. These rules are anchored to OUR notice id
 * and OUR dialog id, so an id outranks the SDK's class-only rules and no sibling product's notice on the
 * same screen is affected either way. That isolation matters more since SDK 2.19.0, because the SDK now
 * emits ONE stylesheet for every active product and only the inline property varies.
 *
 * The selectors name only `posi-*` classes, which every product emits identically and no config key can
 * change, so a future sync cannot silently stop them matching the way the old `she-*` selectors could.
 * If `accent` is ever dropped again, the SDK falls back to its neutral and these rules still repaint
 * this plugin's notice. Check this function whenever includes/posimyth-sdk/ is re-synced.
 *
 * Priority 11: the SDK attaches its own inline CSS at the default 10 on the same `wp-admin` handle,
 * and inline styles are concatenated in attach order, so these rules come after it and win at equal
 * specificity without needing !important.
 *
 * @since 2.2.1
 *
 * @return void
 */
function she_posimyth_brand_styles() {
	/*
	 * Only where one of the two surfaces can actually appear, so this is not ~600 bytes of dead CSS on
	 * every admin page. The dialog lives on plugins.php; the notice can appear anywhere, but never
	 * once the consent question has been answered. Both reads are autoloaded options.
	 */
	$she_screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	$she_on_plugins = ( $she_screen && isset( $she_screen->id ) && in_array( $she_screen->id, array( 'plugins', 'plugins-network' ), true ) );

	if ( ! $she_on_plugins && get_site_option( 'posi_consent_dismissed_she_suite' ) ) {
		return;
	}

	$she_brand = '#9d1a4f';

	$she_css = '
		/* Consent notice — the accent for this product, not the SDK default.
		   The bundled notice now delivers the accent as the --posi-accent custom property set inline
		   per instance, so it paints itself correctly and these rules are belt and braces rather than
		   the primary mechanism. Setting the property on OUR notice id is the whole repaint: every
		   coloured declaration in the SDK stylesheet reads it, so one line covers the border, both
		   buttons and their hover/focus states.
		   Anchored to OUR id, so no sibling notice is touched — which matters more than it used to,
		   because one stylesheet now serves every product.
		   The class-keyed rules this block used to carry are gone: they named the she-* classes, and
		   those are a legacy host-stylesheet hook that nothing styles any more. */
		#posi-consent-sticky-header-effects-for-elementor { --posi-accent: ' . $she_brand . '; }
		#posi-consent-sticky-header-effects-for-elementor .posi-notice-icon svg path { stroke: ' . $she_brand . '; }
		#posi-consent-sticky-header-effects-for-elementor .posi-consent-allow,
		#posi-consent-sticky-header-effects-for-elementor .posi-consent-allow:hover,
		#posi-consent-sticky-header-effects-for-elementor .posi-consent-allow:focus {
			background-color: ' . $she_brand . '; border-color: ' . $she_brand . ';
		}
		#posi-consent-sticky-header-effects-for-elementor .posi-consent-skip,
		#posi-consent-sticky-header-effects-for-elementor .posi-consent-skip:hover,
		#posi-consent-sticky-header-effects-for-elementor .posi-consent-skip:focus {
			color: ' . $she_brand . '; border-color: ' . $she_brand . ';
		}
		#posi-consent-sticky-header-effects-for-elementor .posi-notice-text a { color: ' . $she_brand . '; }

		/* Deactivation dialog — the same accent, applied from here rather than through config.
		   The bundled survey takes an `accent` key, but that only reaches the dialog while THIS
		   copy of the SDK in this plugin wins the loader. A sibling shipping a higher posimyth-sdk/version.php
		   takes over, and that copy hardcodes one colour with no accent key, so the key is ignored
		   and the dialog renders in the brand of that product. These rules do not care which copy renders.
		   Every one is anchored to OUR modal id, so a sibling dialog on the same screen keeps its
		   colour. The tints use color-mix() the same way the SDK does, so only the hue differs. */
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-dialog { border-top-color: ' . $she_brand . '; }
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-submit {
			background: ' . $she_brand . '; border-color: ' . $she_brand . ';
		}
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-skip:hover { color: ' . $she_brand . '; }
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-opt:hover { border-color: ' . $she_brand . '; }
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-cards label:hover { border-color: ' . $she_brand . '; }
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-cards label:has(input:checked) {
			border-color: ' . $she_brand . ';
			background: color-mix(in srgb, ' . $she_brand . ' 8%, #fff);
		}
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-cards label:has(input:focus-visible) {
			outline-color: ' . $she_brand . ';
		}
		#posi-deact-sticky-header-effects-for-elementor .posi-deact-reasons label:hover {
			background: color-mix(in srgb, ' . $she_brand . ' 8%, #fff);
		}
	';

	wp_add_inline_style( 'wp-admin', $she_css );
}

/**
 * Analytics activation ping.
 *
 * Must run from the activation hook, NOT the SDK's `activated_plugin` hook: during a plugin's own
 * activation request WordPress fires `plugins_loaded` before it includes this file, so
 * she_posimyth_analytics_boot() never ran and nothing is listening when `activated_plugin` fires.
 * Consent-gated inside on_self_activate(), so a fresh install still sends nothing.
 */
register_activation_hook(
	SHE_HEADER__FILE__,
	function () {
		// Rebranded installs must not phone home from here either — this runs during our own activation
		// request, before the plugins_loaded gate has had a chance to short-circuit.
		if ( she_posimyth_is_white_labelled() ) {
			return;
		}
		// she_posimyth_analytics_boot() never ran in this request, so load the subclass here; the loader
		// already loaded the shared base immediately (its did_action branch) on include.
		require_once SHE_HEADER_PATH . 'includes/posimyth-sdk/class-posimyth-tracker-she.php';
		if ( class_exists( 'Posimyth_Tracker_SHE' ) ) {
			Posimyth_Tracker_SHE::on_self_activate();
		}
	}
);

/**
 * Remove the weekly heartbeat schedule on deactivation.
 *
 * Without this the cron event stays registered in WordPress permanently after the plugin is gone,
 * firing against a hook with no listener and reappearing in every cron listing.
 */
register_deactivation_hook(
	SHE_HEADER__FILE__,
	function () {
		require_once SHE_HEADER_PATH . 'includes/posimyth-sdk/class-posimyth-tracker-she.php';
		if ( class_exists( 'Posimyth_Tracker_SHE' ) && method_exists( 'Posimyth_Tracker_SHE', 'unschedule' ) ) {
			Posimyth_Tracker_SHE::unschedule();
		}
	}
);

/**
 * Load gettext translate for our text domain.
 *
 * @since 1.0.0
 *
 * @return void
 */
function she_header_load_plugin() {

	if ( ! did_action( 'elementor/loaded' ) ) {
		add_action( 'admin_notices', 'she_header_fail_load' );
		return;
	}

	$elementor_version_required = '2.0';
	if ( ! version_compare( ELEMENTOR_VERSION, $elementor_version_required, '>=' ) ) {
		add_action(
			'admin_notices',
			function () {
				she_header_admin_notice_elementor_update(
					__( 'Sticky Header Effects not working because you are using an old version of Elementor.', 'she-header' )
				);
			}
		);
		return;
	}

	$elementor_version_recommendation = '3.0';
	if ( ! version_compare( ELEMENTOR_VERSION, $elementor_version_recommendation, '>=' ) ) {
		add_action(
			'admin_notices',
			function () {
				she_header_admin_notice_elementor_update(
					__( 'A new version of Elementor is available. For better performance and compatibility of Sticky Header Effects, we recommend updating to the latest version.', 'she-header' )
				);
			}
		);
	}

	include( SHE_HEADER_PATH . 'plugin.php' );
	include SHE_HEADER_PATH . 'includes/class-she-loader.php';
}
add_action( 'plugins_loaded', 'she_header_load_plugin' );

/**
 * Load the plugin text domain for translations.
 *
 * @since 2.2.0
 *
 * @return void
 */
function she_header_load_textdomain() {
	load_plugin_textdomain( 'she-header', false, dirname( SHE_HEADER_PLUGIN_BASE ) . '/languages' );
}
add_action( 'init', 'she_header_load_textdomain' );

/**
 * Render a standard WP admin error notice.
 *
 * @since 2.2.0
 *
 * @param string $message HTML message content (already built with safe tags).
 * @return void
 */
function she_render_admin_notice( $message ) {
	echo wp_kses_post( '<div class="error">' . $message . '</div>' );
}

/**
 * Show in WP Dashboard notice about the plugin is not activated.
 *
 * @since 1.0.0
 *
 * @return void
 */
function she_header_fail_load() {
	$screen = get_current_screen();
	if ( isset( $screen->parent_file ) && 'plugins.php' === $screen->parent_file && 'update' === $screen->id ) {
		return;
	}

	$plugin = 'elementor/elementor.php';

	if ( she_header_is_elementor_installed() ) {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$activation_url = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );

		$message = '<p>' . __( 'Sticky Header Effects not working because you need to activate the Elementor plugin.', 'she-header' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $activation_url, __( 'Activate Elementor Now', 'she-header' ) ) . '</p>';
	} else {
		if ( ! current_user_can( 'install_plugins' ) ) {
			return;
		}

		$install_url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );

		$message = '<p>' . __( 'Sticky Header Effects is not working because you need to install the Elementor plugin', 'she-header' ) . '</p>';
		$message .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $install_url, __( 'Install Elementor Now', 'she-header' ) ) . '</p>';
	}

	she_render_admin_notice( $message );
}

/**
 * Render the "Update Elementor" admin notice with the given message.
 *
 * Shared by both the hard out-of-date error (Elementor below the required
 * version) and the soft upgrade recommendation (Elementor below the
 * recommended version) — both show the same "Update Elementor Now" action and
 * differ only in their message sentence.
 *
 * @since 2.2.0
 *
 * @param string $message Already-translated notice sentence.
 * @return void
 */
function she_header_admin_notice_elementor_update( $message ) {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}

	$file_path = 'elementor/elementor.php';

	$upgrade_link = wp_nonce_url( self_admin_url( 'update.php?action=upgrade-plugin&plugin=' ) . $file_path, 'upgrade-plugin_' . $file_path );
	$notice = '<p>' . $message . '</p>';
	$notice .= '<p>' . sprintf( '<a href="%s" class="button-primary">%s</a>', $upgrade_link, __( 'Update Elementor Now', 'she-header' ) ) . '</p>';

	she_render_admin_notice( $notice );
}

if ( ! function_exists( 'she_header_is_elementor_installed' ) ) {

	function she_header_is_elementor_installed() {
		$file_path = 'elementor/elementor.php';
		$installed_plugins = get_plugins();

		return isset( $installed_plugins[ $file_path ] );
	}
}
