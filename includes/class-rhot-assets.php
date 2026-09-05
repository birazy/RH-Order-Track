<?php
/**
 * Front-end asset registration.
 *
 * One registered handle serves both the shortcode and the Elementor widget,
 * so a page using both loads the CSS and JS exactly once.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Assets {

	const HANDLE = 'rhot-track';

	private static $instance = null;

	private $registered = false;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		// Priority 20 so the stylesheet is registered after the theme's own,
		// which puts it later in the cascade when both are printed.
		add_action( 'wp_enqueue_scripts', array( $this, 'register' ), 20 );

		// Elementor registers widget assets on its own hooks; using the same
		// handle means get_style_depends() picks up this exact registration.
		add_action( 'elementor/frontend/after_register_styles', array( $this, 'register' ) );
		add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register' ) );
	}

	public function register() {
		if ( $this->registered ) {
			return;
		}

		$this->registered = true;

		if ( RHOT_Settings::is_on( 'load_css' ) ) {
			wp_register_style( self::HANDLE, RHOT_PLUGIN_URL . 'assets/css/rhot-track.css', array(), RHOT_VERSION );

			$inline = RHOT_Styles::build_inline_css();

			if ( '' !== $inline ) {
				wp_add_inline_style( self::HANDLE, $inline );
			}
		}

		wp_register_script( self::HANDLE, RHOT_PLUGIN_URL . 'assets/js/rhot-track.js', array(), RHOT_VERSION, true );

		wp_localize_script(
			self::HANDLE,
			'rhotTrack',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'action'       => RHOT_Ajax::ACTION,
				'nonceAction'  => RHOT_Ajax::NONCE_REFRESH_ACTION,
				'urlPrefill'   => RHOT_Settings::is_on( 'url_prefill' ) ? 'yes' : 'no',
				'i18n'         => array(
					'loading' => __( 'Checking…', 'rh-order-track' ),
					'error'   => __( 'Something went wrong. Please try again.', 'rh-order-track' ),
					'empty'   => __( 'Please fill in the form before tracking.', 'rh-order-track' ),
				),
			)
		);
	}

	/**
	 * Called from the shortcode and the widget at render time. Enqueuing this
	 * late still works because both styles and scripts also print in the
	 * footer.
	 */
	public static function enqueue() {
		$assets = self::instance();
		$assets->register();

		if ( RHOT_Settings::is_on( 'load_css' ) ) {
			wp_enqueue_style( self::HANDLE );
		}

		wp_enqueue_script( self::HANDLE );
	}
}
