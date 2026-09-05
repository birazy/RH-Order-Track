<?php
/**
 * A single order result.
 *
 * Override by copying this file to yourtheme/rh-order-track/order-card.php
 *
 * Escaping convention: keys ending in `_html` are already sanitized markup
 * (wp_kses_post); everything else is plain text and is escaped here.
 *
 * @package RH_Order_Track
 * @version 1.0.0
 *
 * @var array $order View model from RHOT_Formatter.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$status_class = 'rhot-status rhot-status--' . sanitize_html_class( $order['status'] );
?>
<article class="rhot-card">

	<header class="rhot-card__head">
		<?php if ( ! empty( $order['number'] ) ) : ?>
			<h4 class="rhot-card__number">
				<?php
				printf(
					/* translators: %s: order number */
					esc_html__( 'Order #%s', 'rh-order-track' ),
					esc_html( $order['number'] )
				);
				?>
			</h4>
		<?php endif; ?>

		<span class="<?php echo esc_attr( $status_class ); ?>">
			<?php echo esc_html( $order['status_label'] ); ?>
		</span>
	</header>

	<?php if ( ! empty( $order['timeline']['steps'] ) ) : ?>
		<?php RHOT_Template::render( 'timeline.php', array( 'timeline' => $order['timeline'] ) ); ?>
	<?php endif; ?>

	<?php if ( ! empty( $order['summary'] ) || ! empty( $order['meta_rows'] ) ) : ?>
		<dl class="rhot-rows">
			<?php foreach ( $order['summary'] as $row ) : ?>
				<div class="rhot-row">
					<dt class="rhot-row__label"><?php echo esc_html( $row['label'] ); ?></dt>
					<dd class="rhot-row__value"><?php echo esc_html( $row['value'] ); ?></dd>
				</div>
			<?php endforeach; ?>

			<?php foreach ( $order['meta_rows'] as $row ) : ?>
				<div class="rhot-row rhot-row--meta">
					<dt class="rhot-row__label"><?php echo esc_html( $row['label'] ); ?></dt>
					<dd class="rhot-row__value">
						<?php if ( ! empty( $row['url'] ) ) : ?>
							<a class="rhot-link" href="<?php echo esc_url( $row['url'] ); ?>" target="_blank" rel="nofollow noopener noreferrer"><?php echo esc_html( $row['value'] ); ?></a>
						<?php else : ?>
							<?php echo esc_html( $row['value'] ); ?>
						<?php endif; ?>
					</dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( ! empty( $order['items'] ) ) : ?>
		<ul class="rhot-items">
			<?php foreach ( $order['items'] as $item ) : ?>
				<li class="rhot-item">
					<?php if ( ! empty( $item['thumb_html'] ) ) : ?>
						<div class="rhot-item__thumb"><?php echo wp_kses_post( $item['thumb_html'] ); ?></div>
					<?php endif; ?>

					<div class="rhot-item__body">
						<span class="rhot-item__name"><?php echo esc_html( $item['name'] ); ?></span>

						<?php if ( ! empty( $item['sku'] ) ) : ?>
							<span class="rhot-item__sku">
								<?php
								printf(
									/* translators: %s: product SKU */
									esc_html__( 'SKU: %s', 'rh-order-track' ),
									esc_html( $item['sku'] )
								);
								?>
							</span>
						<?php endif; ?>

						<?php if ( ! empty( $item['meta'] ) ) : ?>
							<ul class="rhot-item__meta">
								<?php foreach ( $item['meta'] as $meta ) : ?>
									<li>
										<span class="rhot-item__meta-label"><?php echo esc_html( $meta['label'] ); ?>:</span>
										<?php echo wp_kses_post( $meta['value_html'] ); ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>

					<div class="rhot-item__figures">
						<?php if ( isset( $item['quantity'] ) ) : ?>
							<span class="rhot-item__qty">&times;&nbsp;<?php echo esc_html( number_format_i18n( $item['quantity'] ) ); ?></span>
						<?php endif; ?>

						<?php if ( ! empty( $item['price_html'] ) ) : ?>
							<span class="rhot-item__price"><?php echo wp_kses_post( $item['price_html'] ); ?></span>
						<?php endif; ?>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

	<?php if ( ! empty( $order['totals'] ) ) : ?>
		<dl class="rhot-rows rhot-rows--totals">
			<?php foreach ( $order['totals'] as $row ) : ?>
				<div class="rhot-row<?php echo ! empty( $row['is_total'] ) ? ' rhot-row--total' : ''; ?>">
					<dt class="rhot-row__label"><?php echo esc_html( $row['label'] ); ?></dt>
					<dd class="rhot-row__value"><?php echo wp_kses_post( $row['value_html'] ); ?></dd>
				</div>
			<?php endforeach; ?>
		</dl>
	<?php endif; ?>

	<?php if ( ! empty( $order['customer'] ) ) : ?>
		<?php $customer = $order['customer']; ?>
		<div class="rhot-customer">
			<?php if ( ! empty( $customer['name'] ) || ! empty( $customer['phone'] ) || ! empty( $customer['email'] ) ) : ?>
				<dl class="rhot-rows">
					<?php if ( ! empty( $customer['name'] ) ) : ?>
						<div class="rhot-row">
							<dt class="rhot-row__label"><?php esc_html_e( 'Name', 'rh-order-track' ); ?></dt>
							<dd class="rhot-row__value"><?php echo esc_html( $customer['name'] ); ?></dd>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $customer['phone'] ) ) : ?>
						<div class="rhot-row">
							<dt class="rhot-row__label"><?php esc_html_e( 'Phone', 'rh-order-track' ); ?></dt>
							<dd class="rhot-row__value"><?php echo esc_html( $customer['phone'] ); ?></dd>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $customer['email'] ) ) : ?>
						<div class="rhot-row">
							<dt class="rhot-row__label"><?php esc_html_e( 'Email', 'rh-order-track' ); ?></dt>
							<dd class="rhot-row__value"><?php echo esc_html( $customer['email'] ); ?></dd>
						</div>
					<?php endif; ?>
				</dl>
			<?php endif; ?>

			<?php if ( ! empty( $customer['billing_html'] ) || ! empty( $customer['shipping_html'] ) ) : ?>
				<div class="rhot-addresses">
					<?php if ( ! empty( $customer['billing_html'] ) ) : ?>
						<div class="rhot-address">
							<h5 class="rhot-address__title"><?php esc_html_e( 'Billing address', 'rh-order-track' ); ?></h5>
							<address><?php echo wp_kses_post( $customer['billing_html'] ); ?></address>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $customer['shipping_html'] ) ) : ?>
						<div class="rhot-address">
							<h5 class="rhot-address__title"><?php esc_html_e( 'Shipping address', 'rh-order-track' ); ?></h5>
							<address><?php echo wp_kses_post( $customer['shipping_html'] ); ?></address>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $customer['note'] ) ) : ?>
				<div class="rhot-note-block">
					<h5 class="rhot-address__title"><?php esc_html_e( 'Your note', 'rh-order-track' ); ?></h5>
					<p class="rhot-note"><?php echo esc_html( $customer['note'] ); ?></p>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $order['notes'] ) ) : ?>
		<ul class="rhot-notes">
			<?php foreach ( $order['notes'] as $note ) : ?>
				<li class="rhot-notes__item">
					<time class="rhot-notes__date"><?php echo esc_html( $note['date'] ); ?></time>
					<div class="rhot-notes__body"><?php echo wp_kses_post( $note['content_html'] ); ?></div>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>

</article>
