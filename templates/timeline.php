<?php
/**
 * Status stepper.
 *
 * Override by copying this file to yourtheme/rh-order-track/timeline.php
 *
 * @package RH_Order_Track
 * @version 1.0.0
 *
 * @var array $timeline {
 *     @type bool  $off_track True when the order's status is not on the
 *                            normal path (cancelled, refunded, failed), in
 *                            which case no step is marked reached.
 *     @type array $steps     Each with slug, label, color and state.
 * }
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<ol class="rhot-timeline<?php echo ! empty( $timeline['off_track'] ) ? ' rhot-timeline--off' : ''; ?>">
	<?php foreach ( $timeline['steps'] as $step ) : ?>
		<li class="rhot-step rhot-step--<?php echo esc_attr( $step['state'] ); ?> rhot-status--<?php echo esc_attr( sanitize_html_class( $step['slug'] ) ); ?>"
			<?php echo 'current' === $step['state'] ? 'aria-current="step"' : ''; ?>>
			<span class="rhot-step__dot" aria-hidden="true"></span>
			<span class="rhot-step__label"><?php echo esc_html( $step['label'] ); ?></span>
		</li>
	<?php endforeach; ?>
</ol>
