<?php
/**
 * Elementor category and widget registration.
 *
 * Uses the modern registration API (elementor/widgets/register +
 * $widgets_manager->register), which is what Elementor 3.5+ requires; the
 * legacy widgets_registered/register_widget_type pair no longer exists.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Elementor {

	const CATEGORY_SLUG = 'rh-order-track';

	public function __construct() {
		add_action( 'elementor/elements/categories_registered', array( $this, 'register_category' ) );
		add_action( 'elementor/widgets/register', array( $this, 'register_widget' ) );
	}

	public function register_category( $elements_manager ) {
		$elements_manager->add_category(
			self::CATEGORY_SLUG,
			array(
				'title' => __( 'RH Order Track', 'rh-order-track' ),
				'icon'  => 'eicon-search',
			)
		);
	}

	public function register_widget( $widgets_manager ) {
		// Loaded lazily, inside the callback, so the widget class is only
		// parsed on requests where Elementor is actually building widgets.
		require_once RHOT_PLUGIN_DIR . 'widgets/class-rhot-track-widget.php';

		$widgets_manager->register( new RHOT_Track_Widget() );
	}
}
