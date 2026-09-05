<?php
/**
 * Lookup results wrapper.
 *
 * Override by copying this file to yourtheme/rh-order-track/result.php
 *
 * @package RH_Order_Track
 * @version 1.0.0
 *
 * @var array $results View models, one per matched order.
 * @var int   $count   Number of results.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="rhot-results">

	<?php if ( $count > 1 ) : ?>
		<p class="rhot-results__count">
			<?php
			printf(
				/* translators: %s: number of matching orders */
				esc_html( _n( '%s recent order found.', '%s recent orders found.', $count, 'rh-order-track' ) ),
				esc_html( number_format_i18n( $count ) )
			);
			?>
		</p>
	<?php endif; ?>

	<?php foreach ( $results as $order ) : ?>
		<?php RHOT_Template::render( 'order-card.php', array( 'order' => $order ) ); ?>
	<?php endforeach; ?>

</div>
