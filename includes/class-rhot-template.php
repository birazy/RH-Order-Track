<?php
/**
 * Theme-overridable template loading.
 *
 * Any template shipped in this plugin's templates/ directory can be replaced
 * by dropping a file of the same name into `yourtheme/rh-order-track/`.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Template {

	const THEME_DIR = 'rh-order-track';

	/**
	 * locate_template() already walks child theme then parent theme, so the
	 * only thing left to do is fall back to the plugin's own copy.
	 */
	public static function locate( $name ) {
		$name = ltrim( (string) $name, '/' );

		$template = locate_template(
			array(
				trailingslashit( self::THEME_DIR ) . $name,
				$name,
			)
		);

		if ( ! $template ) {
			$template = RHOT_PLUGIN_DIR . 'templates/' . $name;
		}

		/**
		 * Filters the resolved template path.
		 *
		 * @param string $template Absolute path.
		 * @param string $name     Template file name.
		 */
		return apply_filters( 'rhot_locate_template', $template, $name );
	}

	public static function render( $name, $args = array() ) {
		$located = self::locate( $name );

		if ( ! $located || ! file_exists( $located ) ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Template
		// args are built entirely by this plugin, never from user input.
		extract( (array) $args );

		include $located;
	}

	public static function get_html( $name, $args = array() ) {
		ob_start();

		self::render( $name, $args );

		return ob_get_clean();
	}
}
