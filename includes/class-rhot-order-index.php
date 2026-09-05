<?php
/**
 * Normalized billing-phone index used for customer order lookups.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Order_Index {

	const META_KEY = '_rhot_phone_digits';

	/**
	 * Below this many digits an input is too short to identify anybody — a
	 * three-character entry would otherwise match thousands of orders.
	 */
	const MIN_DIGITS = 7;

	/**
	 * Comparing on the last 10 digits collapses every way a Bangladeshi number
	 * gets stored — 01712345678, +8801712345678, 880 1712-345678, 1712345678 —
	 * onto the same key, without a country-code table.
	 */
	const KEY_LENGTH = 10;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Priority 20 so third-party plugins that rewrite billing_phone on the
		// default priority have already run and we index their final value.
		add_action( 'woocommerce_before_order_object_save', array( $this, 'index_phone' ), 20, 2 );
	}

	/**
	 * Writes the normalized phone key onto the order that is about to be
	 * saved.
	 *
	 * This deliberately hooks `woocommerce_before_order_object_save` rather
	 * than `woocommerce_new_order`/`woocommerce_update_order`. It fires above
	 * the data store — so it works identically under HPOS and legacy post
	 * storage — and, crucially, it runs *before* persistence: both data stores
	 * call $order->save_meta_data() as part of the same save, so the value is
	 * written by the in-flight save. Calling $order->save() here would recurse
	 * infinitely; we must only mutate the object and return.
	 *
	 * @param WC_Data           $order      Order about to be saved.
	 * @param WC_Data_Store_WP  $data_store Its data store.
	 */
	public function index_phone( $order, $data_store ) {
		unset( $data_store );

		// WC_Order_Refund is not a WC_Order and carries no billing phone.
		if ( ! $order instanceof WC_Order ) {
			return;
		}

		if ( ! RHOT_Settings::is_on( 'enable_index' ) ) {
			return;
		}

		$digits  = self::normalize( $order->get_billing_phone( 'edit' ) );
		$current = (string) $order->get_meta( self::META_KEY, true );

		// Nothing changed: skip the write so ordinary order saves don't churn
		// a meta row on every single update.
		if ( $digits === $current ) {
			return;
		}

		if ( '' === $digits ) {
			$order->delete_meta_data( self::META_KEY );

			return;
		}

		$order->update_meta_data( self::META_KEY, $digits );
	}

	/**
	 * Reduces any phone number to a comparable key: the last KEY_LENGTH
	 * digits, or an empty string when the input is too short to be meaningful.
	 *
	 * Returning '' for unusable input is load-bearing, not defensive padding —
	 * callers must refuse to build a query from an empty key. See
	 * RHOT_Lookup::find_by_phone() for why.
	 */
	public static function normalize( $phone ) {
		$phone = self::to_ascii_digits( (string) $phone );

		$digits = preg_replace( '/\D+/', '', $phone );

		if ( strlen( $digits ) < self::MIN_DIGITS ) {
			return '';
		}

		return substr( $digits, -self::KEY_LENGTH );
	}

	/**
	 * Converts Bengali (০-৯) and Arabic-Indic (٠-٩) numerals to ASCII before
	 * digit extraction. Customers on Bangla keyboards routinely type their
	 * number in Bengali digits; stripping non-ASCII first would erase the
	 * whole number and silently return "not found".
	 */
	public static function to_ascii_digits( $string ) {
		$map = array(
			'০' => '0',
			'১' => '1',
			'২' => '2',
			'৩' => '3',
			'৪' => '4',
			'৫' => '5',
			'৬' => '6',
			'৭' => '7',
			'৮' => '8',
			'৯' => '9',
			'٠' => '0',
			'١' => '1',
			'٢' => '2',
			'٣' => '3',
			'٤' => '4',
			'٥' => '5',
			'٦' => '6',
			'٧' => '7',
			'٨' => '8',
			'٩' => '9',
		);

		return strtr( $string, $map );
	}

	/**
	 * Plausible stored spellings of a number, for the exact-match fallback
	 * against WooCommerce's own billing_phone field.
	 */
	public static function variants( $raw, $digits ) {
		$variants = array();

		foreach ( array( trim( (string) $raw ), $digits, '0' . $digits, '88' . $digits, '+88' . $digits, '880' . $digits, '+880' . $digits ) as $variant ) {
			if ( '' !== $variant && ! in_array( $variant, $variants, true ) ) {
				$variants[] = $variant;
			}
		}

		return $variants;
	}

	/**
	 * Reads order meta with a post-meta fallback.
	 *
	 * Some third-party plugins still write order meta with
	 * update_post_meta( $order_id, ... ) — Send-To-SteadFast does exactly this
	 * for `steadfast_consignment_id`. Under HPOS that lands in wp_postmeta
	 * instead of the order meta table, so $order->get_meta() alone would come
	 * back empty. Trying both keeps custom meta rows working regardless of
	 * which storage the writing plugin assumed.
	 */
	public static function read_meta( $order, $key ) {
		if ( ! $order instanceof WC_Order || '' === $key ) {
			return '';
		}

		$value = $order->get_meta( $key, true );

		if ( '' === $value || null === $value || array() === $value ) {
			$value = get_post_meta( $order->get_id(), $key, true );
		}

		return is_scalar( $value ) ? (string) $value : '';
	}
}
