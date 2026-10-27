<?php
/**
 * This file is used to load widget builder files and the builder.
 *
 * @link       https://posimyth.com/
 * @since      2.0
 *
 * @package    she-header
 */

/**
 * Exit if accessed directly.
 * */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'She_Dashboard_Ajax' ) ) {

	/**
	 * This class used for widget load
	 *
	 * @since 2.0
	 */
	class She_Dashboard_Ajax {

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
			add_action( 'wp_ajax_she_dashboard_ajax_call', array( $this, 'she_dashboard_ajax_call' ) );
		}

		/**
		 * Load wdkit page content.
		 *
		 * @since 2.0
		 */
		public function she_dashboard_ajax_call() {

			if ( ! check_ajax_referer( 'she-db-nonce', 'nonce', false ) ) {

				$response = $this->she_set_response( false, 'Permission denied.', 'You do not have permission to perform this action.' );

				wp_send_json( $response );
				wp_die();
			}

			if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
				$response = $this->she_set_response( false, 'Invalid Permission.', 'Something went wrong.' );

				wp_send_json( $response );
				wp_die();
			}

			$type = isset( $_POST['type'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['type'] ) ) ) : false;
			if ( ! $type ) {
				$response = $this->she_set_response( false, 'Invalid type.', 'Something went wrong.' );

				wp_send_json( $response );
				wp_die();
			}

			switch ( $type ) {
				case 'shed_onload_data':
					$response = $this->shed_onload_data();
					break;
				case 'she_plugin_install':
					$response = $this->she_plugin_install();
					break;
				case 'she_theme_install':
					$response = $this->she_theme_install();
					break;
				case 'she_activate_theme':
					$response = $this->she_activate_theme();
					break;
				case 'she_prev_version':
					$response = $this->she_prev_version();
					break;
				case 'she_rollback_check':
					$response = $this->she_rollback_check();
					break;
				case 'she_api_call':
					$response = $this->she_api_call();
					break;
				case 'she_create_page':
					$response = $this->she_create_page();
					break;
				case 'she_onboarding_setup':
					$response = $this->she_onboarding_setup();
					break;
				case 'she_user_meta_data':
					$response = $this->she_user_meta_data();
					break;
				case 'she_feature_toggle':
					$response = $this->she_feature_toggle();
					break;
				case 'she_analytics_consent':
					$response = $this->she_analytics_consent();
					break;
				case 'she_dismiss_review':
					$response = $this->she_dismiss_review();
					break;
				default:
					$response = $this->she_set_response( false, 'Invalid type.', 'Something went wrong.' );
					break;
			}

			wp_send_json( $response );
			wp_die();
		}

		/**
		 * Set Response
		 *
		 * @since 2.0
		 */
		public function shed_onload_data() {

			$plugins = array(
				array(
					'name'        => 'the-plus-addons-for-elementor-page-builder',
					'status'      => '',
					'plugin_slug' => 'the-plus-addons-for-elementor-page-builder/theplus_elementor_addon.php',
				),
				array(
					'name'        => 'wdesignkit',
					'status'      => '',
					'plugin_slug' => 'wdesignkit/wdesignkit.php',
				),
				array(
					'name'        => 'the-plus-addons-for-block-editor',
					'status'      => '',
					'plugin_slug' => 'the-plus-addons-for-block-editor/the-plus-addons-for-block-editor.php',
				),
				array(
					'name'        => 'uichemy',
					'status'      => '',
					'plugin_slug' => 'uichemy/uichemy.php',
				),
				array(
					'name'        => 'nexter-extension',
					'status'      => '',
					'plugin_slug' => 'nexter-extension/nexter-extension.php',
				),
				array(
					'name'        => 'elementor-pro',
					'status'      => '',
					'plugin_slug' => 'elementor-pro/elementor-pro.php',
				),
			);

			$plugin_details = $this->she_check_plugins_depends( $plugins );
			$plugin_details = ! empty( $plugin_details ) ? $plugin_details : $plugins;

			$theme_details = $this->she_check_theme_depends( 'nexter' );

			$user       = wp_get_current_user();
			$user_image = get_avatar_url( $user->ID );

			$tpae_pro = 0;

			$check_onboarding = get_option( 'she_onboarding_setup' );

			$set_onboarding['check_onboarding'] = 'show';
			if ( $check_onboarding ) {
				$set_onboarding['check_onboarding'] = 'hide';
			}

			$user_info = array(
				'user_image'        => $user_image,
				'roles'             => $user->roles,
				'user_name'         => $user->display_name,
				'user_email'        => $user->user_email,
				'she_notificetions' => 'open',
				'success'           => true,
			);

			$response = array(
				'success'          => true,
				'message'          => esc_html__( 'success', 'she-header' ),
				'description'      => esc_html__( 'success', 'she-header' ),
				'user_info'        => $user_info,
				'plugin_detail'    => $plugin_details,
				'theme_detail'     => $theme_details,
				'check_onboarding' => $set_onboarding,
			);

			return $response;
		}

		/**
		 *
		 * It is Use for Check Plugin Dependency of template.
		 *
		 * @since 2.0
		 *
		 * @param array $plugins List of required plugins to check.
		 */
		public function she_check_plugins_depends( $plugins ) {
			$update_plugin = array();

			$all_plugins = get_plugins();

			foreach ( $plugins as $plugin ) {
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
			}

			return $update_plugin;
		}

		/**
		 *
		 * It is Use for Check Theme Dependency of template.
		 *
		 * @since 2.0
		 *
		 * @param array $theme_slug List of required theme to check.
		 */
		public function she_check_theme_depends( $theme_slug ) {

			$theme = wp_get_theme( $theme_slug );

			if ( ! $theme->exists() ) {
				return array(
					'name'   => $theme_slug,
					'status' => 'unavailable',
				);
			}

			$current_theme = wp_get_theme();

			if ( $theme_slug === $current_theme->get_stylesheet() ) {
				return array(
					'name'   => $theme_slug,
					'status' => 'active',
				);
			} else {
				return array(
					'name'   => $theme_slug,
					'status' => 'inactive',
				);
			}
		}

		/**
		 *
		 * It is Use for Active Theme of template.
		 *
		 * @since 2.0
		 *
		 * @param array $plugins List of required plugins to check.
		 */
		public function she_activate_theme() {

			if ( ! current_user_can( 'switch_themes' ) ) {
				return $this->she_set_response( false, 'Permission denied.', 'You do not have permission to switch themes.' );
			}

			$theme_slug = isset( $_POST['theme_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['theme_slug'] ) ) : '';

			$active_theme = wp_get_theme();
			$theme_name   = $active_theme->get( 'Name' );

			$theme = wp_get_theme( $theme_slug );
			if ( $theme->exists() && 'Nexter' !== $theme_name ) {

				switch_theme( $theme->get_stylesheet() );
				return array(
					'name'   => $theme_slug,
					'status' => 'Activated',
				);
			}

			return $this->she_set_response( false, 'Activation failed.', 'Theme not found or already active.' );
		}

		/**
		 * Get Plugin Previous Versions
		 *
		 * @since 2.0
		 */
		public function she_prev_version() {

			$versions_list = get_transient( 'she_rollback_version_' . SHE_HEADER_VERSION );

			if ( $versions_list === false ) {

				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

				$plugin_info = plugins_api(
					'plugin_information',
					array(
						'slug' => 'sticky-header-effects-for-elementor',
					)
				);

				if ( empty( $plugin_info->versions ) || ! is_array( $plugin_info->versions ) ) {
					return array();
				}

				krsort( $plugin_info->versions );

				$versions_list = array();

				$index = 0;
				foreach ( $plugin_info->versions as $version => $download_link ) {
					if ( 25 <= $index ) {
						break;
					}

					$lowercase_version      = strtolower( $version );
					$check_rollback_version = ! preg_match( '/(beta|rc|trunk|dev)/i', $lowercase_version );

					$check_rollback_version = apply_filters( 'she_check_rollback_version', $check_rollback_version, $lowercase_version );

					if ( ! $check_rollback_version ) {
						continue;
					}

					if ( version_compare( $version, SHE_HEADER_VERSION, '>=' ) ) {
						continue;
					}

					++$index;
					$versions_list[] = $version;
				}

				set_transient( 'she_rollback_version_' . SHE_HEADER_VERSION, $versions_list, WEEK_IN_SECONDS );
			}

			return $versions_list;
		}

		/**
		 * Rollback to Previous Versions
		 *
		 * @since 2.0
		 */
		public function she_rollback_check() {

			if ( ! current_user_can( 'update_plugins' ) ) {
				return $this->she_set_response( false, 'Permission denied.', 'You do not have permission to update plugins.' );
			}

			$current_ver = isset( $_POST['version'] ) ? sanitize_text_field( wp_unslash( $_POST['version'] ) ) : '';

			$rv = $this->she_prev_version();
			if ( empty( $current_ver ) || ! in_array( $current_ver, $rv, true ) ) {
				return $this->she_set_response( false, 'Invalid version.', 'Try selecting another version.' );
			}

			$plugin_slug = basename( SHE_HEADER_PLUGIN_BASE, '.php' );

			$this_version    = $current_ver;
			$this_pluginname = SHE_HEADER_PLUGIN_BASE;
			$this_pluginslug = $plugin_slug;
			$this_plugin_url = sprintf( 'https://downloads.wordpress.org/plugin/%s.%s.zip', $this_pluginslug, $this_version );

			$plugin_info = array(
				'plugin_name' => $this_pluginname,
				'plugin_slug' => $this_pluginslug,
				'version'     => $this_version,
				'package_url' => $this_plugin_url,
			);

			$update_plugins_data = get_site_transient( 'update_plugins' );

			if ( ! is_object( $update_plugins_data ) ) {
				$update_plugins_data = new \stdClass();
			}

			$plugin_info              = new \stdClass();
			$plugin_info->new_version = $this_version;
			$plugin_info->slug        = $this_pluginslug;
			$plugin_info->package     = $this_plugin_url;
			$plugin_info->url         = 'https://stickyheadereffects.com/';

			$update_plugins_data->response[ $this_pluginname ] = $plugin_info;

			set_site_transient( 'update_plugins', $update_plugins_data );

			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			$args = array(
				'url'    => 'update.php?action=upgrade-plugin&plugin=' . rawurlencode( $this_pluginname ),
				'plugin' => $this_pluginname,
				'nonce'  => 'upgrade-plugin_' . $this_pluginname,
				'title'  => '<div class="theplus-rb-subtitle">' . esc_html__( 'Rollback to Previous Version', 'she-header' ) . '</div>',
			);

			$upgrader_plugin = new \Plugin_Upgrader( new \Plugin_Upgrader_Skin( $args ) );
			$upgrader_plugin->upgrade( $this_pluginname );

			$activation_result = activate_plugin( $this_pluginname );

			return $this->she_set_response( true, 'Roll Back Successfully', 'Roll Back Successfully Done.' );
		}

		/**
		 * WdesignKit Onboarding check
		 *
		 * @since 2.0
		 * @version 2.1.1
		 */
		public function she_set_wdkit_onboarding( $she_plugin ) {

			if ( ! empty( $she_plugin ) ) {
				$wdkit_onbording = get_option( 'wkit_onbording_end', null );

				if ( $wdkit_onbording === null ) {
					add_option( 'wkit_onbording_end', true );
				} else {
					update_option( 'wkit_onbording_end', true );
				}
			}
		}

		/**
		 * Plugin Install
		 *
		 * @since 2.0
		 * @version 2.1.1
		 */
		public function she_plugin_install() {

			if ( ! current_user_can( 'install_plugins' ) ) {
				$response = $this->she_set_response( false, 'Permission denied.', 'You do not have permission to perform this action.' );
				return $response;
			}

			$slug = isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '';
			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			if ( ! $slug ) {
				return $this->she_set_response( false, 'Slug Not Found.', 'Something went wrong.' );
			}

			$installed_plugins = get_plugins();

			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			include_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
			include_once ABSPATH . 'wp-admin/includes/class-plugin-upgrader.php';

			$result = array();

			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

			$plugin_info = plugins_api(
				'plugin_information',
				array(
					'slug'   => $name,
					'fields' => array(
						'version' => false,
					),
				)
			);

			if ( is_wp_error( $plugin_info ) || ! isset( $plugin_info->download_link ) ) {
				wp_send_json_error( array( 'content' => __( 'Failed to retrieve plugin information.', 'she-header' ) ) );
			}

			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );

			$plugin_basename = $slug;

			if ( ! isset( $installed_plugins[ $plugin_basename ] ) && empty( $installed_plugins[ $plugin_basename ] ) ) {

				$installed         = $upgrader->install( $plugin_info->download_link );

				if ( is_wp_error( $installed ) || ! $installed ) {
					return $this->she_set_response( false, 'Install failed.', 'Plugin could not be installed.' );
				}

				$activation_result = activate_plugin( $plugin_basename );

				$success = null === $activation_result;

				$she_plugin = isset( $_POST['she_plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['she_plugin'] ) ) : '';

				$this->she_set_wdkit_onboarding( $she_plugin );

				$result = $this->she_set_response( $success, 'Successfully Install', 'Successfully Install', '' );

			} elseif ( isset( $installed_plugins[ $plugin_basename ] ) ) {

				$activation_result = activate_plugin( $plugin_basename );

				$success    = null === $activation_result;
				$she_plugin = isset( $_POST['she_plugin'] ) ? sanitize_text_field( wp_unslash( $_POST['she_plugin'] ) ) : '';

				$this->she_set_wdkit_onboarding( $she_plugin );
				$result = $this->she_set_response( $success, 'Successfully Activate', 'Successfully Activate', '' );

			}

			return $result;
		}

		/**
		 * Theme Install
		 *
		 * @since 2.0
		 */
		public function she_theme_install() {

			if ( ! current_user_can( 'install_themes' ) ) {
				return $this->she_set_response( false, 'Invalid nonce.', 'The security check failed. Please refresh the page and try again.' );
			}

			$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			if ( ! $name ) {
				return $this->she_set_response( false, 'Theme slug not found.', 'Something went wrong.' );
			}

			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			require_once ABSPATH . 'wp-admin/includes/class-automatic-upgrader-skin.php';
			require_once ABSPATH . 'wp-admin/includes/theme-install.php';

			$theme_info = themes_api(
				'theme_information',
				array(
					'slug'   => $name,
					'fields' => array(
						'download_link' => true,
					),
				)
			);

			if ( is_wp_error( $theme_info ) || empty( $theme_info->download_link ) ) {
				return $this->she_set_response( false, 'oops', 'Could not retrieve theme information.', '' );
			}

			$skin      = new \Automatic_Upgrader_Skin();
			$upgrader  = new \Theme_Upgrader( $skin );
			$installed = $upgrader->install( $theme_info->download_link );

			if ( is_wp_error( $installed ) || ! $installed ) {
				return $this->she_set_response( false, 'Install failed.', 'Theme could not be installed.', '' );
			}

			/* translators: %s: theme name */
			return $this->she_set_response( true, sprintf( __( 'Success %s', 'she-header' ), esc_html( $name ) ), '', '' );
		}

		/**
		 * API call and get Response
		 *
		 * @since 2.0
		 */
		public function she_api_call() {

			$method  = isset( $_POST['method'] ) ? sanitize_text_field( wp_unslash( $_POST['method'] ) ) : 'POST';
			$api_url = isset( $_POST['api_url'] ) ? esc_url_raw( wp_unslash( $_POST['api_url'] ) ) : '';
			$body    = isset( $_POST['url_body'] ) ? json_decode( wp_unslash( $_POST['url_body'] ) ) : array();

			// Whitelist allowed external domains to prevent SSRF.
			$allowed_hosts = array( 'stickyheadereffects.com', 'api.posimyth.com', 'posimyth.com', 'wdesignkit.com' );
			$parsed_url    = wp_parse_url( $api_url );
			$parsed_host   = isset( $parsed_url['host'] ) ? strtolower( (string) $parsed_url['host'] ) : '';
			$parsed_host   = preg_replace( '/^www\./', '', $parsed_host );
			$parsed_scheme = isset( $parsed_url['scheme'] ) ? strtolower( $parsed_url['scheme'] ) : '';
			if ( empty( $api_url ) || ! in_array( $parsed_host, $allowed_hosts, true ) ) {
				return $this->she_set_response( false, 'Invalid URL.', 'Only requests to approved domains are allowed.' );
			}

			// Force HTTPS and reject any non-standard port to further harden against SSRF.
			if ( 'https' !== $parsed_scheme || isset( $parsed_url['port'] ) ) {
				return $this->she_set_response( false, 'Invalid URL.', 'Only standard HTTPS requests are allowed.' );
			}

			// Only accept a decoded JSON object/array as the request body.
			if ( ! is_array( $body ) && ! is_object( $body ) ) {
				$body = array();
			}

			// Only POST and GET are supported.
			if ( ! in_array( $method, array( 'POST', 'GET' ), true ) ) {
				return $this->she_set_response( false, 'Invalid method.', 'Only GET and POST are supported.' );
			}

			$header_template = isset( $_POST['store'] ) ? sanitize_text_field( wp_unslash( $_POST['store'] ) ) : '';

			if ( 'header_template' === $header_template ) {
				$she_header_template = get_transient( 'she_header_template' );

				if ( false !== $she_header_template ) {
					return get_option( 'she_header_template' );
				}

				delete_option( 'she_header_template' );
				delete_transient( 'she_header_template' );
			}

			$args = array(
				'method'  => $method,
				'headers' => array(
					'Content-Type' => 'application/json',
				),
			);

			if ( ! empty( $body ) ) {
				$args['body'] = wp_json_encode( $body );
			}

			$response = null;

			if ( 'POST' === $method ) {
				$response = wp_remote_post( $api_url, $args );
			}

			if ( 'GET' === $method ) {
				$response = wp_remote_get( $api_url, $args );
			}

			if ( is_wp_error( $response ) ) {
				return $this->she_set_response( false, 'Request failed.', $response->get_error_message() );
			}

			$status_code = wp_remote_retrieve_response_code( $response );
			$getdataone  = wp_remote_retrieve_body( $response );
			$statuscode  = array( 'HTTP_CODE' => $status_code );

			$response = json_decode( $getdataone, true );
			$final    = $statuscode;

			if ( is_array( $statuscode ) && is_array( $response ) ) {
				$final = array_merge( $statuscode, $response );

				if ( 200 === (int) $status_code ) {
					if ( 'header_template' === $header_template ) {
						add_option( 'she_header_template', $final, '', 'no' );
						set_transient( 'she_header_template', 'header_template', 24 * HOUR_IN_SECONDS );
						// set_transient('she_header_template', 'header_template', 120);
					}
				}
			}

			return $final;
		}


		/**
		 * Create Page for Header
		 *
		 * @since 2.0
		 */
		public function she_create_page() {
			$post_type = isset( $_POST['post_type'] ) ? sanitize_text_field( wp_unslash( $_POST['post_type'] ) ) : 'elementor_library';

			if ( ! in_array( $post_type, array( 'elementor_library', 'nxt_builder' ), true ) ) {
				$post_type = 'elementor_library';
			}

			$post_args = array(
				'post_type'   => $post_type,
				'post_title'  => 'sticky-header',
				'post_status' => 'draft',
			);

			$post_id = wp_insert_post( $post_args );

			if ( $post_type === 'nxt_builder' ) {
				if ( $post_id && ! is_wp_error( $post_id ) ) {
					update_post_meta( $post_id, 'template_type', 'header' );
					update_post_meta( $post_id, 'nxt-hooks-layout-sections', 'header' );
				}
			} elseif ( $post_type === 'elementor_library' ) {
				if ( $post_id && ! is_wp_error( $post_id ) ) {
					update_post_meta( $post_id, '_elementor_template_type', 'header' );
				}
			}

			$elementor_edit_url = admin_url( 'post.php?post=' . $post_id . '&action=elementor&she_onload=true' );

			return $this->she_set_response(
				true,
				'Page created successfully',
				'',
				array(
					'post_id'  => $post_id,
					'edit_url' => $elementor_edit_url,
				)
			);
		}

		/**
		 * Onboarding Setup
		 *
		 * @since 2.0
		 */
		public function she_onboarding_setup() {

			$onboarding = get_option( 'she_onboarding_setup' );

			if ( ! $onboarding ) {
				update_option( 'she_onboarding_setup', 'hide' );
			}

			$onboarding = get_option( 'she_onboarding_setup' );
			if ( $onboarding ) {
				$response = $this->she_set_response( true, 'Onboarding Setup', 'Onboarding Setup', '' );
			} else {
				$response = $this->she_set_response( false, 'Onboarding Setup Failed', 'Onboarding Setup Failed', '' );
			}

			$get_notification = get_option( 'she_menu_notificetions' );

			if ( $get_notification !== SHE_MENU_NOTIFICETIONS ) {
				update_option( 'she_menu_notificetions', SHE_MENU_NOTIFICETIONS );
			}

			return $response;
		}

		/**
		 * Persist the user's dismissal of the dashboard review ask.
		 *
		 * @since 2.2.1
		 */
		public function she_dismiss_review() {

			update_option( 'she_review_notice', 'hide' );

			return $this->she_set_response( true, 'Review dismissed', 'Review dismissed', '' );
		}


		/**
		 * User Meta Data — retained as a no-op so the dashboard's existing call still resolves.
		 *
		 * The legacy onboarding telemetry this used to perform was removed in 2.2.1. It POSTed the site's
		 * admin_email to api.posimyth.com/wp-json/she/v2/she_store_user_data on every call, with no opt-in
		 * surface, no white-label suppression and no way for anyone to decline — an email address is
		 * personal data, and the consent copy this plugin now shows explicitly promises none is sent.
		 *
		 * Reporting is the shared SDK's job now (Posimyth_Tracker_SHE, booted from the main plugin file)
		 * and is gated on the sharing opt-in. Its payload carries no email at all; the single exception is
		 * the deactivation dialog's "I agree to be contacted" box, which is off by default.
		 *
		 * Consent is deliberately NOT written here either. Nothing in this handler represents the user
		 * agreeing to anything — the dashboard calls it as part of loading — so treating it as consent
		 * would switch sharing on for people who were never asked, which is the pattern being removed.
		 *
		 * The endpoint keeps answering because build/index.js still calls it and a removed case would
		 * return "Invalid type" to a dashboard that has no reason to show an error.
		 *
		 * @since 2.0
		 * @version 2.2.1
		 *
		 * @return array
		 */
		public function she_user_meta_data() {
			return $this->she_set_response( true, 'success', '', array( 'onBoarding' => true ) );
		}

		/**
		 * Capability required to answer the analytics sharing question.
		 *
		 * On multisite the consent is ONE answer for the whole network — the SDK stores it as a site
		 * option and Posimyth_Consent_Notice gates its own notice on manage_network_options. This screen
		 * has to require the same thing, or a subsite administrator could decide for every other blog on
		 * the network through the dashboard even though the notice refuses to let them.
		 *
		 * @since 2.2.1
		 *
		 * @return string
		 */
		private function she_analytics_capability() {
			return is_multisite() ? 'manage_network_options' : 'manage_options';
		}

		/**
		 * Whether the analytics feature exists on this install at all.
		 *
		 * A white-labelled install never boots the tracker (see she_posimyth_is_white_labelled() in the
		 * main plugin file), so the dashboard must not offer a switch that cannot do anything.
		 *
		 * @since 2.2.1
		 *
		 * @return bool
		 */
		private function she_analytics_available() {
			if ( ! function_exists( 'she_posimyth_is_white_labelled' ) ) {
				return false;
			}

			return ! she_posimyth_is_white_labelled();
		}

		/**
		 * Current analytics state, for the dashboard to render from.
		 *
		 * @since 2.2.1
		 *
		 * @return array
		 */
		public function she_analytics_state() {
			return array(
				// False on a rebranded install: hide the control entirely rather than showing a dead one.
				'available'  => $this->she_analytics_available(),
				'enabled'    => (bool) get_site_option( 'posimyth_she_share_analytics', false ),
				// False for a subsite admin on multisite — show the state, but read-only.
				'can_manage' => current_user_can( $this->she_analytics_capability() ),
			);
		}

		/**
		 * Records an explicit yes/no to analytics sharing.
		 *
		 * Three things have to happen together, and each fails silently on its own:
		 *
		 * 1. SITE options, not per-blog options. Posimyth_Tracker_Base::has_consent() reads with
		 *    get_site_option(), so update_option() would write somewhere the SDK never looks — on
		 *    multisite the switch would appear to work while nothing was ever sent. On single site
		 *    get_site_option() falls back to the plain option, so this is equivalent there.
		 *
		 * 2. The suite-wide "answered" flag is set for BOTH answers. Posimyth_Consent_Notice::
		 *    should_show() treats "opt-in empty and never dismissed" as unanswered, so switching sharing
		 *    OFF here without this would bring the admin notice straight back to ask again — immediately
		 *    after the user deliberately said no.
		 *
		 * 3. Turning it on sends something now. Without a ping the hub does not learn about this install
		 *    until the weekly cron happens to fire.
		 *
		 * @since 2.2.1
		 *
		 * @param bool $enable Whether sharing is being switched on.
		 * @return void
		 */
		private function she_store_analytics_consent( $enable ) {

			$enable = (bool) $enable;

			update_site_option( 'posimyth_she_share_analytics', $enable ? 1 : 0 );
			update_site_option( 'posi_consent_dismissed_she_suite', 1 );

			if ( ! $enable || ! class_exists( 'Posimyth_Tracker_SHE' ) ) {
				return;
			}

			/*
			 * report_activation() sends `activate` at most once per active period, so a user who switches
			 * sharing off and later back on would otherwise send nothing at all and stay invisible until
			 * the weekly heartbeat. Send the activation on the first opt-in, and a heartbeat on a
			 * re-opt-in — that reports current state now without inflating the hub's activation count,
			 * which is exactly what the once-per-period guard exists to protect.
			 */
			$already_reported = get_option( 'posimyth_she_activate_reported' );

			Posimyth_Tracker_SHE::send_first_ping();

			if ( $already_reported ) {
				Posimyth_Tracker_SHE::do_request( 'heartbeat' );
			}
		}

		/**
		 * Read or set the analytics sharing consent from the dashboard.
		 *
		 * Nonce and the logged-in manage_options check are already done by she_dashboard_ajax_call();
		 * this adds the network-scope capability on top — see she_analytics_capability().
		 *
		 * Deliberately its own endpoint. Consent has to be written as a site option, has to set the
		 * suite-wide answered flag and has to ping, none of which a generic option writer does — and a
		 * consent flag should not be reachable through a general-purpose read/write API in the first
		 * place.
		 *
		 * @since 2.2.1
		 *
		 * @return array
		 */
		public function she_analytics_consent() {

			if ( ! $this->she_analytics_available() ) {
				return $this->she_set_response( false, 'Unavailable.', 'Data sharing is not available on this installation.' );
			}

			if ( ! current_user_can( $this->she_analytics_capability() ) ) {
				return $this->she_set_response( false, 'Invalid Permission.', 'You do not have permission to change this setting.' );
			}

			$operation = isset( $_POST['operation'] ) ? strtolower( sanitize_text_field( wp_unslash( $_POST['operation'] ) ) ) : 'get';

			if ( 'set' === $operation ) {

				if ( ! isset( $_POST['share_analytics'] ) ) {
					return $this->she_set_response( false, 'No data found.', 'Please send valid data.' );
				}

				// Accept the truthy spellings a JS client may send; anything else — including the strings
				// "false" and "0", which are both truthy in PHP — counts as off.
				$raw = strtolower( sanitize_text_field( wp_unslash( $_POST['share_analytics'] ) ) );

				$this->she_store_analytics_consent( in_array( $raw, array( '1', 'true', 'on', 'yes' ), true ) );
			}

			return $this->she_set_response( true, 'Data Found.', 'Data Found Successfully.', $this->she_analytics_state() );
		}

		/**
		 * Enable / disable Free sub-features and Pro modules from the dashboard
		 * "Features" tab. Accepts either a single { slug, status } pair or a
		 * bulk { features: JSON map } payload (used by Enable All / Disable All
		 * and the per-group toggles), persists it, and clears Elementor's CSS
		 * cache so removed Free controls stop emitting styles.
		 *
		 * @since 2.2.1
		 */
		public function she_feature_toggle() {

			if ( ! class_exists( 'She_Features' ) ) {
				include_once SHE_HEADER_PATH . 'includes/features/class-she-features.php';
			}

			if ( ! class_exists( 'She_Features' ) ) {
				return $this->she_set_response( false, 'Unavailable.', 'Feature manager could not be loaded.' );
			}

			$map = array();

			if ( isset( $_POST['features'] ) ) {
				$decoded = json_decode( sanitize_text_field( wp_unslash( $_POST['features'] ) ), true );
				if ( is_array( $decoded ) ) {
					foreach ( $decoded as $slug => $value ) {
						$slug         = sanitize_text_field( $slug );
						$map[ $slug ] = ( 'off' === $value ) ? 'off' : 'on';
					}
				}
			} elseif ( isset( $_POST['slug'] ) ) {
				$slug          = sanitize_text_field( wp_unslash( $_POST['slug'] ) );
				$status        = isset( $_POST['status'] ) ? sanitize_text_field( wp_unslash( $_POST['status'] ) ) : 'on';
				$map[ $slug ]  = ( 'off' === $status ) ? 'off' : 'on';
			}

			if ( empty( $map ) ) {
				return $this->she_set_response( false, 'Invalid request.', 'No features supplied.' );
			}

			$status = She_Features::save_status( $map );

			// Removed Free controls no longer emit their selectors; regenerate
			// Elementor's cached CSS so disabled effects stop on the frontend.
			if ( class_exists( '\Elementor\Plugin' ) ) {
				$elementor = \Elementor\Plugin::instance();
				if ( isset( $elementor->files_manager ) ) {
					$elementor->files_manager->clear_cache();
				}
			}

			return $this->she_set_response( true, 'Features updated.', '', array( 'features' => $status ) );
		}

		/**
		 * Set the response data.
		 *
		 * @since 2.0
		 *
		 * @param bool   $success     Indicates whether the operation was successful. Default is false.
		 * @param string $message     The main message to include in the response. Default is an empty string.
		 * @param string $description A more detailed description of the message or error. Default is an empty string.
		 * @param mixed  $data        Optional additional data to include in the response. Default is an empty string.
		 */
		public function she_set_response( $success = false, $message = '', $description = '', $data = '' ) {

			$response = array(
				'success'     => $success,
				'message'     => esc_html( $message ),
				'description' => esc_html( $description ),
				'data'        => $data,
			);

			return $response;
		}
	}

	She_Dashboard_Ajax::instance();
}
