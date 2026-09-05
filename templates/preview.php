<?php
/**
 * Static sample result, shown only inside the Elementor editor so the result
 * panel can be styled without performing a real lookup.
 *
 * Override by copying this file to yourtheme/rh-order-track/preview.php
 *
 * @package RH_Order_Track
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="rhot-results">
	<article class="rhot-card">
		<header class="rhot-card__head">
			<h4 class="rhot-card__number"><?php esc_html_e( 'Order #1042', 'rh-order-track' ); ?></h4>
			<span class="rhot-status rhot-status--processing"><?php esc_html_e( 'Processing', 'rh-order-track' ); ?></span>
		</header>

		<ol class="rhot-timeline">
			<li class="rhot-step rhot-step--done">
				<span class="rhot-step__dot" aria-hidden="true"></span>
				<span class="rhot-step__label"><?php esc_html_e( 'Pending payment', 'rh-order-track' ); ?></span>
			</li>
			<li class="rhot-step rhot-step--current" aria-current="step">
				<span class="rhot-step__dot" aria-hidden="true"></span>
				<span class="rhot-step__label"><?php esc_html_e( 'Processing', 'rh-order-track' ); ?></span>
			</li>
			<li class="rhot-step rhot-step--upcoming">
				<span class="rhot-step__dot" aria-hidden="true"></span>
				<span class="rhot-step__label"><?php esc_html_e( 'Completed', 'rh-order-track' ); ?></span>
			</li>
		</ol>

		<dl class="rhot-rows">
			<div class="rhot-row">
				<dt class="rhot-row__label"><?php esc_html_e( 'Order date', 'rh-order-track' ); ?></dt>
				<dd class="rhot-row__value"><?php echo esc_html( date_i18n( get_option( 'date_format' ) ) ); ?></dd>
			</div>
			<div class="rhot-row">
				<dt class="rhot-row__label"><?php esc_html_e( 'Payment method', 'rh-order-track' ); ?></dt>
				<dd class="rhot-row__value"><?php esc_html_e( 'Cash on delivery', 'rh-order-track' ); ?></dd>
			</div>
		</dl>

		<ul class="rhot-items">
			<li class="rhot-item">
				<div class="rhot-item__thumb"><?php echo wp_kses_post( wc_placeholder_img( 'woocommerce_thumbnail' ) ); ?></div>
				<div class="rhot-item__body">
					<span class="rhot-item__name"><?php esc_html_e( 'Sample product', 'rh-order-track' ); ?></span>
				</div>
				<div class="rhot-item__figures">
					<span class="rhot-item__qty">&times;&nbsp;1</span>
					<span class="rhot-item__price"><?php echo wp_kses_post( wc_price( 1250 ) ); ?></span>
				</div>
			</li>
		</ul>

		<dl class="rhot-rows rhot-rows--totals">
			<div class="rhot-row rhot-row--total">
				<dt class="rhot-row__label"><?php esc_html_e( 'Total', 'rh-order-track' ); ?></dt>
				<dd class="rhot-row__value"><?php echo wp_kses_post( wc_price( 1250 ) ); ?></dd>
			</div>
		</dl>
	</article>
</div>
