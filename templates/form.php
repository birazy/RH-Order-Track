<?php
/**
 * Order tracking form.
 *
 * Override by copying this file to yourtheme/rh-order-track/form.php
 *
 * @package RH_Order_Track
 * @version 1.0.0
 *
 * @var string $id          Unique wrapper id.
 * @var string $classes     Root class list.
 * @var string $mode        Active match mode.
 * @var string $title       Heading, may be empty.
 * @var string $subtitle    Sub-heading, may be empty.
 * @var string $button_text Submit button label.
 * @var array  $fields      Field definitions.
 * @var string $nonce       Nonce for the AJAX lookup.
 * @var bool   $preview     True inside the Elementor editor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="<?php echo esc_attr( $classes ); ?>" id="<?php echo esc_attr( $id ); ?>" data-rhot-mode="<?php echo esc_attr( $mode ); ?>">

	<?php if ( '' !== $title || '' !== $subtitle ) : ?>
		<div class="rhot-head">
			<?php if ( '' !== $title ) : ?>
				<h3 class="rhot-title"><?php echo esc_html( $title ); ?></h3>
			<?php endif; ?>

			<?php if ( '' !== $subtitle ) : ?>
				<p class="rhot-subtitle"><?php echo esc_html( $subtitle ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<form class="rhot-form" method="post" novalidate>
		<input type="hidden" name="rhot_nonce" value="<?php echo esc_attr( $nonce ); ?>" />
		<input type="hidden" name="rhot_mode" value="<?php echo esc_attr( $mode ); ?>" />

		<div class="rhot-fields">
			<?php foreach ( $fields as $field ) : ?>
				<?php $field_id = $id . '-' . $field['key']; ?>
				<div class="rhot-field rhot-field--<?php echo esc_attr( str_replace( 'rhot_', '', $field['key'] ) ); ?>">
					<label class="rhot-label" for="<?php echo esc_attr( $field_id ); ?>">
						<?php echo esc_html( $field['label'] ); ?>
						<?php if ( ! empty( $field['required'] ) ) : ?>
							<span class="rhot-required" aria-hidden="true">*</span>
						<?php endif; ?>
					</label>
					<input
						class="rhot-input"
						type="<?php echo esc_attr( $field['type'] ); ?>"
						id="<?php echo esc_attr( $field_id ); ?>"
						name="<?php echo esc_attr( $field['key'] ); ?>"
						inputmode="<?php echo esc_attr( $field['inputmode'] ); ?>"
						autocomplete="<?php echo esc_attr( $field['autocomplete'] ); ?>"
						placeholder="<?php echo esc_attr( $field['placeholder'] ); ?>"
						<?php echo ! empty( $field['required'] ) ? 'required' : ''; ?>
					/>
				</div>
			<?php endforeach; ?>

			<div class="rhot-field rhot-field--submit">
				<button type="submit" class="rhot-btn">
					<span class="rhot-btn__label"><?php echo esc_html( $button_text ); ?></span>
					<span class="rhot-spinner" aria-hidden="true"></span>
				</button>
			</div>
		</div>

		<noscript>
			<p class="rhot-note"><?php esc_html_e( 'JavaScript needs to be enabled to track an order.', 'rh-order-track' ); ?></p>
		</noscript>
	</form>

	<div class="rhot-result" role="region" aria-live="polite">
		<?php
		if ( $preview ) {
			// Elementor editor only: a representative result so the designer
			// can style the panel without running a real lookup.
			RHOT_Template::render( 'preview.php' );
		}
		?>
	</div>
</div>
