<?php
/**
 * Colour tokens: inherit the theme by default, win over it when configured.
 *
 * Two separate layers make those two goals compatible rather than
 * contradictory:
 *
 *  - The VALUE layer (which colour) is a var() fallback chain living on the
 *    plugin root. It is deliberately low-specificity and reads Elementor's
 *    global colours and theme.json presets, so an untouched install simply
 *    looks like the theme.
 *  - The APPLICATION layer (which element gets it) lives in the stylesheet at
 *    a specificity themes cannot casually beat.
 *
 * Precedence, highest first:
 *   1. Elementor widget style control  -> --rhot-*-ovr via {{WRAPPER}}
 *   2. Plugin settings (Design tab)    -> --rhot-*-ovr via wp_add_inline_style
 *   3. Elementor kit global colours    -> --e-global-color-*
 *   4. theme.json palette presets      -> --wp--preset--color--*
 *   5. Hard fallback literal
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Styles {

	/**
	 * Setting key => [ CSS variable base, Elementor global id, theme.json preset slug, hard fallback ].
	 */
	private static function token_map() {
		return array(
			'color_primary'    => array( 'primary', 'primary', 'primary', '#2563eb' ),
			'color_on_primary' => array( 'on-primary', '', '', '#ffffff' ),
			'color_text'       => array( 'text', 'text', 'foreground', '#1f2937' ),
			'color_muted'      => array( 'muted', '', '', '#6b7280' ),
			'color_surface'    => array( 'surface', '', 'base', '#ffffff' ),
			'color_border'     => array( 'border', '', '', '#e5e7eb' ),
			'color_success'    => array( 'success', '', '', '#15803d' ),
			'color_danger'     => array( 'danger', '', '', '#b91c1c' ),
		);
	}

	/**
	 * The fallback chain, emitted once on the plugin root.
	 *
	 * The Elementor and theme.json links are only written when those systems
	 * are actually present, so we never reference a variable that can't
	 * resolve on this site.
	 */
	public static function build_token_css() {
		$has_elementor  = did_action( 'elementor/loaded' ) && class_exists( '\Elementor\Plugin' );
		$has_theme_json = function_exists( 'wp_theme_has_theme_json' ) && wp_theme_has_theme_json();

		$lines = array();

		foreach ( self::token_map() as $map ) {
			list( $var, $elementor_id, $preset, $fallback ) = $map;

			$chain = $fallback;

			if ( $has_theme_json && '' !== $preset ) {
				$chain = sprintf( 'var(--wp--preset--color--%s, %s)', $preset, $chain );
			}

			if ( $has_elementor && '' !== $elementor_id ) {
				$chain = sprintf( 'var(--e-global-color-%s, %s)', $elementor_id, $chain );
			}

			$lines[] = sprintf( '--rhot-%1$s: var(--rhot-%1$s-ovr, %2$s);', $var, $chain );
		}

		$lines[] = '--rhot-radius: var(--rhot-radius-ovr, 10px);';

		return '.rhot-track{' . implode( '', $lines ) . '}';
	}

	/**
	 * The admin's explicit choices, written as the highest-priority link of
	 * the chain. Emitted only when Color source is "custom" and only for
	 * colours actually filled in, so a half-configured Design tab still
	 * inherits the rest from the theme.
	 */
	public static function build_override_css() {
		if ( 'custom' !== RHOT_Settings::get_value( 'color_source' ) ) {
			return '';
		}

		$settings = RHOT_Settings::get();
		$lines    = array();

		foreach ( self::token_map() as $key => $map ) {
			if ( empty( $settings[ $key ] ) ) {
				continue;
			}

			$lines[] = sprintf( '--rhot-%s-ovr: %s;', $map[0], $settings[ $key ] );
		}

		$radius = trim( (string) $settings['radius'] );

		if ( '' !== $radius ) {
			// Bare numbers are the common admin mistake; treat them as px.
			if ( is_numeric( $radius ) ) {
				$radius .= 'px';
			}

			$lines[] = sprintf( '--rhot-radius-ovr: %s;', $radius );
		}

		if ( empty( $lines ) ) {
			return '';
		}

		return '.rhot-track{' . implode( '', $lines ) . '}';
	}

	/**
	 * Per-status accent colours, emitted as their own variables so the
	 * timeline and the status badge can both use them without inline styles
	 * on every element.
	 */
	public static function build_status_css() {
		$colors = (array) RHOT_Settings::get_value( 'status_colors' );

		$lines = array();

		foreach ( $colors as $slug => $color ) {
			$color = RHOT_Settings::sanitize_color( $color );

			if ( '' === $color ) {
				continue;
			}

			$lines[] = sprintf( '.rhot-track .rhot-status--%s{--rhot-status: %s;}', sanitize_html_class( $slug ), $color );
		}

		return implode( '', $lines );
	}

	public static function build_inline_css() {
		return self::build_token_css() . self::build_override_css() . self::build_status_css();
	}

	/**
	 * Elementor's active kit colours, for the admin screen only: they let the
	 * Design tab tell the admin exactly which colour it is currently
	 * inheriting and prefill the pickers with it.
	 *
	 * Never called on a front-end request — get_active_kit() instantiates a
	 * full Kit document and runs the entire controls stack.
	 */
	public static function get_elementor_colors() {
		$colors = array();

		if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
			return $colors;
		}

		try {
			$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

			if ( ! $kit ) {
				return $colors;
			}

			// Only system_colors have stable ids (primary, secondary, text,
			// accent); custom_colors are keyed by random hashes.
			foreach ( (array) $kit->get_settings( 'system_colors' ) as $row ) {
				if ( isset( $row['_id'], $row['color'] ) ) {
					$colors[ $row['_id'] ] = $row['color'];
				}
			}
		} catch ( \Throwable $e ) {
			// Fail safe: the Design tab just shows no inheritance hint.
			return array();
		}

		return $colors;
	}

	/**
	 * theme.json palette, flattened.
	 *
	 * wp_get_global_settings() returns colour palettes keyed by ORIGIN
	 * ( default / theme / custom ), and core supplies a `default` palette on
	 * every site — including classic themes with no theme.json at all. Only
	 * `theme` and `custom` are real signal about the active design.
	 */
	public static function get_theme_json_colors() {
		$flat = array();

		if ( ! function_exists( 'wp_get_global_settings' ) || ! function_exists( 'wp_theme_has_theme_json' ) ) {
			return $flat;
		}

		if ( ! wp_theme_has_theme_json() ) {
			return $flat;
		}

		$palette = wp_get_global_settings( array( 'color', 'palette' ) );

		if ( ! is_array( $palette ) ) {
			return $flat;
		}

		foreach ( array( 'theme', 'custom' ) as $origin ) {
			if ( empty( $palette[ $origin ] ) || ! is_array( $palette[ $origin ] ) ) {
				continue;
			}

			foreach ( $palette[ $origin ] as $item ) {
				if ( isset( $item['slug'], $item['color'] ) ) {
					$flat[ $item['slug'] ] = $item['color'];
				}
			}
		}

		return $flat;
	}

	/**
	 * What the front end would currently resolve a token to, for the admin
	 * preview. Mirrors the CSS chain's priority order.
	 */
	public static function describe_inherited( $setting_key ) {
		$map = self::token_map();

		if ( ! isset( $map[ $setting_key ] ) ) {
			return array();
		}

		list( , $elementor_id, $preset, $fallback ) = $map[ $setting_key ];

		if ( '' !== $elementor_id ) {
			$elementor = self::get_elementor_colors();

			if ( ! empty( $elementor[ $elementor_id ] ) ) {
				return array(
					'color'  => $elementor[ $elementor_id ],
					'source' => __( 'Elementor global colour', 'rh-order-track' ),
				);
			}
		}

		if ( '' !== $preset ) {
			$theme = self::get_theme_json_colors();

			if ( ! empty( $theme[ $preset ] ) ) {
				return array(
					'color'  => $theme[ $preset ],
					'source' => __( 'Theme palette (theme.json)', 'rh-order-track' ),
				);
			}
		}

		return array(
			'color'  => $fallback,
			'source' => __( 'Plugin default', 'rh-order-track' ),
		);
	}
}
