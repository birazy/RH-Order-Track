<?php
/**
 * [rh_order_track] shortcode.
 *
 * Also owns the form-argument builder shared with the Elementor widget, so
 * both entry points render byte-identical markup through the same template.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Shortcode {

	const TAG = 'rh_order_track';

	private static $instance = null;

	private static $sequence = 0;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	public function render( $atts ) {
		$atts = shortcode_atts( self::default_atts(), $atts, self::TAG );

		return self::render_form( $atts );
	}

	/**
	 * Every attribute defaults to an empty string, which means "inherit the
	 * global setting". Only what the user actually typed overrides anything.
	 */
	public static function default_atts() {
		return array(
			'mode'         => '',
			'title'        => '',
			'subtitle'     => '',
			'button_text'  => '',
			'layout'       => '',
			'order_label'  => '',
			'order_ph'     => '',
			'phone_label'  => '',
			'phone_ph'     => '',
			'email_label'  => '',
			'email_ph'     => '',
			'show_order'   => '',
			'show_phone'   => '',
			'show_email'   => '',
			'class'        => '',
		);
	}

	public static function render_form( $atts, $preview = false ) {
		if ( ! RHOT_Compat::has_woocommerce() ) {
			return '';
		}

		RHOT_Assets::enqueue();

		return RHOT_Template::get_html( 'form.php', self::build_args( $atts, $preview ) );
	}

	/**
	 * Builds the template arguments from per-instance overrides layered on
	 * top of the global settings.
	 *
	 * @param array $atts    Per-instance overrides; '' means inherit.
	 * @param bool  $preview True inside the Elementor editor, where a static
	 *                       sample result is rendered so the design can be
	 *                       styled without performing a real lookup.
	 */
	public static function build_args( $atts, $preview = false ) {
		$atts = wp_parse_args( $atts, self::default_atts() );

		$mode = $atts['mode'];

		if ( ! in_array( $mode, RHOT_Settings::MATCH_MODES, true ) ) {
			$mode = RHOT_Settings::get_value( 'match_mode' );
		}

		$layout = in_array( $atts['layout'], array( 'stacked', 'inline' ), true )
			? $atts['layout']
			: RHOT_Settings::get_value( 'form_layout' );

		self::$sequence++;

		$classes = array( 'rhot-track', 'rhot-scope', 'rhot-layout--' . $layout );

		if ( RHOT_Settings::is_on( 'force_styles' ) ) {
			$classes[] = 'rhot-force';
		}

		if ( '' !== trim( (string) $atts['class'] ) ) {
			$classes[] = sanitize_html_class( $atts['class'] );
		}

		return array(
			'id'          => 'rhot-' . self::$sequence,
			'classes'     => implode( ' ', $classes ),
			'mode'        => $mode,
			'title'       => RHOT_Settings::resolve( $atts['title'], 'form_title' ),
			'subtitle'    => RHOT_Settings::resolve( $atts['subtitle'], 'form_subtitle' ),
			'button_text' => self::text( RHOT_Settings::resolve( $atts['button_text'], 'button_text' ), __( 'Track order', 'rh-order-track' ) ),
			'fields'      => self::build_fields( $atts, $mode ),
			'nonce'       => wp_create_nonce( RHOT_Ajax::NONCE_ACTION ),
			'preview'     => (bool) $preview,
		);
	}

	private static function build_fields( $atts, $mode ) {
		$fields = array();

		// The match mode decides which fields are meaningful at all; the
		// per-field toggles can then hide one further. A field the mode
		// requires is never hidden by a stale toggle.
		$order_on = self::enabled( $atts['show_order'], 'field_order_enabled' ) && 'phone_only' !== $mode;
		$phone_on = self::enabled( $atts['show_phone'], 'field_phone_enabled' ) && 'order_only' !== $mode;
		$email_on = self::enabled( $atts['show_email'], 'field_email_enabled' ) && ! in_array( $mode, array( 'order_only', 'phone_only' ), true );

		if ( 'both' === $mode || 'order_only' === $mode ) {
			$order_on = true;
		}

		if ( 'both' === $mode || 'phone_only' === $mode ) {
			$phone_on = true;
		}

		if ( $order_on ) {
			$fields[] = array(
				'key'         => 'rhot_order',
				'type'        => 'text',
				'inputmode'   => 'numeric',
				'label'       => self::text( RHOT_Settings::resolve( $atts['order_label'], 'field_order_label' ), __( 'Order number', 'rh-order-track' ) ),
				'placeholder' => self::text( RHOT_Settings::resolve( $atts['order_ph'], 'field_order_ph' ), __( 'e.g. 1042', 'rh-order-track' ) ),
				'required'    => in_array( $mode, array( 'both', 'order_only' ), true ),
				'autocomplete' => 'off',
			);
		}

		if ( $phone_on ) {
			$fields[] = array(
				'key'         => 'rhot_phone',
				'type'        => 'tel',
				'inputmode'   => 'tel',
				'label'       => self::text( RHOT_Settings::resolve( $atts['phone_label'], 'field_phone_label' ), __( 'Phone number', 'rh-order-track' ) ),
				'placeholder' => self::text( RHOT_Settings::resolve( $atts['phone_ph'], 'field_phone_ph' ), __( 'The number you ordered with', 'rh-order-track' ) ),
				'required'    => in_array( $mode, array( 'both', 'phone_only' ), true ),
				'autocomplete' => 'tel',
			);
		}

		if ( $email_on ) {
			$fields[] = array(
				'key'         => 'rhot_email',
				'type'        => 'email',
				'inputmode'   => 'email',
				'label'       => self::text( RHOT_Settings::resolve( $atts['email_label'], 'field_email_label' ), __( 'Email address', 'rh-order-track' ) ),
				'placeholder' => self::text( RHOT_Settings::resolve( $atts['email_ph'], 'field_email_ph' ), __( 'Optional', 'rh-order-track' ) ),
				'required'    => false,
				'autocomplete' => 'email',
			);
		}

		return $fields;
	}

	/**
	 * A per-instance switcher: 'yes'/'no' overrides, anything else inherits.
	 */
	private static function enabled( $override, $key ) {
		if ( 'yes' === $override ) {
			return true;
		}

		if ( 'no' === $override ) {
			return false;
		}

		return RHOT_Settings::is_on( $key );
	}

	private static function text( $value, $fallback ) {
		$value = trim( (string) $value );

		return '' !== $value ? $value : $fallback;
	}
}
