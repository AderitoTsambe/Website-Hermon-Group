<?php
namespace SheHeader\Base;

use SheHeader\Plugin;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

abstract class Module_Base {

	/**
	 * @var \ReflectionClass
	 */
	private $reflection;

	/**
	 * @var Module_Base
	 */
	protected static $_instances = [];

	/**
	 * Throw error on object clone
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object therefore, we don't want the object to be cloned.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'she-header' ), '1.0.0' );
	}

	/**
	 * Disable unserializing of the class
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cheatin&#8217; huh?', 'she-header' ), '1.0.0' );
	}

	public static function is_active() {
		return true;
	}

	public static function class_name() {
		return get_called_class();
	}

	/**
	 * @return static
	 */
	public static function instance() {
		if ( empty( static::$_instances[ static::class_name() ] ) ) {
			static::$_instances[ static::class_name() ] = new static();
		}

		return static::$_instances[ static::class_name() ];
	}

	abstract public function get_name();

	public function get_widgets() {
		return [];
	}

	public function __construct() {
		$this->reflection = new \ReflectionClass( $this );

		add_action( 'elementor/widgets/register', [ $this, 'init_widgets' ] );
	}

	public function init_widgets() {
		$widget_manager = Plugin::elementor()->widgets_manager;

		foreach ( $this->get_widgets() as $widget ) {
			$class_name = $this->reflection->getNamespaceName() . '\Widgets\\' . $widget;
			$widget_manager->register( new $class_name() );
		}
	}

	/**
	 * Detect the Elementor editor or preview context.
	 *
	 * Shared by feature modules so editor/preview detection is consistent
	 * and null-safe across all of them.
	 *
	 * @since 2.2.0
	 * @return bool True in the Elementor editor or preview, false otherwise.
	 */
	protected function is_editor_or_preview() {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return false;
		}

		$ep = \Elementor\Plugin::instance();

		if ( isset( $ep->editor ) && $ep->editor->is_edit_mode() ) {
			return true;
		}

		if ( isset( $ep->preview ) && method_exists( $ep->preview, 'is_preview_mode' ) && $ep->preview->is_preview_mode() ) {
			return true;
		}

		return false;
	}
}
