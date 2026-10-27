<?php
/**
 * This file is used to load widget builder files and the builder.
 *
 * @link https://posimyth.com/
 * @since 2.0
 *
 * @package she-header
 */

/**
 * Exit if accessed directly.
 * */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'She_Notice_Main' ) ) {

	/**
	 * This class used for widget load
	 *
	 * @since 2.0
	 */
	class She_Notice_Main {

		/**
		 *
		 * Ensures only one instance of the class is loaded or can be loaded.
		 *
		 * @var instance
		 * @since 2.0
		 */
		private static $instance = null;

		/**
		 * This instance is used to load class
		 *
		 * @since 2.0
		 */
		public static function instance() {

			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * This constructor is used to load builder files.
		 *
		 * @since 2.0
		 */
		public function __construct() {
			$this->she_load();
		}

		/**
		 *
		 * It is Use for Check Plugin Dependency of template.
		 *
		 * @since 6.0.0
		 */
		public function tpae_check_plugins_depends( $plugin ) {
			$update_plugin = array();

			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			
			$all_plugins = get_plugins();

			$pluginslug = ! empty( $plugin['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $plugin['plugin_slug'] ) ) : '';

			if ( ! is_plugin_active( $pluginslug ) ) {
				if ( ! isset( $all_plugins[ $pluginslug ] ) ) {
						$plugin['status'] = 'unavailable';
				} else {
					$plugin['status'] = 'inactive';
				}

				$update_plugin[] = $plugin;
			} else {
				$plugin['status'] = 'active';
				$update_plugin[]  = $plugin;
			}

			return $update_plugin;
		}

		/**
		 * Add Menu Page WdKit.
		 *
		 * @version 2.0
		 */
		public function she_load() {
			if ( is_admin() && current_user_can( 'manage_options' ) ) {
				/*
				 * The legacy deactivation dialog (class-she-deactivate-feedback.php) was deleted in 2.2.1.
				 *
				 * Its replacement is the shared SDK's Posimyth_Deactivation_Survey, wired up in
				 * she_posimyth_analytics_boot() in the main plugin file. Do NOT include a second dialog
				 * here: both bound the Plugins screen's Deactivate link, so two modals stacked on one
				 * click — the same bug Nexter Extension and The Plus Addons for Elementor each shipped
				 * until their legacy popups were deleted.
				 *
				 * The old one also found the link by `[data-slug="…"]`, which WordPress derives from the
				 * TRANSLATED plugin name, so on a non-English locale it silently matched nothing; depended
				 * on elementorCommon, so it did nothing at all when Elementor was inactive; and posted the
				 * reason plus the current user's email address to api.posimyth.com/she/v2 with no opt-in
				 * surface and no white-label suppression. The SDK dialog matches the link by href, needs no
				 * Elementor, is suppressed on rebranded installs, and only attaches an email address when
				 * the user ticks the contact box.
				 */
				/*
				 * The 20% off Pro sale notice supersedes the Pro-launch notice
				 * (class-she-pro-launch-notice.php, kept for later reuse). Only one
				 * dark upsell banner shows at a time so the admin screens are not
				 * stacked with two notices for the same non-Pro user.
				 */
				include SHE_HEADER_PATH . 'includes/notices/class-she-sale-notice.php';

				// Join Community notice (30 days after install) — skip once dismissed.
				if ( ! get_option( 'she_join_community_notice' ) ) {
					include SHE_HEADER_PATH . 'includes/notices/class-she-join-community-notice.php';
				}

				$ele_pro_plugin = array(
					'name'        => 'elementor-pro',
					'status'      => '',
					'plugin_slug' => 'elementor-pro/elementor-pro.php',
				);

				$ele_pro_details = $this->tpae_check_plugins_depends( $ele_pro_plugin );
			  	
				if ( ! empty( $ele_pro_details[0]['status'] ) && 'unavailable' === $ele_pro_details[0]['status'] ) {
					include SHE_HEADER_PATH . 'includes/notices/class-she-nexter-extension-promo.php';
				}
			}
		}
	}

	She_Notice_Main::instance();
}
