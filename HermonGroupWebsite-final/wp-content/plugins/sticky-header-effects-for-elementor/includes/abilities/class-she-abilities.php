<?php
/**
 * WordPress Abilities API integration (Free).
 *
 * Registers the `sticky-header-effects` ability category and the core
 * demo-building abilities so AI agents (and any Abilities API consumer,
 * including WebMCP) can create and configure sticky-header demos through the
 * plugin's own validated code path instead of writing `_elementor_data` blind.
 *
 * Abilities are a WordPress 6.9+ feature. On older cores `wp_register_ability()`
 * does not exist and this class is a no-op (see {@see Abilities::init()}).
 *
 * @package sticky-header-effects-for-elementor
 * @since 2.2.0
 */

namespace SheHeader;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Registers Free-side abilities and exposes the shared settings/Elementor-data
 * helpers that the Pro abilities reuse, so demo building has a single code path.
 */
class Abilities {

	/**
	 * Ability category slug shared by Free and Pro abilities.
	 */
	const CATEGORY = 'sticky-header-effects';

	/**
	 * Wire the registration hooks.
	 *
	 * Safe to call on every request: it only adds actions, and bails entirely
	 * when the Abilities API is unavailable (WordPress < 6.9).
	 *
	 * @return void
	 */
	public static function init() {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return; // Abilities API not available (WP < 6.9).
		}

		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Register the shared ability category.
	 *
	 * @return void
	 */
	public static function register_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) {
			return;
		}

		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Sticky Header Effects', 'she-header' ),
				'description' => __( 'Create and configure sticky-header demos built with Sticky Header Effects for Elementor.', 'she-header' ),
			)
		);
	}

	/**
	 * Register the Free abilities.
	 *
	 * @return void
	 */
	public static function register_abilities() {
		// 1. List the available sticky effects (read-only catalogue).
		wp_register_ability(
			'sticky-header-effects/list-effects',
			array(
				'label'               => __( 'List sticky header effects', 'she-header' ),
				'description'         => __( 'Returns the catalogue of available sticky-header effect settings (keys, labels, types, defaults and allowed values). Call this first so other abilities only receive valid setting keys and values.', 'she-header' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'        => 'object',
					'description' => __( 'Effect catalogue keyed by setting name.', 'she-header' ),
				),
				'execute_callback'    => array( __CLASS__, 'exec_list_effects' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		// 2. Apply sticky settings to an existing Elementor template/section.
		wp_register_ability(
			'sticky-header-effects/apply-settings',
			array(
				'label'               => __( 'Apply sticky header settings', 'she-header' ),
				'description'         => __( 'Applies sticky-header effect settings to an existing Elementor header template or page. Enables the sticky effect and merges the given settings into the target element. Use list-effects to discover valid keys.', 'she-header' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'ID of the Elementor post/template to update.', 'she-header' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'Optional Elementor element id to target. Defaults to the first top-level element.', 'she-header' ),
						),
						'settings'   => array(
							'type'                 => 'object',
							'description'          => __( 'Map of sticky effect setting keys to values. Unknown keys are ignored.', 'she-header' ),
							'additionalProperties' => true,
						),
					),
					'required'   => array( 'post_id', 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'element_id'   => array( 'type' => 'string' ),
						'applied_keys' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'exec_apply_settings' ),
				'permission_callback' => function ( $input ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
					return $post_id && current_user_can( 'edit_post', $post_id );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);

		// 3. Create a brand-new sticky-header demo (template + optional page).
		wp_register_ability(
			'sticky-header-effects/create-demo',
			array(
				'label'               => __( 'Create sticky header demo', 'she-header' ),
				'description'         => __( 'Creates an Elementor header template pre-built with a sticky header (brand + nav) and the given sticky effects applied, and optionally a page that uses it. Returns the edit and preview URLs.', 'she-header' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'demo_name'  => array(
							'type'        => 'string',
							'description' => __( 'Title for the demo header template.', 'she-header' ),
						),
						'effects'    => array(
							'type'                 => 'object',
							'description'          => __( 'Map of sticky effect setting keys to values (see list-effects). Optional.', 'she-header' ),
							'additionalProperties' => true,
						),
						'brand'      => array(
							'type'        => 'string',
							'description' => __( 'Brand/logo text shown on the left of the header. Defaults to the site name.', 'she-header' ),
						),
						'menu_items' => array(
							'type'        => 'array',
							'description' => __( 'Navigation labels shown on the right. Defaults to Home/About/Services/Contact.', 'she-header' ),
							'items'       => array( 'type' => 'string' ),
						),
						'with_page'  => array(
							'type'        => 'boolean',
							'description' => __( 'Also create a draft page that demonstrates the header. Default false.', 'she-header' ),
						),
					),
					'required'   => array( 'demo_name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'template_id' => array( 'type' => 'integer' ),
						'page_id'     => array( 'type' => 'integer' ),
						'edit_url'    => array( 'type' => 'string' ),
						'preview_url' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'exec_create_demo' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_theme_options' ) && current_user_can( 'publish_posts' );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);

		// 4. Validate that a post actually has a working sticky header.
		wp_register_ability(
			'sticky-header-effects/validate-demo',
			array(
				'label'               => __( 'Validate sticky header demo', 'she-header' ),
				'description'         => __( 'Checks an Elementor post and reports whether a sticky header is correctly wired up, which effects are configured, and any warnings. Read-only self-check used to confirm a demo was built correctly.', 'she-header' ),
				'category'            => self::CATEGORY,
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'ID of the Elementor post/template to inspect.', 'she-header' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'is_sticky'        => array( 'type' => 'boolean' ),
						'element_id'       => array( 'type' => 'string' ),
						'effects_detected' => array( 'type' => 'array' ),
						'warnings'         => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => array( __CLASS__, 'exec_validate_demo' ),
				'permission_callback' => function ( $input ) {
					$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
					return $post_id && current_user_can( 'edit_post', $post_id );
				},
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Shared source of truth — settings catalogue
	 * ------------------------------------------------------------------- */

	/**
	 * The Free sticky-effect settings catalogue.
	 *
	 * Mirrors the controls registered in the transparent module and is the
	 * single source of truth for both `list-effects` and the allowed-key
	 * filter used when applying settings. Keep in sync with
	 * {@see \SheHeader\Modules\Transparent\Module::register_controls()}.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function get_setting_schema() {
		return array(
			'transparent'                 => array(
				'label'   => __( 'Enable sticky effects', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => 'yes',
			),
			'transparent_on'              => array(
				'label'   => __( 'Enable on devices', 'she-header' ),
				'type'    => 'devices',
				'options' => array( 'desktop', 'tablet', 'mobile' ),
				'default' => array( 'desktop', 'tablet', 'mobile' ),
			),
			'scroll_distance'             => array(
				'label'   => __( 'Scroll distance (px)', 'she-header' ),
				'type'    => 'slider',
				'unit'    => 'px',
				'default' => array( 'size' => 60, 'unit' => 'px' ),
			),
			'transparent_header_show'     => array(
				'label'   => __( 'Transparent header (before scroll)', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'she_offset_top'              => array(
				'label' => __( 'Offset top', 'she-header' ),
				'type'  => 'slider',
				'unit'  => 'px',
			),
			'she_width'                   => array(
				'label' => __( 'Width', 'she-header' ),
				'type'  => 'slider',
				'unit'  => '%',
			),
			'background_show'             => array(
				'label'   => __( 'Background colour on sticky', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'background_type'             => array(
				'label'   => __( 'Background type', 'she-header' ),
				'type'    => 'select',
				'options' => array( 'classic', 'gradient' ),
				'default' => 'classic',
			),
			'background'                  => array(
				'label' => __( 'Background colour', 'she-header' ),
				'type'  => 'color',
			),
			'color_b'                     => array(
				'label' => __( 'Gradient second colour', 'she-header' ),
				'type'  => 'color',
			),
			'shrink_header'               => array(
				'label'   => __( 'Shrink header on sticky', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'custom_height_header'        => array(
				'label' => __( 'Shrunk header height', 'she-header' ),
				'type'  => 'slider',
				'unit'  => 'px',
			),
			'shrink_header_logo'          => array(
				'label'   => __( 'Shrink logo on sticky', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'custom_height_header_logo'   => array(
				'label' => __( 'Shrunk logo height', 'she-header' ),
				'type'  => 'slider',
				'unit'  => 'px',
			),
			'change_logo_color'           => array(
				'label'   => __( 'Change logo colour on sticky', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'blur_bg'                     => array(
				'label'   => __( 'Blur background on sticky', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'blur_bg_blur_amount'         => array(
				'label' => __( 'Blur amount', 'she-header' ),
				'type'  => 'slider',
				'unit'  => 'px',
			),
			'blur_bg_saturate_amount'     => array(
				'label' => __( 'Saturate amount', 'she-header' ),
				'type'  => 'slider',
				'unit'  => '%',
			),
			'bottom_border'               => array(
				'label'   => __( 'Bottom border on sticky', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'custom_bottom_border_color'  => array(
				'label' => __( 'Bottom border colour', 'she-header' ),
				'type'  => 'color',
			),
			'custom_bottom_border_width'  => array(
				'label' => __( 'Bottom border width', 'she-header' ),
				'type'  => 'slider',
				'unit'  => 'px',
			),
			'bottom_shadow'               => array(
				'label'   => __( 'Bottom shadow on sticky', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'bottom_shadow_color'         => array(
				'label' => __( 'Bottom shadow colour', 'she-header' ),
				'type'  => 'color',
			),
			'hide_header'                 => array(
				'label'   => __( 'Hide header on scroll down', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'scroll_distance_hide_header' => array(
				'label' => __( 'Hide-on-scroll distance', 'she-header' ),
				'type'  => 'slider',
				'unit'  => 'px',
			),
			'she_totop_enable'            => array(
				'label'   => __( 'Back to Top button', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'she_totop_position'          => array(
				'label'   => __( 'Back to Top position', 'she-header' ),
				'type'    => 'select',
				'options' => array( 'right', 'left' ),
				'default' => 'right',
			),
			'she_totop_display_after'     => array(
				'label'   => __( 'Back to Top: show after (px scrolled)', 'she-header' ),
				'type'    => 'slider',
				'unit'    => 'px',
				'default' => array( 'size' => 300, 'unit' => 'px' ),
			),
			'she_totop_size'              => array(
				'label'   => __( 'Back to Top button size', 'she-header' ),
				'type'    => 'slider',
				'unit'    => 'px',
				'default' => array( 'size' => 44, 'unit' => 'px' ),
			),
			'she_totop_offset'            => array(
				'label'   => __( 'Back to Top: distance from edge', 'she-header' ),
				'type'    => 'slider',
				'unit'    => 'px',
				'default' => array( 'size' => 24, 'unit' => 'px' ),
			),
			'she_totop_radius'            => array(
				'label'   => __( 'Back to Top border radius', 'she-header' ),
				'type'    => 'slider',
				'unit'    => '%',
				'default' => array( 'size' => 50, 'unit' => '%' ),
			),
			'she_totop_icon_color'        => array(
				'label'   => __( 'Back to Top icon colour', 'she-header' ),
				'type'    => 'color',
				'default' => '#ffffff',
			),
			'she_totop_bg'                => array(
				'label'   => __( 'Back to Top background colour', 'she-header' ),
				'type'    => 'color',
				'default' => '#9d1a4f',
			),
			'she_totop_bg_hover'          => array(
				'label' => __( 'Back to Top background (hover)', 'she-header' ),
				'type'  => 'color',
			),
			'she_anchor_offset'           => array(
				'label'   => __( 'Fix anchor scroll', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
			'she_anchor_offset_extra'     => array(
				'label'   => __( 'Anchor scroll extra offset', 'she-header' ),
				'type'    => 'slider',
				'unit'    => 'px',
				'default' => array( 'size' => 0, 'unit' => 'px' ),
			),
			'she_anchor_smooth'           => array(
				'label'   => __( 'Anchor smooth scroll', 'she-header' ),
				'type'    => 'switch',
				'enum'    => array( 'yes', '' ),
				'default' => '',
			),
		);
	}

	/**
	 * Setting keys that {@see apply_settings_to_post()} is allowed to write.
	 *
	 * Pro extends this via the `she_abilities_allowed_setting_keys` filter so
	 * Pro effect keys flow through the same validated apply path.
	 *
	 * @return string[]
	 */
	public static function get_allowed_setting_keys() {
		$keys = array_keys( self::get_setting_schema() );

		/**
		 * Filter the setting keys abilities may write into Elementor data.
		 *
		 * @since 2.2.0
		 *
		 * @param string[] $keys Allowed setting keys.
		 */
		return (array) apply_filters( 'she_abilities_allowed_setting_keys', $keys );
	}

	/* ---------------------------------------------------------------------
	 * Shared apply logic (Pro reuses this)
	 * ------------------------------------------------------------------- */

	/**
	 * Merge sticky settings into a target element of an Elementor post.
	 *
	 * Enables the sticky effect on the element and merges the (validated)
	 * settings into it. Used by both `apply-settings` and the Pro
	 * `apply-pro-effect` ability so there is one writer for `_elementor_data`.
	 *
	 * @param int    $post_id    Elementor post/template id.
	 * @param array  $settings   Raw settings map (filtered against allowed keys).
	 * @param string $element_id Optional target element id; defaults to first top-level element.
	 *
	 * @return array|\WP_Error { success, element_id, applied_keys } or error.
	 */
	public static function apply_settings_to_post( $post_id, array $settings, $element_id = '' ) {
		$post_id = (int) $post_id;
		$post    = get_post( $post_id );

		if ( ! $post ) {
			return new \WP_Error( 'she_invalid_post', __( 'Post not found.', 'she-header' ) );
		}

		$data = self::get_elementor_data( $post_id );

		if ( empty( $data ) ) {
			return new \WP_Error(
				'she_no_elementor_data',
				__( 'This post has no Elementor content yet. Use create-demo instead, or build the header in the editor first.', 'she-header' )
			);
		}

		$clean = self::prepare_settings( $settings );
		// Always turn the sticky effect on when applying settings.
		$clean['transparent'] = 'yes';

		$found_id = '';
		$ok       = self::merge_into_element( $data, $clean, $element_id, $found_id );

		if ( ! $ok ) {
			return new \WP_Error( 'she_element_not_found', __( 'Target element not found in this post.', 'she-header' ) );
		}

		self::save_elementor_data( $post_id, $data );

		return array(
			'success'      => true,
			'element_id'   => $found_id,
			'applied_keys' => array_keys( $clean ),
		);
	}

	/**
	 * Filter + sanitise an incoming settings map against the allowed keys.
	 *
	 * @param array $settings Raw settings.
	 * @return array Clean settings ready to write.
	 */
	public static function prepare_settings( array $settings ) {
		$allowed = array_flip( self::get_allowed_setting_keys() );
		$clean   = array_intersect_key( $settings, $allowed );

		return self::sanitize_value( $clean );
	}

	/**
	 * Recursively sanitise a setting value (string, number, or nested array).
	 *
	 * @param mixed $value Value to sanitise.
	 * @return mixed
	 */
	protected static function sanitize_value( $value ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $k => $v ) {
				$key         = is_string( $k ) ? sanitize_key( $k ) : $k;
				$out[ $key ] = self::sanitize_value( $v );
			}
			return $out;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		return sanitize_text_field( (string) $value );
	}

	/* ---------------------------------------------------------------------
	 * Elementor data helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Read and decode a post's Elementor data.
	 *
	 * @param int $post_id Post id.
	 * @return array Decoded Elementor element tree (empty array if none).
	 */
	public static function get_elementor_data( $post_id ) {
		$raw = get_post_meta( (int) $post_id, '_elementor_data', true );

		if ( empty( $raw ) ) {
			return array();
		}

		if ( is_array( $raw ) ) {
			return $raw;
		}

		$decoded = json_decode( $raw, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Persist an Elementor element tree back to a post and clear its CSS cache.
	 *
	 * @param int   $post_id Post id.
	 * @param array $data    Element tree.
	 * @return void
	 */
	public static function save_elementor_data( $post_id, array $data ) {
		$post_id = (int) $post_id;

		// Elementor stores `_elementor_data` as a slashed JSON string.
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );

		if ( ! get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );
		}

		if ( defined( 'ELEMENTOR_VERSION' ) ) {
			update_post_meta( $post_id, '_elementor_version', ELEMENTOR_VERSION );
		}

		// Regenerate CSS so the change is visible without opening the editor.
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}

	/**
	 * Merge settings into a matching element, searching the tree recursively.
	 *
	 * @param array  $elements   Element tree (passed by reference).
	 * @param array  $settings   Settings to merge.
	 * @param string $element_id Target id, or '' for the first top-level element.
	 * @param string $found_id   Receives the id of the element that was updated.
	 * @return bool True if an element was updated.
	 */
	protected static function merge_into_element( array &$elements, array $settings, $element_id, &$found_id ) {
		if ( empty( $elements ) ) {
			return false;
		}

		// No specific target: update the first top-level element.
		if ( '' === $element_id ) {
			$elements[0]['settings'] = array_merge(
				isset( $elements[0]['settings'] ) && is_array( $elements[0]['settings'] ) ? $elements[0]['settings'] : array(),
				$settings
			);
			$found_id                = isset( $elements[0]['id'] ) ? (string) $elements[0]['id'] : '';
			return true;
		}

		foreach ( $elements as &$element ) {
			if ( isset( $element['id'] ) && (string) $element['id'] === (string) $element_id ) {
				$element['settings'] = array_merge(
					isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array(),
					$settings
				);
				$found_id            = (string) $element['id'];
				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( self::merge_into_element( $element['elements'], $settings, $element_id, $found_id ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Generate an Elementor-style 7-char element id.
	 *
	 * @return string
	 */
	protected static function generate_id() {
		return substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 7 );
	}

	/* ---------------------------------------------------------------------
	 * Execute callbacks
	 * ------------------------------------------------------------------- */

	/**
	 * `list-effects` — return the effect catalogue.
	 *
	 * @param array $input Unused.
	 * @return array
	 */
	public static function exec_list_effects( $input = array() ) {
		$effects = array();

		foreach ( self::get_setting_schema() as $key => $meta ) {
			$effects[ $key ] = array_merge(
				array( 'key' => $key, 'tier' => 'free', 'applies_to' => array( 'section', 'container' ) ),
				$meta
			);
		}

		/**
		 * Filter the effect catalogue returned by list-effects.
		 *
		 * @since 2.2.0
		 *
		 * @param array $effects Catalogue keyed by setting name.
		 */
		$effects = (array) apply_filters( 'she_abilities_effects_catalog', $effects );

		return array(
			'count'   => count( $effects ),
			'effects' => $effects,
		);
	}

	/**
	 * `apply-settings` — apply settings to an existing post.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function exec_apply_settings( $input ) {
		$post_id    = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;
		$element_id = isset( $input['element_id'] ) ? (string) $input['element_id'] : '';
		$settings   = isset( $input['settings'] ) && is_array( $input['settings'] ) ? $input['settings'] : array();

		if ( ! $post_id ) {
			return new \WP_Error( 'she_missing_post_id', __( 'A post_id is required.', 'she-header' ) );
		}

		return self::apply_settings_to_post( $post_id, $settings, $element_id );
	}

	/**
	 * `create-demo` — build a header template (and optional page) with effects.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function exec_create_demo( $input ) {
		$demo_name  = isset( $input['demo_name'] ) ? sanitize_text_field( (string) $input['demo_name'] ) : '';
		$effects    = isset( $input['effects'] ) && is_array( $input['effects'] ) ? $input['effects'] : array();
		$brand      = isset( $input['brand'] ) ? sanitize_text_field( (string) $input['brand'] ) : get_bloginfo( 'name' );
		$with_page  = ! empty( $input['with_page'] );
		$menu_items = isset( $input['menu_items'] ) && is_array( $input['menu_items'] )
			? array_map( 'sanitize_text_field', $input['menu_items'] )
			: array( __( 'Home', 'she-header' ), __( 'About', 'she-header' ), __( 'Services', 'she-header' ), __( 'Contact', 'she-header' ) );

		if ( '' === $demo_name ) {
			return new \WP_Error( 'she_missing_demo_name', __( 'A demo_name is required.', 'she-header' ) );
		}

		// Create the header template (mirrors the dashboard's she_create_page()).
		$template_id = wp_insert_post(
			array(
				'post_type'   => 'elementor_library',
				'post_title'  => $demo_name,
				'post_status' => 'publish',
			)
		);

		if ( ! $template_id || is_wp_error( $template_id ) ) {
			return new \WP_Error( 'she_template_failed', __( 'Could not create the header template.', 'she-header' ) );
		}

		update_post_meta( $template_id, '_elementor_template_type', 'header' );

		// Build a minimal, valid header: container (brand + inline nav list),
		// with the sticky effect enabled and the requested effects baked in.
		$settings = self::prepare_settings( $effects );
		$settings = array_merge(
			array(
				'content_width'        => 'full',
				'flex_direction'       => 'row',
				'flex_justify_content' => 'space-between',
				'flex_align_items'     => 'center',
				'transparent'          => 'yes',
				'transparent_on'       => array( 'desktop', 'tablet', 'mobile' ),
			),
			$settings
		);

		$nav_list = array();
		foreach ( $menu_items as $item ) {
			$nav_list[] = array(
				'_id'  => self::generate_id(),
				'text' => $item,
			);
		}

		$data = array(
			array(
				'id'       => self::generate_id(),
				'elType'   => 'container',
				'settings' => $settings,
				'elements' => array(
					array(
						'id'         => self::generate_id(),
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title'       => $brand,
							'header_size' => 'h2',
						),
						'elements'   => array(),
					),
					array(
						'id'         => self::generate_id(),
						'elType'     => 'widget',
						'widgetType' => 'icon-list',
						'settings'   => array(
							'view'      => 'inline',
							'icon_list' => $nav_list,
						),
						'elements'   => array(),
					),
				),
				'isInner'  => false,
			),
		);

		self::save_elementor_data( $template_id, $data );

		$result = array(
			'template_id' => (int) $template_id,
			'edit_url'    => admin_url( 'post.php?post=' . $template_id . '&action=elementor' ),
			'preview_url' => get_preview_post_link( $template_id ),
		);

		// Optionally create a demo page that uses this header.
		if ( $with_page ) {
			$page_id = wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_title'  => $demo_name . ' ' . __( 'Demo', 'she-header' ),
					'post_status' => 'draft',
				)
			);

			if ( $page_id && ! is_wp_error( $page_id ) ) {
				$result['page_id']     = (int) $page_id;
				$result['page_edit_url'] = admin_url( 'post.php?post=' . $page_id . '&action=elementor' );
			}
		}

		return $result;
	}

	/**
	 * `validate-demo` — report whether a post has a working sticky header.
	 *
	 * @param array $input Ability input.
	 * @return array|\WP_Error
	 */
	public static function exec_validate_demo( $input ) {
		$post_id = isset( $input['post_id'] ) ? (int) $input['post_id'] : 0;

		if ( ! $post_id ) {
			return new \WP_Error( 'she_missing_post_id', __( 'A post_id is required.', 'she-header' ) );
		}

		$data = self::get_elementor_data( $post_id );

		if ( empty( $data ) ) {
			return array(
				'is_sticky'        => false,
				'element_id'       => '',
				'effects_detected' => array(),
				'warnings'         => array( __( 'No Elementor data found on this post.', 'she-header' ) ),
			);
		}

		$known    = self::get_setting_schema();
		$found_id = '';
		$detected = array();
		$is_sticky = self::scan_for_sticky( $data, $known, $found_id, $detected );

		$warnings = array();
		if ( ! $is_sticky ) {
			$warnings[] = __( 'No element has the sticky effect enabled (transparent = yes).', 'she-header' );
		}

		return array(
			'is_sticky'        => $is_sticky,
			'element_id'       => $found_id,
			'effects_detected' => array_values( array_unique( $detected ) ),
			'warnings'         => $warnings,
		);
	}

	/**
	 * Recursively scan the element tree for a sticky-enabled element.
	 *
	 * @param array  $elements Element tree.
	 * @param array  $known    Known setting keys (schema).
	 * @param string $found_id Receives the sticky element id.
	 * @param array  $detected Receives the list of configured effect keys.
	 * @return bool
	 */
	protected static function scan_for_sticky( array $elements, array $known, &$found_id, array &$detected ) {
		foreach ( $elements as $element ) {
			$settings = isset( $element['settings'] ) && is_array( $element['settings'] ) ? $element['settings'] : array();

			if ( isset( $settings['transparent'] ) && 'yes' === $settings['transparent'] ) {
				$found_id = isset( $element['id'] ) ? (string) $element['id'] : '';

				foreach ( $settings as $key => $value ) {
					if ( isset( $known[ $key ] ) && 'transparent' !== $key && '' !== $value && array() !== $value ) {
						$detected[] = $key;
					}
				}

				return true;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				if ( self::scan_for_sticky( $element['elements'], $known, $found_id, $detected ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
