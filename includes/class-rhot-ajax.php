<?php
/**
 * Public AJAX endpoints.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Ajax {

	const ACTION               = 'rhot_track_order';
	const NONCE_ACTION         = 'rhot_track_order_action';
	const NONCE_REFRESH_ACTION = 'rhot_refresh_nonce';

	/**
	 * Inputs are capped before any pattern matching so a megabyte-long string
	 * can't be used to burn CPU in preg_replace.
	 */
	const MAX_INPUT_LENGTH = 64;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_' . self::ACTION, array( $this, 'handle_track' ) );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, array( $this, 'handle_track' ) );

		add_action( 'wp_ajax_' . self::NONCE_REFRESH_ACTION, array( $this, 'handle_refresh_nonce' ) );
		add_action( 'wp_ajax_nopriv_' . self::NONCE_REFRESH_ACTION, array( $this, 'handle_refresh_nonce' ) );
	}

	public function handle_track() {
		// Counted before any validation, so probing with deliberately
		// malformed input can't buy unlimited free attempts past the cap.
		$this->record_attempt();

		if ( $this->is_rate_limited() ) {
			$this->fail( 'rate_limited', __( 'Too many attempts. Please wait a few minutes and try again.', 'rh-order-track' ) );
		}

		$nonce = isset( $_POST['rhot_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['rhot_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			// The JS retries once with a fresh nonce on this exact code: on a
			// full-page-cached site the nonce baked into the HTML goes stale
			// after a day and every lookup would otherwise fail.
			$this->fail( 'invalid_nonce', __( 'Your session expired. Please try again.', 'rh-order-track' ) );
		}

		if ( ! RHOT_Compat::has_woocommerce() ) {
			$this->fail( 'unavailable', __( 'Order tracking is temporarily unavailable. Please try again shortly.', 'rh-order-track' ) );
		}

		$input = array(
			'order' => $this->read_field( 'rhot_order' ),
			'phone' => $this->read_field( 'rhot_phone' ),
			'email' => $this->read_field( 'rhot_email' ),
		);

		$context = array();

		$mode = $this->read_field( 'rhot_mode' );

		if ( '' !== $mode && in_array( $mode, RHOT_Settings::MATCH_MODES, true ) ) {
			$context['match_mode'] = $mode;
		}

		$orders = RHOT_Lookup::find( $input, $context );

		if ( is_wp_error( $orders ) ) {
			$this->fail( $orders->get_error_code(), $orders->get_error_message() );
		}

		$single = ( 1 === count( $orders ) );

		$results = array();

		foreach ( $orders as $order ) {
			$results[] = RHOT_Formatter::to_view_model( $order, $single );
		}

		$html = RHOT_Template::get_html(
			'result.php',
			array(
				'results' => $results,
				'count'   => count( $results ),
			)
		);

		wp_send_json_success(
			array(
				'html'  => $html,
				'count' => count( $results ),
			)
		);
	}

	/**
	 * Hands out a fresh nonce so a cached page can recover without a reload.
	 * Safe to expose: a nonce alone grants nothing, and the endpoint it
	 * unlocks is read-only and rate limited.
	 */
	public function handle_refresh_nonce() {
		wp_send_json_success( array( 'nonce' => wp_create_nonce( self::NONCE_ACTION ) ) );
	}

	private function read_field( $key ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			return '';
		}

		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );

		return trim( mb_substr( $value, 0, self::MAX_INPUT_LENGTH ) );
	}

	private function fail( $code, $message ) {
		wp_send_json_error(
			array(
				'code'    => $code,
				'message' => $message,
			)
		);
	}

	private function rate_limit_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';

		return 'rhot_rl_' . md5( $ip );
	}

	/**
	 * Not a true sliding window: set_transient() resets the full expiry on
	 * every attempt, so the counter only clears after a quiet period of the
	 * configured length. That is stricter than a sliding window, not looser —
	 * documented here so it isn't mistaken for a bug.
	 */
	private function record_attempt() {
		$key   = $this->rate_limit_key();
		$count = (int) get_transient( $key );

		set_transient( $key, $count + 1, (int) RHOT_Settings::get_value( 'rate_limit_window' ) );
	}

	private function is_rate_limited() {
		return (int) get_transient( $this->rate_limit_key() ) > (int) RHOT_Settings::get_value( 'rate_limit_max' );
	}
}
