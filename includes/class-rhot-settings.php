<?php
/**
 * Settings storage: one serialized option array, one sanitize callback.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Settings {

	const OPTION_KEY   = 'rhot_settings';
	const OPTION_GROUP = 'rhot_settings_group';

	const MATCH_MODES = array( 'both', 'either', 'order_only', 'phone_only' );

	/**
	 * Statuses that must never be shown to a customer regardless of settings —
	 * these are internal/administrative states, not stages of a real order.
	 */
	const NEVER_VISIBLE_STATUSES = array( 'trash', 'draft', 'auto-draft', 'checkout-draft' );

	public static function defaults() {
		return array(
			// --- Lookup -------------------------------------------------
			'match_mode'            => 'both',
			'max_results'           => 3,
			'enable_index'          => 'yes',
			'enable_fallbacks'      => 'yes',
			'rate_limit_max'        => 20,
			'rate_limit_window'     => 900,
			'url_prefill'           => 'no',

			// --- Form ---------------------------------------------------
			'form_layout'           => 'stacked',
			'form_title'            => '',
			'form_subtitle'         => '',
			'button_text'           => '',
			'field_order_enabled'   => 'yes',
			'field_order_label'     => '',
			'field_order_ph'        => '',
			'field_phone_enabled'   => 'yes',
			'field_phone_label'     => '',
			'field_phone_ph'        => '',
			'field_email_enabled'   => 'no',
			'field_email_label'     => '',
			'field_email_ph'        => '',
			'not_found_text'        => '',

			// --- Statuses -----------------------------------------------
			'visible_statuses'      => array( 'pending', 'processing', 'on-hold', 'completed', 'cancelled', 'refunded', 'failed' ),
			'timeline_statuses'     => array( 'pending', 'processing', 'completed' ),
			'status_labels'         => array(),
			'status_colors'         => array(),
			'hidden_status_action'  => 'generic',
			'hidden_status_text'    => '',

			// --- Order details ------------------------------------------
			'show_timeline'         => 'yes',
			'show_order_number'     => 'yes',
			'show_date'             => 'yes',
			'show_status'           => 'yes',
			'show_payment_method'   => 'yes',
			'show_shipping_method'  => 'yes',
			'show_items'            => 'yes',
			'show_thumbnails'       => 'yes',
			'show_sku'              => 'no',
			'show_item_meta'        => 'no',
			'show_quantity'         => 'yes',
			'show_item_price'       => 'yes',
			'show_subtotal'         => 'no',
			'show_discount'         => 'yes',
			'show_shipping_total'   => 'yes',
			'show_tax'              => 'no',
			'show_total'            => 'yes',
			'show_billing_name'     => 'yes',
			'show_billing_phone'    => 'no',
			'show_billing_email'    => 'no',
			'show_billing_address'  => 'no',
			'show_shipping_address' => 'no',
			'show_customer_note'    => 'yes',
			'show_order_notes'      => 'no',
			'mask_contact'          => 'yes',

			// --- Courier / custom meta rows -----------------------------
			'meta_rows'             => array(),

			// --- Design -------------------------------------------------
			'color_source'          => 'auto',
			'color_primary'         => '',
			'color_on_primary'      => '',
			'color_text'            => '',
			'color_muted'           => '',
			'color_surface'         => '',
			'color_border'          => '',
			'color_success'         => '',
			'color_danger'          => '',
			'radius'                => '',
			'force_styles'          => 'no',
			'load_css'              => 'yes',
		);
	}

	/**
	 * Which settings keys each admin tab owns. Saving a tab rewrites only its
	 * own keys and leaves every other key untouched — without this map an
	 * unchecked checkbox on tab A would wipe tab B's values, because unchecked
	 * boxes are simply absent from $_POST.
	 */
	public static function tab_fields() {
		return array(
			'general'  => array(
				'match_mode',
				'max_results',
				'rate_limit_max',
				'rate_limit_window',
				'url_prefill',
				'form_layout',
				'form_title',
				'form_subtitle',
				'button_text',
				'field_order_enabled',
				'field_order_label',
				'field_order_ph',
				'field_phone_enabled',
				'field_phone_label',
				'field_phone_ph',
				'field_email_enabled',
				'field_email_label',
				'field_email_ph',
				'not_found_text',
				'enable_index',
				'enable_fallbacks',
			),
			'statuses' => array(
				'visible_statuses',
				'timeline_statuses',
				'status_labels',
				'status_colors',
				'hidden_status_action',
				'hidden_status_text',
			),
			'details'  => array(
				'show_timeline',
				'show_order_number',
				'show_date',
				'show_status',
				'show_payment_method',
				'show_shipping_method',
				'show_items',
				'show_thumbnails',
				'show_sku',
				'show_item_meta',
				'show_quantity',
				'show_item_price',
				'show_subtotal',
				'show_discount',
				'show_shipping_total',
				'show_tax',
				'show_total',
				'show_billing_name',
				'show_billing_phone',
				'show_billing_email',
				'show_billing_address',
				'show_shipping_address',
				'show_customer_note',
				'show_order_notes',
				'mask_contact',
			),
			'courier'  => array( 'meta_rows' ),
			'design'   => array(
				'color_source',
				'color_primary',
				'color_on_primary',
				'color_text',
				'color_muted',
				'color_surface',
				'color_border',
				'color_success',
				'color_danger',
				'radius',
				'force_styles',
				'load_css',
			),
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION_KEY, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	public static function get_value( $key, $fallback = null ) {
		$settings = self::get();

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}

	public static function is_on( $key ) {
		return 'yes' === self::get_value( $key );
	}

	/**
	 * Resolves a per-instance override (Elementor widget control or shortcode
	 * attribute) against the global setting. An empty string always means
	 * "inherit", so a widget only overrides what the designer actually touched.
	 */
	public static function resolve( $override, $key ) {
		if ( is_string( $override ) && '' !== trim( $override ) ) {
			return trim( $override );
		}

		return self::get_value( $key );
	}

	/**
	 * Unprefixed status slug => translated label.
	 *
	 * wc_get_order_statuses() returns keys WITH the `wc-` prefix while
	 * $order->get_status() returns them WITHOUT it. Everything this plugin
	 * stores and compares uses the unprefixed form, and the prefix is stripped
	 * exactly once — here — so no runtime comparison can go off-by-prefix.
	 */
	public static function get_status_choices() {
		$choices = array();

		if ( ! function_exists( 'wc_get_order_statuses' ) ) {
			return $choices;
		}

		foreach ( wc_get_order_statuses() as $key => $label ) {
			$slug = self::unprefix_status( $key );

			if ( in_array( $slug, self::NEVER_VISIBLE_STATUSES, true ) ) {
				continue;
			}

			$choices[ $slug ] = $label;
		}

		return $choices;
	}

	public static function unprefix_status( $status ) {
		$status = (string) $status;

		return 0 === strpos( $status, 'wc-' ) ? substr( $status, 3 ) : $status;
	}

	/**
	 * The customer-facing label for a status: the admin's custom wording when
	 * they set one, otherwise WooCommerce's own name (which also covers custom
	 * statuses registered by other plugins).
	 */
	public static function get_status_label( $slug ) {
		$labels = (array) self::get_value( 'status_labels' );

		if ( ! empty( $labels[ $slug ] ) ) {
			return $labels[ $slug ];
		}

		if ( function_exists( 'wc_get_order_status_name' ) ) {
			return wc_get_order_status_name( $slug );
		}

		return ucfirst( str_replace( '-', ' ', $slug ) );
	}

	public static function get_status_color( $slug ) {
		$colors = (array) self::get_value( 'status_colors' );

		return ! empty( $colors[ $slug ] ) ? $colors[ $slug ] : '';
	}

	public static function is_status_visible( $slug ) {
		if ( in_array( $slug, self::NEVER_VISIBLE_STATUSES, true ) ) {
			return false;
		}

		return in_array( $slug, (array) self::get_value( 'visible_statuses' ), true );
	}

	public function __construct() {
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public function register() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Rebuilds only the submitted tab's keys from scratch, then merges them
	 * over the currently stored settings. Rebuilding (rather than iterating
	 * $input) is what makes unchecked checkboxes and emptied repeater rows
	 * actually save as "off"/"empty" instead of silently keeping their old
	 * value. Idempotent, because it always starts from defaults + stored.
	 */
	public static function sanitize( $input ) {
		$current = self::get();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		$tabs = self::tab_fields();
		$tab  = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';

		$keys = isset( $tabs[ $tab ] ) ? $tabs[ $tab ] : array_keys( self::defaults() );

		$clean = $current;

		foreach ( $keys as $key ) {
			$clean[ $key ] = self::sanitize_field( $key, isset( $input[ $key ] ) ? $input[ $key ] : null, $current, $input );
		}

		return $clean;
	}

	private static function sanitize_field( $key, $value, $current, $input = array() ) {
		$defaults = self::defaults();
		$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';

		// Booleans: stored as the strings 'yes'/'no'. An absent key means the
		// checkbox was unchecked, which is a real "no", not a missing value.
		if ( 'yes' === $default || 'no' === $default ) {
			return 'yes' === $value ? 'yes' : 'no';
		}

		switch ( $key ) {
			case 'match_mode':
				return in_array( $value, self::MATCH_MODES, true ) ? $value : 'both';

			case 'form_layout':
				return in_array( $value, array( 'stacked', 'inline' ), true ) ? $value : 'stacked';

			case 'color_source':
				return in_array( $value, array( 'auto', 'custom' ), true ) ? $value : 'auto';

			case 'hidden_status_action':
				return in_array( $value, array( 'generic', 'not_found' ), true ) ? $value : 'generic';

			case 'max_results':
				return max( 1, min( 20, absint( $value ) ) );

			case 'rate_limit_max':
				return max( 1, min( 500, absint( $value ) ) );

			case 'rate_limit_window':
				return max( 60, min( DAY_IN_SECONDS, absint( $value ) ) );

			case 'visible_statuses':
				return self::sanitize_status_list( $value );

			case 'timeline_statuses':
				// Stored in display order, so the stepper renders left to
				// right exactly as the admin numbered the steps.
				return self::sanitize_status_list( $value, isset( $input['timeline_order'] ) ? (array) $input['timeline_order'] : array() );

			case 'status_labels':
				return self::sanitize_status_map( $value, 'sanitize_text_field' );

			case 'status_colors':
				return self::sanitize_status_map( $value, array( __CLASS__, 'sanitize_color' ) );

			case 'meta_rows':
				return self::sanitize_meta_rows( $value );

			case 'radius':
				return '' === trim( (string) $value ) ? '' : sanitize_text_field( $value );
		}

		if ( 0 === strpos( $key, 'color_' ) ) {
			return self::sanitize_color( $value );
		}

		if ( null === $value ) {
			return isset( $current[ $key ] ) ? $current[ $key ] : $default;
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Whitelisted against the statuses WooCommerce actually knows about, so a
	 * crafted POST can never register an unknown status as "visible".
	 */
	private static function sanitize_status_list( $value, $order = array() ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = array_keys( self::get_status_choices() );

		$clean = array();

		foreach ( $value as $slug ) {
			$slug = self::unprefix_status( sanitize_key( $slug ) );

			if ( in_array( $slug, $allowed, true ) && ! in_array( $slug, $clean, true ) ) {
				$clean[] = $slug;
			}
		}

		if ( ! empty( $order ) ) {
			usort(
				$clean,
				function ( $a, $b ) use ( $order ) {
					$weight_a = isset( $order[ $a ] ) ? (int) $order[ $a ] : 99;
					$weight_b = isset( $order[ $b ] ) ? (int) $order[ $b ] : 99;

					return $weight_a <=> $weight_b;
				}
			);
		}

		return $clean;
	}

	private static function sanitize_status_map( $value, $callback ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$allowed = array_keys( self::get_status_choices() );

		$clean = array();

		foreach ( $value as $slug => $raw ) {
			$slug = self::unprefix_status( sanitize_key( $slug ) );

			if ( ! in_array( $slug, $allowed, true ) ) {
				continue;
			}

			$sanitized = call_user_func( $callback, $raw );

			if ( '' !== $sanitized && null !== $sanitized ) {
				$clean[ $slug ] = $sanitized;
			}
		}

		return $clean;
	}

	/**
	 * Repeater of custom detail rows: a label, the order meta key to read, and
	 * an optional URL template where {value} is replaced by the meta value so
	 * a courier tracking number can become a clickable link.
	 */
	private static function sanitize_meta_rows( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$clean = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$meta_key = isset( $row['meta_key'] ) ? sanitize_text_field( $row['meta_key'] ) : '';
			$label    = isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '';

			// A row without a meta key has nothing to read, so it is dropped —
			// this is also how a row gets deleted from the repeater.
			if ( '' === $meta_key ) {
				continue;
			}

			$url = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';

			// esc_url_raw() would strip the {value} placeholder's braces, so
			// validate the shape by hand and keep the template intact.
			if ( '' !== $url && ! preg_match( '#^https?://#i', $url ) ) {
				$url = '';
			}

			$clean[] = array(
				'label'    => '' !== $label ? $label : $meta_key,
				'meta_key' => $meta_key,
				'url'      => $url,
			);
		}

		return $clean;
	}

	public static function sanitize_color( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$hex = sanitize_hex_color( $value );

		return $hex ? $hex : '';
	}
}
