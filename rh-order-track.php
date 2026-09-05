<?php
/**
 * Plugin Name: RH Order Track
 * Description: WooCommerce order tracking for customers — Elementor widget and shortcode, with fully configurable lookup fields, visible statuses, order details and colors.
 * Version: 1.0.0
 * Author: Rubel Hossain
 * Author URI: https://rubelhossain.online
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: rh-order-track
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'RHOT_VERSION', '1.0.0' );
define( 'RHOT_PLUGIN_FILE', __FILE__ );
define( 'RHOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'RHOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-compat.php';

final class RH_Order_Track {

	const MINIMUM_PHP_VERSION = '7.4';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * The compatibility layer is instantiated here — at plugin-file include
	 * time — and NOT inside the plugins_loaded callback below. WooCommerce
	 * fires `before_woocommerce_init` from its own plugins_loaded handler, so
	 * anything registered from our plugins_loaded callback can already be too
	 * late: FeaturesController::declare_compatibility() hard-checks
	 * doing_action( 'before_woocommerce_init' ) and refuses otherwise, which
	 * would silently mark this plugin HPOS-incompatible.
	 */
	private function __construct() {
		new RHOT_Compat();

		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
	}

	public function on_plugins_loaded() {
		load_plugin_textdomain( 'rh-order-track', false, dirname( plugin_basename( RHOT_PLUGIN_FILE ) ) . '/languages' );

		if ( ! RHOT_Compat::is_compatible() ) {
			return;
		}

		$this->includes();

		// Order indexing and the settings store must load on every request —
		// admin, front-end, REST and CLI alike — because orders can be created
		// from any of them.
		RHOT_Order_Index::instance();

		if ( is_admin() ) {
			RHOT_Admin::instance();
			RHOT_Tools::instance();
		}

		RHOT_Ajax::instance();
		RHOT_Assets::instance();
		RHOT_Shortcode::instance();

		// Elementor is an optional dependency: the shortcode and AJAX endpoint
		// work perfectly well without it, so only the widget layer is gated.
		if ( did_action( 'elementor/loaded' ) ) {
			require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-elementor.php';
			new RHOT_Elementor();
		}
	}

	private function includes() {
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-settings.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-order-index.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-lookup.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-formatter.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-template.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-styles.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-assets.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-ajax.php';
		require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-shortcode.php';

		if ( is_admin() ) {
			require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-admin.php';
			require_once RHOT_PLUGIN_DIR . 'includes/class-rhot-tools.php';
		}
	}
}

RH_Order_Track::instance();
