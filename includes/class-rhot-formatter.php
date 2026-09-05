<?php
/**
 * Turns a WC_Order into a safe, settings-filtered view model for templates.
 *
 * Escaping convention used throughout: every value is already safe for
 * output. Keys ending in `_html` hold markup that has been through
 * wp_kses_post(); every other value is plain text that templates escape with
 * esc_html(). Nothing raw from the database reaches a template.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Formatter {

	/**
	 * @param WC_Order $order  Order to describe.
	 * @param bool     $single True when this is the only result, which unlocks
	 *                         the more expensive per-order details.
	 */
	public static function to_view_model( $order, $single = true ) {
		if ( ! $order instanceof WC_Order ) {
			return array();
		}

		$status = $order->get_status();

		$data = array(
			'id'           => $order->get_id(),
			'status'       => $status,
			'status_label' => RHOT_Settings::get_status_label( $status ),
			'status_color' => RHOT_Settings::get_status_color( $status ),
			'timeline'     => RHOT_Settings::is_on( 'show_timeline' ) ? self::build_timeline( $status ) : array(),
			'summary'      => self::build_summary( $order ),
			'items'        => RHOT_Settings::is_on( 'show_items' ) ? self::build_items( $order ) : array(),
			'totals'       => self::build_totals( $order ),
			'customer'     => self::build_customer( $order ),
			'meta_rows'    => self::build_meta_rows( $order ),
			'notes'        => array(),
		);

		if ( RHOT_Settings::is_on( 'show_order_number' ) ) {
			$data['number'] = $order->get_order_number();
		}

		// get_customer_order_notes() temporarily unhooks WooCommerce's
		// comments_clauses filter on every call, so it is fetched only for a
		// single resolved order and never inside a multi-result loop.
		if ( $single && RHOT_Settings::is_on( 'show_order_notes' ) ) {
			$data['notes'] = self::build_notes( $order );
		}

		/**
		 * Filters the whole view model before it reaches a template.
		 *
		 * @param array    $data  View model.
		 * @param WC_Order $order Source order.
		 */
		return apply_filters( 'rhot_order_view_model', $data, $order );
	}

	/**
	 * A stepper across the statuses the admin nominated as the normal path.
	 *
	 * An order sitting in a status that is not on that path — cancelled,
	 * refunded, failed — is "off track": showing it as step 0 of 3 would be
	 * actively misleading, so the template renders a standalone badge instead.
	 */
	private static function build_timeline( $status ) {
		$steps = (array) RHOT_Settings::get_value( 'timeline_statuses' );

		$steps = array_values(
			array_filter(
				$steps,
				array( 'RHOT_Settings', 'is_status_visible' )
			)
		);

		if ( empty( $steps ) ) {
			return array();
		}

		$position = array_search( $status, $steps, true );
		$off      = ( false === $position );

		$built = array();

		foreach ( $steps as $index => $slug ) {
			if ( $off ) {
				$state = 'upcoming';
			} elseif ( $index < $position ) {
				$state = 'done';
			} elseif ( $index === $position ) {
				$state = 'current';
			} else {
				$state = 'upcoming';
			}

			$built[] = array(
				'slug'  => $slug,
				'label' => RHOT_Settings::get_status_label( $slug ),
				'color' => RHOT_Settings::get_status_color( $slug ),
				'state' => $state,
			);
		}

		return array(
			'off_track' => $off,
			'steps'     => $built,
		);
	}

	private static function build_summary( $order ) {
		$rows = array();

		if ( RHOT_Settings::is_on( 'show_date' ) ) {
			$date = $order->get_date_created();

			// get_date_created() is nullable — an order created through some
			// import paths has no date at all.
			if ( $date ) {
				$rows[] = array(
					'key'   => 'date',
					'label' => __( 'Order date', 'rh-order-track' ),
					'value' => wc_format_datetime( $date ),
				);
			}
		}

		if ( RHOT_Settings::is_on( 'show_payment_method' ) ) {
			$payment = $order->get_payment_method_title();

			if ( '' !== $payment ) {
				$rows[] = array(
					'key'   => 'payment',
					'label' => __( 'Payment method', 'rh-order-track' ),
					'value' => $payment,
				);
			}
		}

		if ( RHOT_Settings::is_on( 'show_shipping_method' ) ) {
			$shipping = $order->get_shipping_method();

			// Empty for virtual/downloadable orders with no shipping line.
			if ( '' !== $shipping ) {
				$rows[] = array(
					'key'   => 'shipping',
					'label' => __( 'Shipping method', 'rh-order-track' ),
					'value' => $shipping,
				);
			}
		}

		return $rows;
	}

	private static function build_items( $order ) {
		$items = array();

		$show_thumb = RHOT_Settings::is_on( 'show_thumbnails' );
		$show_sku   = RHOT_Settings::is_on( 'show_sku' );
		$show_meta  = RHOT_Settings::is_on( 'show_item_meta' );
		$show_qty   = RHOT_Settings::is_on( 'show_quantity' );
		$show_price = RHOT_Settings::is_on( 'show_item_price' );

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$product = $item->get_product();
			$exists  = $product instanceof WC_Product;

			$row = array(
				// The line item carries its own name snapshot, which survives
				// the product being renamed or deleted afterwards.
				'name' => $item->get_name(),
			);

			if ( $show_thumb ) {
				$row['thumb_html'] = wp_kses_post(
					$exists
						? $product->get_image( 'woocommerce_thumbnail', array(), true )
						: wc_placeholder_img( 'woocommerce_thumbnail' )
				);
			}

			if ( $show_sku && $exists && $product->get_sku() ) {
				$row['sku'] = $product->get_sku();
			}

			if ( $show_qty ) {
				$row['quantity'] = $item->get_quantity();
			}

			if ( $show_price ) {
				$row['price_html'] = wp_kses_post( $order->get_formatted_line_subtotal( $item ) );
			}

			if ( $show_meta ) {
				// display_value arrives pre-sanitized by WooCommerce
				// (wp_kses_post + make_clickable + wpautop).
				$meta = array();

				foreach ( $item->get_formatted_meta_data() as $entry ) {
					$meta[] = array(
						'label'      => wp_strip_all_tags( $entry->display_key ),
						'value_html' => wp_kses_post( $entry->display_value ),
					);
				}

				if ( ! empty( $meta ) ) {
					$row['meta'] = $meta;
				}
			}

			$items[] = $row;
		}

		return $items;
	}

	private static function build_totals( $order ) {
		$rows = array();

		if ( RHOT_Settings::is_on( 'show_subtotal' ) ) {
			$rows[] = array(
				'label'      => __( 'Subtotal', 'rh-order-track' ),
				'value_html' => wp_kses_post( $order->get_subtotal_to_display() ),
			);
		}

		if ( RHOT_Settings::is_on( 'show_discount' ) && $order->get_total_discount() > 0 ) {
			$rows[] = array(
				'label'      => __( 'Discount', 'rh-order-track' ),
				'value_html' => wp_kses_post( $order->get_discount_to_display() ),
			);
		}

		if ( RHOT_Settings::is_on( 'show_shipping_total' ) && $order->get_shipping_method() ) {
			$rows[] = array(
				'label'      => __( 'Shipping', 'rh-order-track' ),
				'value_html' => wp_kses_post( $order->get_shipping_to_display() ),
			);
		}

		if ( RHOT_Settings::is_on( 'show_tax' ) && $order->get_total_tax() > 0 ) {
			$rows[] = array(
				'label'      => __( 'Tax', 'rh-order-track' ),
				'value_html' => wp_kses_post( wc_price( $order->get_total_tax(), array( 'currency' => $order->get_currency() ) ) ),
			);
		}

		if ( RHOT_Settings::is_on( 'show_total' ) ) {
			$rows[] = array(
				'label'      => __( 'Total', 'rh-order-track' ),
				'value_html' => wp_kses_post( $order->get_formatted_order_total() ),
				'is_total'   => true,
			);
		}

		return $rows;
	}

	private static function build_customer( $order ) {
		$customer = array();
		$mask     = RHOT_Settings::is_on( 'mask_contact' );

		if ( RHOT_Settings::is_on( 'show_billing_name' ) ) {
			$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );

			if ( '' !== $name ) {
				$customer['name'] = $name;
			}
		}

		if ( RHOT_Settings::is_on( 'show_billing_phone' ) ) {
			$phone = $order->get_billing_phone();

			if ( '' !== $phone ) {
				$customer['phone'] = $mask ? self::mask_phone( $phone ) : $phone;
			}
		}

		if ( RHOT_Settings::is_on( 'show_billing_email' ) ) {
			$email = $order->get_billing_email();

			if ( '' !== $email ) {
				$customer['email'] = $mask ? self::mask_email( $email ) : $email;
			}
		}

		if ( RHOT_Settings::is_on( 'show_billing_address' ) ) {
			$address = $order->get_formatted_billing_address();

			if ( $address ) {
				$customer['billing_html'] = wp_kses_post( $address );
			}
		}

		if ( RHOT_Settings::is_on( 'show_shipping_address' ) && $order->has_shipping_address() ) {
			$address = $order->get_formatted_shipping_address();

			if ( $address ) {
				$customer['shipping_html'] = wp_kses_post( $address );
			}
		}

		if ( RHOT_Settings::is_on( 'show_customer_note' ) ) {
			$note = $order->get_customer_note();

			if ( '' !== $note ) {
				$customer['note'] = $note;
			}
		}

		return $customer;
	}

	private static function build_notes( $order ) {
		$notes = array();

		foreach ( $order->get_customer_order_notes() as $note ) {
			$notes[] = array(
				'date'         => mysql2date( get_option( 'date_format' ), $note->comment_date ),
				'content_html' => wp_kses_post( wpautop( wptexturize( $note->comment_content ) ) ),
			);
		}

		return $notes;
	}

	/**
	 * Admin-configured extra rows read straight off the order's meta — the
	 * generic way to surface a courier consignment id or any other plugin's
	 * order meta without hard-coding an integration.
	 */
	private static function build_meta_rows( $order ) {
		$rows = array();

		foreach ( (array) RHOT_Settings::get_value( 'meta_rows' ) as $row ) {
			if ( empty( $row['meta_key'] ) ) {
				continue;
			}

			$value = RHOT_Order_Index::read_meta( $order, $row['meta_key'] );

			if ( '' === $value ) {
				continue;
			}

			$built = array(
				'label' => isset( $row['label'] ) ? $row['label'] : $row['meta_key'],
				'value' => $value,
			);

			if ( ! empty( $row['url'] ) ) {
				$built['url'] = esc_url_raw( str_replace( '{value}', rawurlencode( $value ), $row['url'] ) );
			}

			$rows[] = $built;
		}

		return $rows;
	}

	/**
	 * 01712345678 -> 0171*****78. Keeps enough for the customer to recognise
	 * their own number while not handing a full number to someone who guessed
	 * an order id.
	 */
	public static function mask_phone( $phone ) {
		$phone = trim( (string) $phone );
		$len   = strlen( $phone );

		if ( $len < 7 ) {
			return str_repeat( '*', $len );
		}

		return substr( $phone, 0, 4 ) . str_repeat( '*', $len - 6 ) . substr( $phone, -2 );
	}

	public static function mask_email( $email ) {
		$parts = explode( '@', (string) $email );

		if ( 2 !== count( $parts ) || '' === $parts[0] ) {
			return '';
		}

		return substr( $parts[0], 0, 1 ) . str_repeat( '*', max( 3, strlen( $parts[0] ) - 1 ) ) . '@' . $parts[1];
	}
}
