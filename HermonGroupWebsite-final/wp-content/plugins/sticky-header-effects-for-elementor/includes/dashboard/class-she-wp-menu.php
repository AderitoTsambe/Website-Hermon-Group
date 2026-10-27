<?php
/**
 * This file is used to load widget builder files and the builder.
 *
 * @link       https://posimyth.com/
 * @since      1.7.3
 *
 * @package    she-header
 */

/**
 * Exit if accessed directly.
 * */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'She_Wp_Menu' ) ) {

	/**
	 * This class used for widget load
	 *
	 * @since 1.7.3
	 */
	class She_Wp_Menu {

		/**
		 *
		 * Ensures only one instance of the class is loaded or can be loaded.
		 *
		 * @var instance
		 * @since 1.7.3
		 */
		private static $instance = null;

		/**
		 * This instance is used to load class
		 *
		 * @since 1.7.3
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
		 * @since 1.7.3
		 */
		public function __construct() {
			add_action( 'admin_menu', array( $this, 'she_admin_menu' ) );
			add_action( 'admin_enqueue_scripts', array( $this, 'she_enqueue_scripts' ) );
		}

		/**
		 * Add Menu Page WdKit.
		 *
		 * @since 1.7.3
		 * @version 2.0
		 */
		public function she_admin_menu() {
			$capability = 'manage_options';

			if ( current_user_can( $capability ) ) {
				add_action(
					'admin_menu',
					function () {
						add_submenu_page(
							'elementor',
							__( 'Sticky Header Effects', 'she-header' ),
							__( 'Sticky Header Effects', 'she-header' ),
							'manage_options',
							'she-header',
							array( $this, 'she_menu_page_template' ),
							14
						);
					},
					80
				);
			}
		}

		/**
		 * Load wdkit page content.
		 *
		 * @since 1.7.3
		 */
		public function she_menu_page_template() {
			echo '<div id="she-app"></div>';
		}

		/**
		 * Register the JavaScript for the admin area.
		 *
		 * @param string $page give builder name.
		 * @since 2.0
		 */
		public function she_enqueue_scripts( $page ) {

			$get_notification = get_option( 'she_menu_notificetions' );

			/*
			 * admin.css contains nothing but the unread badge on the Elementor menu item, so it is only
			 * worth a request while that badge is actually on screen. She_Loader::she_add_notificetions()
			 * adds the she-admin-notice-active class under exactly this condition, on the same request,
			 * so the two cannot disagree. Note the gate is the badge state and NOT the current screen:
			 * the admin menu renders everywhere, so restricting this to the dashboard page would leave
			 * the badge unstyled on every other admin page.
			 *
			 * The option is seeded to '1' on install and set to SHE_MENU_NOTIFICETIONS once the user has
			 * seen the dashboard, after which it never goes back — so on a settled site this now skips a
			 * stylesheet that used to load on every single admin page and style nothing.
			 */
			if ( $get_notification !== SHE_MENU_NOTIFICETIONS ) {
				wp_enqueue_style( 'she-admin-style', SHE_HEADER_URL . '/assets/css/admin.css', array(), SHE_HEADER_VERSION, 'all' );
			}

			if ( 'elementor_page_she-header' === $page ) {

				$plugins = array(
					array(
						'name'        => 'nexter-extension',
						'status'      => '',
						'plugin_slug' => 'nexter-extension/nexter-extension.php',
					),
					array(
						'name'        => 'wdesignkit',
						'status'      => '',
						'plugin_slug' => 'wdesignkit/wdesignkit.php',
					),
				);

				$all_plugins   = get_plugins();
				$update_plugin = array();
				foreach ( $plugins as $plugin ) {
					$pluginslug = ! empty( $plugin['plugin_slug'] ) ? sanitize_text_field( wp_unslash( $plugin['plugin_slug'] ) ) : '';

					if ( ! is_plugin_active( $pluginslug ) ) {
						if ( ! isset( $all_plugins[ $pluginslug ] ) ) {
								$plugin['status'] = 'unavailable';
						} else {
							$plugin['status'] = 'inactive';
						}

						$update_plugin[] = $plugin;
					} elseif ( is_plugin_active( $pluginslug ) ) {
						$plugin['status'] = 'active';
						$update_plugin[]  = $plugin;
					}
				}
				// Version the compiled dashboard bundle by its file modification
				// time so every rebuild busts the browser cache automatically
				// (falls back to the plugin version if the file is unreadable).
				$css_file    = SHE_HEADER_PATH . 'build/index.css';
				$js_file     = SHE_HEADER_PATH . 'build/index.js';
				$css_version = file_exists( $css_file ) ? filemtime( $css_file ) : SHE_HEADER_VERSION;
				$js_version  = file_exists( $js_file ) ? filemtime( $js_file ) : SHE_HEADER_VERSION;

				/*
				 * Analytics sharing state, so the dashboard can render the toggle in the right position on
				 * first paint instead of flashing the wrong one while an AJAX round trip resolves.
				 *
				 * Read from She_Dashboard_Ajax rather than repeated here: the same three values decide
				 * whether the control renders at all (`available` is false on a white-labelled install),
				 * whether it is editable (`can_manage` is false for a subsite admin on multisite) and where
				 * it sits. Two copies of that logic would drift, and the copy that drifted would be the one
				 * deciding whether a user is shown a switch that cannot work.
				 *
				 * Guarded: both files are included by She_Loader behind the same is_admin() +
				 * manage_options gate, but this class must not fatal if that ever changes.
				 */
				$she_analytics = array();
				if ( class_exists( 'She_Dashboard_Ajax' ) ) {
					$she_analytics = She_Dashboard_Ajax::instance()->she_analytics_state();
				}

				wp_enqueue_style( 'she-editor-css', SHE_HEADER_URL . 'build/index.css', array(), $css_version );
				wp_style_add_data( 'she-editor-css', 'rtl', 'replace' );

				if ( ! class_exists( 'She_Features' ) ) {
					include_once SHE_HEADER_PATH . 'includes/features/class-she-features.php';
				}

				$she_feature_groups = class_exists( 'She_Features' ) ? She_Features::get_groups() : array();
				$she_feature_status = class_exists( 'She_Features' ) ? She_Features::get_status() : array();

				wp_enqueue_script( 'she-editor-js', SHE_HEADER_URL . 'build/index.js', array( 'wp-i18n', 'wp-element', 'wp-components' ), $js_version, true );
				wp_set_script_translations( 'she-editor-js', 'she-header' );

				// Review ask: show only after the install is a few days old and
				// the user hasn't dismissed it. Old installs (no timestamp) qualify.
				$she_install_time = get_option( 'she_header_install_time' );
				$she_review_due   = ( ! $she_install_time || strtotime( $she_install_time ) <= ( time() - ( 3 * DAY_IN_SECONDS ) ) );
				$she_review_ready = ( 'hide' !== get_option( 'she_review_notice' ) && $she_review_due ) ? 1 : 0;

				// Home stat: header templates that have Sticky Header Effects
				// enabled (the Free "Enable" toggle writes "transparent":"yes"
				// into the element's Elementor data). Read the raw meta directly
				// so we don't trigger Elementor's per-read data upgrade routine.
				$she_sticky_headers = 0;
				$she_header_ids     = get_posts(
					array(
						'post_type'      => 'elementor_library',
						'post_status'    => 'publish',
						'posts_per_page' => -1,
						'fields'         => 'ids',
						'meta_key'       => '_elementor_template_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						'meta_value'     => 'header', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
						'no_found_rows'  => true,
					)
				);

				if ( is_array( $she_header_ids ) && ! empty( $she_header_ids ) ) {
					global $wpdb;
					$she_ids_in   = implode( ',', array_map( 'absint', $she_header_ids ) );
					$she_hdatas   = $wpdb->get_col( "SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_elementor_data' AND post_id IN ({$she_ids_in})" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

					foreach ( (array) $she_hdatas as $she_hdata ) {
						if ( is_string( $she_hdata ) && false !== strpos( $she_hdata, '"transparent":"yes"' ) ) {
							++$she_sticky_headers;
						}
					}
				}

				wp_localize_script(
					'she-editor-js',
					'shed_data',
					array(
						'ajax_url'           => admin_url( 'admin-ajax.php' ),
						'nonce'              => wp_create_nonce( 'she-db-nonce' ),
						'shed_url'           => SHE_HEADER_URL,
						'shed_wp_version'    => SHE_HEADER_VERSION,
						'she_wp_version'     => get_bloginfo( 'version' ),
						'shed_pro'           => apply_filters( 'she_pro_is_licensed', false ) ? 1 : 0,
						'shed_pro_installed' => defined( 'SHE_PRO_VERSION' ) ? 1 : 0,
						'shed_pro_version'   => defined( 'SHE_PRO_VERSION' ) ? SHE_PRO_VERSION : '',
						'shed_wdkit_url'     => SHE_WDKIT_URL,
						'onboarding_setup'   => get_option( 'she_onboarding_setup' ),
						'shed_review_ready'  => $she_review_ready,
						'shed_sticky_headers' => $she_sticky_headers,
						'shed_plugins'       => $update_plugin,
						'shed_features_list' => $she_feature_groups,
						'shed_features'      => $she_feature_status,
						// { available, enabled, can_manage } — see the note above. Written back through the
						// she_analytics_consent AJAX type, never by a generic option writer.
						'shed_analytics'     => $she_analytics,
						/*
						 * Target of the dashboard's "See what's shared" link. Passed from PHP rather than
						 * hardcoded in the bundle so it stays in step with the consent notice, which is given
						 * the same constant as its docs_url in the main plugin file. A URL baked into
						 * build/index.js is invisible to a grep of the PHP, which is exactly how a sibling
						 * product shipped two surfaces pointing at different pages.
						 */
						'shed_docs_url'      => SHE_HEADER_DOC_URL . 'data-sharing/',
					),
				);
			}
		}
	}

	She_Wp_Menu::instance();
}
