<?php
/**
 * Dependency gating and WooCommerce feature declarations.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Compat {

	public function __construct() {
		add_action( 'before_woocommerce_init', array( $this, 'declare_hpos_compatibility' ) );
		add_action( 'admin_notices', array( $this, 'dependency_notices' ) );
	}

	/**
	 * Every order read in this plugin goes through the WooCommerce CRUD layer
	 * (wc_get_order / wc_get_orders / $order->get_meta), never a direct $wpdb
	 * query against posts or postmeta, so custom order tables are fully
	 * supported. Declaring it explicitly keeps the store off WooCommerce's
	 * "incompatible plugin" list, which otherwise blocks HPOS from being
	 * enabled at all.
	 */
	public function declare_hpos_compatibility() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			return;
		}

		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', RHOT_PLUGIN_FILE, true );
	}

	/**
	 * Never deactivates or wp_die()s — a missing dependency degrades to an
	 * admin notice so the site keeps working and the admin can fix it.
	 */
	public static function is_compatible() {
		return self::has_php() && self::has_woocommerce();
	}

	public static function has_php() {
		return version_compare( PHP_VERSION, RH_Order_Track::MINIMUM_PHP_VERSION, '>=' );
	}

	public static function has_woocommerce() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_orders' );
	}

	public function dependency_notices() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		if ( ! self::has_php() ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>',
				sprintf(
					/* translators: 1: required PHP version, 2: current PHP version */
					esc_html__( 'RH Order Track requires PHP %1$s or newer. This server is running PHP %2$s, so the plugin is inactive.', 'rh-order-track' ),
					esc_html( RH_Order_Track::MINIMUM_PHP_VERSION ),
					esc_html( PHP_VERSION )
				)
			);

			return;
		}

		if ( ! self::has_woocommerce() ) {
			printf(
				'<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>',
				esc_html__( 'RH Order Track needs WooCommerce to be installed and active. The tracking form stays hidden until then.', 'rh-order-track' )
			);
		}
	}

	/**
	 * True when orders live in the custom order tables. Used only for admin
	 * status reporting and to decide whether HPOS-only query features are
	 * safe — the lookup itself deliberately sticks to query arguments that
	 * behave identically in both storage engines.
	 */
	public static function is_hpos_active() {
		if ( ! class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) ) {
			return false;
		}

		return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
	}
}
