<?php
/**
 * Order lookup: turns customer input into matching orders, or nothing.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Lookup {

	/**
	 * Every failure path returns this same code. Distinguishing "no such
	 * order" from "order exists but the phone is wrong" would turn the form
	 * into an oracle for probing which order numbers are real.
	 */
	const NOT_FOUND = 'not_found';

	/**
	 * @param array $input   Raw, already sanitized strings: order, phone, email.
	 * @param array $context Optional per-instance overrides (match_mode).
	 *
	 * @return array|WP_Error List of WC_Order objects, or WP_Error on failure.
	 */
	public static function find( $input, $context = array() ) {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return self::error( 'unavailable', __( 'Order tracking is temporarily unavailable. Please try again shortly.', 'rh-order-track' ) );
		}

		$mode = isset( $context['match_mode'] ) ? $context['match_mode'] : RHOT_Settings::get_value( 'match_mode' );

		if ( ! in_array( $mode, RHOT_Settings::MATCH_MODES, true ) ) {
			$mode = 'both';
		}

		$number = self::clean_order_number( isset( $input['order'] ) ? $input['order'] : '' );
		$digits = RHOT_Order_Index::normalize( isset( $input['phone'] ) ? $input['phone'] : '' );
		$email  = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';

		$required = self::check_required( $mode, $number, $digits, $email );

		if ( is_wp_error( $required ) ) {
			return $required;
		}

		$orders = ( '' !== $number )
			? self::find_by_number( $number, $digits, $email, $mode )
			: self::find_by_phone( $digits, $email );

		if ( is_wp_error( $orders ) ) {
			return $orders;
		}

		$matched = $orders;
		$orders  = self::filter_visible( $orders );

		if ( empty( $orders ) ) {
			// The order was found but sits in a status the admin chose to
			// hide. Reporting a neutral "in progress" message is friendlier
			// than "not found" for a customer whose order is real — but it
			// does confirm the order exists, so the admin can opt out.
			return empty( $matched ) ? self::not_found() : self::hidden();
		}

		return array_values( $orders );
	}

	/**
	 * Mirrors WooCommerce's own tracking shortcode, which strips a leading "#"
	 * because customers copy the order number straight off their receipt.
	 */
	private static function clean_order_number( $raw ) {
		$raw = RHOT_Order_Index::to_ascii_digits( trim( (string) $raw ) );

		return ltrim( $raw, '#' );
	}

	private static function check_required( $mode, $number, $digits, $email ) {
		$has_number = ( '' !== $number );
		$has_phone  = ( '' !== $digits );
		$has_email  = ( '' !== $email );

		if ( 'both' === $mode && ( ! $has_number || ! $has_phone ) ) {
			return self::error( 'incomplete', __( 'Please enter both your order number and the phone number you ordered with.', 'rh-order-track' ) );
		}

		if ( 'order_only' === $mode && ! $has_number ) {
			return self::error( 'incomplete', __( 'Please enter your order number.', 'rh-order-track' ) );
		}

		if ( 'phone_only' === $mode && ! $has_phone ) {
			return self::error( 'incomplete', __( 'Please enter the phone number you ordered with.', 'rh-order-track' ) );
		}

		if ( 'either' === $mode && ! $has_number && ! $has_phone && ! $has_email ) {
			return self::error( 'incomplete', __( 'Please enter your order number or the phone number you ordered with.', 'rh-order-track' ) );
		}

		return true;
	}

	/**
	 * Order-number branch: one direct read, no query, no enumeration surface
	 * beyond the order id itself.
	 */
	private static function find_by_number( $number, $digits, $email, $mode ) {
		/**
		 * Also run through WooCommerce's own tracking filter so any installed
		 * sequential-order-number plugin keeps working here for free, then
		 * expose our own filter on top of it.
		 */
		$resolved = apply_filters( 'woocommerce_shortcode_order_tracking_order_id', $number );
		$resolved = apply_filters( 'rhot_lookup_order_id', $resolved, $number );

		$order_id = absint( $resolved );

		$order = $order_id ? wc_get_order( $order_id ) : false;

		// wc_get_order() can hand back a WC_Order_Refund, which is not a
		// WC_Order - this guard is load-bearing, not decorative.
		if ( ! $order instanceof WC_Order ) {
			return self::not_found();
		}

		$needs_phone = ( 'both' === $mode ) || ( '' !== $digits );
		$needs_email = ( '' !== $email );

		if ( $needs_phone && ! self::phone_matches( $order, $digits ) ) {
			return self::not_found();
		}

		if ( $needs_email && ! self::email_matches( $order, $email ) ) {
			return self::not_found();
		}

		return array( $order );
	}

	/**
	 * Compares against both the stored index and the live billing phone, so
	 * order-number + phone verification works from day one - before the
	 * backfill tool has ever been run.
	 *
	 * hash_equals() because this comparison is what gates access to somebody
	 * else's order details.
	 */
	private static function phone_matches( $order, $digits ) {
		if ( '' === $digits ) {
			return false;
		}

		$stored = (string) $order->get_meta( RHOT_Order_Index::META_KEY, true );
		$live   = RHOT_Order_Index::normalize( $order->get_billing_phone() );

		return ( '' !== $stored && hash_equals( $stored, $digits ) )
			|| ( '' !== $live && hash_equals( $live, $digits ) );
	}

	private static function email_matches( $order, $email ) {
		$stored = $order->get_billing_email();

		if ( '' === $stored ) {
			return false;
		}

		return hash_equals( strtolower( $stored ), strtolower( $email ) );
	}

	/**
	 * Phone-only branch, in three tiers. Every query argument used here
	 * behaves identically under HPOS and legacy post storage.
	 *
	 * meta_query/field_query are deliberately never used: the legacy data
	 * store does not support them and silently drops the clause rather than
	 * erroring, which would return the newest orders on the site to whoever
	 * asked. That is a data-leak-shaped failure, not a no-results failure.
	 */
	private static function find_by_phone( $digits, $email ) {
		// Hard guard. An empty meta_value is treated as "argument not set" by
		// the HPOS query builder, which drops the filter entirely and returns
		// the most recent orders on the store. Never build a query from ''.
		if ( '' === $digits ) {
			if ( '' !== $email ) {
				return self::query( array( 'billing_email' => $email ) );
			}

			return self::not_found();
		}

		$orders = self::query(
			array(
				'meta_key'   => RHOT_Order_Index::META_KEY,
				'meta_value' => $digits,
			)
		);

		if ( ! empty( $orders ) || ! RHOT_Settings::is_on( 'enable_fallbacks' ) ) {
			return $orders;
		}

		// Tier 2: WooCommerce's native billing_phone field. Indexed under
		// HPOS; an auto-generated _billing_phone meta clause under CPT.
		$orders = self::query( array( 'billing_phone' => RHOT_Order_Index::variants( $digits, $digits ) ) );

		if ( ! empty( $orders ) ) {
			return $orders;
		}

		// Tier 3: substring match on the address index WooCommerce maintains
		// itself in both storage engines. Unindexed, hence last, and behind
		// the rate limiter. Only matches when the digits are stored
		// contiguously, so "017-1234-5678" will not be found this way.
		return self::query(
			array(
				'meta_key'     => '_billing_address_index',
				'meta_value'   => $digits,
				'meta_compare' => 'LIKE',
			)
		);
	}

	private static function query( $args ) {
		$visible = (array) RHOT_Settings::get_value( 'visible_statuses' );

		if ( empty( $visible ) ) {
			return array();
		}

		$defaults = array(
			// wc_get_orders() otherwise defaults to every "view-orders" type,
			// which would include refunds.
			'type'    => 'shop_order',
			'limit'   => (int) RHOT_Settings::get_value( 'max_results' ),
			'status'  => $visible,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		);

		$orders = wc_get_orders( array_merge( $defaults, $args ) );

		return is_array( $orders ) ? $orders : array();
	}

	/**
	 * Belt-and-braces status filtering. The query already constrains status,
	 * but the order-number branch bypasses the query entirely, so visibility
	 * has to be enforced here too.
	 */
	private static function filter_visible( $orders ) {
		$visible = array();

		foreach ( (array) $orders as $order ) {
			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			if ( RHOT_Settings::is_status_visible( $order->get_status() ) ) {
				$visible[] = $order;
			}
		}

		return $visible;
	}

	public static function not_found() {
		return self::error( self::NOT_FOUND, self::not_found_message() );
	}

	private static function hidden() {
		if ( 'generic' !== RHOT_Settings::get_value( 'hidden_status_action' ) ) {
			return self::not_found();
		}

		$text = trim( (string) RHOT_Settings::get_value( 'hidden_status_text' ) );

		if ( '' === $text ) {
			$text = __( 'Your order is being processed. Please check back a little later.', 'rh-order-track' );
		}

		return self::error( 'hidden', $text );
	}

	public static function not_found_message() {
		$custom = trim( (string) RHOT_Settings::get_value( 'not_found_text' ) );

		if ( '' !== $custom ) {
			return $custom;
		}

		return __( 'We could not find an order matching those details. Please check them and try again.', 'rh-order-track' );
	}

	private static function error( $code, $message ) {
		return new WP_Error( $code, $message );
	}
}
