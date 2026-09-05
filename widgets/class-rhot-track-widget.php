<?php
/**
 * Elementor "Order Tracking" widget.
 *
 * Style controls deliberately set the --rhot-*-ovr custom properties rather
 * than concrete CSS properties. That gives per-instance overrides for free:
 * Elementor prints them on {{WRAPPER}} (a highly specific, per-post
 * selector) which sits above the plugin's global inline style in the cascade,
 * so a widget colour beats the Design tab without any override logic here.
 * It also means one control recolours every element that consumes the token.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Widget_Base;

class RHOT_Track_Widget extends Widget_Base {

	public function get_name() {
		return 'rhot_order_track';
	}

	public function get_title() {
		return __( 'Order Tracking', 'rh-order-track' );
	}

	public function get_icon() {
		return 'eicon-search';
	}

	public function get_categories() {
		return array( RHOT_Elementor::CATEGORY_SLUG );
	}

	public function get_keywords() {
		return array( 'order', 'track', 'tracking', 'woocommerce', 'status', 'rh' );
	}

	public function get_style_depends() {
		return array( RHOT_Assets::HANDLE );
	}

	public function get_script_depends() {
		return array( RHOT_Assets::HANDLE );
	}

	protected function register_controls() {
		$this->register_content_controls();
		$this->register_style_controls();
	}

	/**
	 * Every content control defaults to an empty value meaning "use the
	 * global setting", so a freshly dropped widget matches the dashboard
	 * configuration and only diverges where the designer types something.
	 */
	private function register_content_controls() {
		$this->start_controls_section(
			'section_content',
			array( 'label' => __( 'Content', 'rh-order-track' ) )
		);

		$this->add_control(
			'title',
			array(
				'label'       => __( 'Heading', 'rh-order-track' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => __( 'Leave empty to use the global setting', 'rh-order-track' ),
			)
		);

		$this->add_control(
			'subtitle',
			array(
				'label'       => __( 'Sub-heading', 'rh-order-track' ),
				'type'        => Controls_Manager::TEXTAREA,
				'rows'        => 2,
				'placeholder' => __( 'Leave empty to use the global setting', 'rh-order-track' ),
			)
		);

		$this->add_control(
			'mode',
			array(
				'label'   => __( 'What the customer enters', 'rh-order-track' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''           => __( 'Use global setting', 'rh-order-track' ),
					'both'       => __( 'Order number and phone (most secure)', 'rh-order-track' ),
					'either'     => __( 'Order number or phone', 'rh-order-track' ),
					'order_only' => __( 'Order number only', 'rh-order-track' ),
					'phone_only' => __( 'Phone only', 'rh-order-track' ),
				),
			)
		);

		$this->add_control(
			'layout',
			array(
				'label'   => __( 'Form layout', 'rh-order-track' ),
				'type'    => Controls_Manager::SELECT,
				'default' => '',
				'options' => array(
					''        => __( 'Use global setting', 'rh-order-track' ),
					'stacked' => __( 'Stacked', 'rh-order-track' ),
					'inline'  => __( 'Inline', 'rh-order-track' ),
				),
			)
		);

		$this->add_control(
			'button_text',
			array(
				'label'       => __( 'Button text', 'rh-order-track' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
				'placeholder' => __( 'Leave empty to use the global setting', 'rh-order-track' ),
			)
		);

		$this->add_control(
			'labels_heading',
			array(
				'label'     => __( 'Field labels', 'rh-order-track' ),
				'type'      => Controls_Manager::HEADING,
				'separator' => 'before',
			)
		);

		$this->add_control(
			'order_label',
			array(
				'label'       => __( 'Order number label', 'rh-order-track' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$this->add_control(
			'order_ph',
			array(
				'label'       => __( 'Order number placeholder', 'rh-order-track' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$this->add_control(
			'phone_label',
			array(
				'label'       => __( 'Phone label', 'rh-order-track' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$this->add_control(
			'phone_ph',
			array(
				'label'       => __( 'Phone placeholder', 'rh-order-track' ),
				'type'        => Controls_Manager::TEXT,
				'label_block' => true,
			)
		);

		$this->end_controls_section();
	}

	private function register_style_controls() {
		$this->start_controls_section(
			'section_style',
			array(
				'label' => __( 'Colours', 'rh-order-track' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_control(
			'inherit_note',
			array(
				'type'            => Controls_Manager::RAW_HTML,
				'raw'             => esc_html__( 'Leave a colour empty to inherit it from your theme or Elementor global colours.', 'rh-order-track' ),
				'content_classes' => 'elementor-descriptor',
			)
		);

		$tokens = array(
			'primary'    => __( 'Primary / button', 'rh-order-track' ),
			'on-primary' => __( 'Button text', 'rh-order-track' ),
			'text'       => __( 'Text', 'rh-order-track' ),
			'muted'      => __( 'Muted text', 'rh-order-track' ),
			'surface'    => __( 'Card background', 'rh-order-track' ),
			'border'     => __( 'Borders', 'rh-order-track' ),
			'danger'     => __( 'Error', 'rh-order-track' ),
		);

		foreach ( $tokens as $token => $label ) {
			$this->add_control(
				'color_' . str_replace( '-', '_', $token ),
				array(
					'label'     => $label,
					'type'      => Controls_Manager::COLOR,
					'selectors' => array(
						'{{WRAPPER}} .rhot-track' => '--rhot-' . $token . '-ovr: {{VALUE}};',
					),
				)
			);
		}

		$this->end_controls_section();

		$this->start_controls_section(
			'section_style_layout',
			array(
				'label' => __( 'Shape and spacing', 'rh-order-track' ),
				'tab'   => Controls_Manager::TAB_STYLE,
			)
		);

		$this->add_responsive_control(
			'radius',
			array(
				'label'      => __( 'Corner radius', 'rh-order-track' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px', 'rem' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 40,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .rhot-track' => '--rhot-radius-ovr: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_responsive_control(
			'gap',
			array(
				'label'      => __( 'Field spacing', 'rh-order-track' ),
				'type'       => Controls_Manager::SLIDER,
				'size_units' => array( 'px' ),
				'range'      => array(
					'px' => array(
						'min' => 0,
						'max' => 48,
					),
				),
				'selectors'  => array(
					'{{WRAPPER}} .rhot-track .rhot-fields' => 'gap: {{SIZE}}{{UNIT}};',
				),
			)
		);

		$this->add_group_control(
			Group_Control_Typography::get_type(),
			array(
				'name'     => 'base_typography',
				'selector' => '{{WRAPPER}} .rhot-track',
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$atts = array(
			'title'       => isset( $settings['title'] ) ? $settings['title'] : '',
			'subtitle'    => isset( $settings['subtitle'] ) ? $settings['subtitle'] : '',
			'mode'        => isset( $settings['mode'] ) ? $settings['mode'] : '',
			'layout'      => isset( $settings['layout'] ) ? $settings['layout'] : '',
			'button_text' => isset( $settings['button_text'] ) ? $settings['button_text'] : '',
			'order_label' => isset( $settings['order_label'] ) ? $settings['order_label'] : '',
			'order_ph'    => isset( $settings['order_ph'] ) ? $settings['order_ph'] : '',
			'phone_label' => isset( $settings['phone_label'] ) ? $settings['phone_label'] : '',
			'phone_ph'    => isset( $settings['phone_ph'] ) ? $settings['phone_ph'] : '',
		);

		// In the editor a sample result is rendered underneath the form so the
		// designer can style the card and timeline without a real lookup.
		$preview = \Elementor\Plugin::$instance->editor->is_edit_mode();

		// RHOT_Shortcode::render_form() escapes everything it emits and is the
		// single shared renderer for the widget and the shortcode.
		echo RHOT_Shortcode::render_form( $atts, $preview ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
