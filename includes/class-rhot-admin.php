<?php
/**
 * Dashboard: the "RH Order T" menu and its tabbed settings screen.
 *
 * Each tab posts only the keys it owns (see RHOT_Settings::tab_fields), with
 * the active tab carried in a hidden _tab field. That is what lets an
 * unchecked checkbox on one tab save as "off" without wiping the other tabs.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Admin {

	const PAGE_SLUG = 'rh-order-track';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		new RHOT_Settings();

		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RHOT_PLUGIN_FILE ), array( $this, 'action_links' ) );
	}

	public function add_menu() {
		add_menu_page(
			__( 'RH Order Track', 'rh-order-track' ),
			__( 'RH Order T', 'rh-order-track' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render' ),
			'dashicons-location-alt',
			56
		);
	}

	public function action_links( $links ) {
		$settings = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) ),
			esc_html__( 'Settings', 'rh-order-track' )
		);

		array_unshift( $links, $settings );

		return $links;
	}

	public function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'rhot-admin', RHOT_PLUGIN_URL . 'assets/css/rhot-admin.css', array( 'wp-color-picker' ), RHOT_VERSION );

		wp_enqueue_script( 'rhot-admin', RHOT_PLUGIN_URL . 'assets/js/rhot-admin.js', array( 'jquery', 'wp-color-picker' ), RHOT_VERSION, true );

		wp_localize_script(
			'rhot-admin',
			'rhotAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => RHOT_Tools::BACKFILL_ACTION,
				'nonce'   => wp_create_nonce( RHOT_Tools::BACKFILL_ACTION ),
				'i18n'    => array(
					'running'  => __( 'Rebuilding…', 'rh-order-track' ),
					'done'     => __( 'Finished.', 'rh-order-track' ),
					'failed'   => __( 'Something went wrong. Please try again.', 'rh-order-track' ),
					'confirm'  => __( 'Rebuild the phone index for all existing orders?', 'rh-order-track' ),
					'removeRow' => __( 'Remove this row?', 'rh-order-track' ),
				),
			)
		);
	}

	private function tabs() {
		return array(
			'general'  => __( 'General', 'rh-order-track' ),
			'statuses' => __( 'Statuses', 'rh-order-track' ),
			'details'  => __( 'Order details', 'rh-order-track' ),
			'courier'  => __( 'Courier & custom rows', 'rh-order-track' ),
			'design'   => __( 'Design', 'rh-order-track' ),
			'tools'    => __( 'Shortcode & tools', 'rh-order-track' ),
		);
	}

	private function current_tab() {
		$tabs = $this->tabs();

		// Read-only tab switch; no state changes here, so no nonce needed.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';

		return isset( $tabs[ $tab ] ) ? $tab : 'general';
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap rhot-admin">
			<h1><?php esc_html_e( 'RH Order Track', 'rh-order-track' ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php foreach ( $this->tabs() as $slug => $label ) : ?>
					<a class="nav-tab <?php echo $slug === $tab ? 'nav-tab-active' : ''; ?>"
						href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&tab=' . $slug ) ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</h2>

			<?php
			if ( 'tools' === $tab ) {
				$this->render_tools();
			} else {
				$this->open_form( $tab );

				call_user_func( array( $this, 'render_' . $tab ) );

				submit_button();

				$this->close_form();
			}
			?>
		</div>
		<?php
	}

	private function open_form( $tab ) {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( RHOT_Settings::OPTION_GROUP ); ?>
			<input type="hidden" name="<?php echo esc_attr( RHOT_Settings::OPTION_KEY ); ?>[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
		<?php
	}

	private function close_form() {
		echo '</form>';
	}

	/* ---------------------------------------------------------------------
	 * Tabs
	 * ------------------------------------------------------------------ */

	private function render_general() {
		$settings = RHOT_Settings::get();
		?>
		<table class="form-table" role="presentation">
			<?php
			$this->row_select(
				'match_mode',
				__( 'What the customer must enter', 'rh-order-track' ),
				array(
					'both'       => __( 'Order number AND phone number (most secure)', 'rh-order-track' ),
					'either'     => __( 'Order number OR phone number', 'rh-order-track' ),
					'order_only' => __( 'Order number only', 'rh-order-track' ),
					'phone_only' => __( 'Phone number only', 'rh-order-track' ),
				),
				$settings['match_mode'],
				__( 'Requiring both is strongly recommended. Order numbers are sequential, so with "order number only" anyone can read any order by typing 1, 2, 3…', 'rh-order-track' )
			);

			$this->row_select(
				'form_layout',
				__( 'Form layout', 'rh-order-track' ),
				array(
					'stacked' => __( 'Stacked (fields on separate lines)', 'rh-order-track' ),
					'inline'  => __( 'Inline (fields side by side)', 'rh-order-track' ),
				),
				$settings['form_layout']
			);

			$this->row_text( 'form_title', __( 'Heading', 'rh-order-track' ), $settings['form_title'], __( 'Track your order', 'rh-order-track' ) );
			$this->row_text( 'form_subtitle', __( 'Sub-heading', 'rh-order-track' ), $settings['form_subtitle'], __( 'Leave empty to hide', 'rh-order-track' ) );
			$this->row_text( 'button_text', __( 'Button text', 'rh-order-track' ), $settings['button_text'], __( 'Track order', 'rh-order-track' ) );
			?>
		</table>

		<h3><?php esc_html_e( 'Fields', 'rh-order-track' ); ?></h3>
		<p class="description">
			<?php esc_html_e( 'A field required by the mode above is always shown, whatever these switches say.', 'rh-order-track' ); ?>
		</p>

		<table class="form-table" role="presentation">
			<?php
			$this->row_checkbox( 'field_order_enabled', __( 'Order number field', 'rh-order-track' ), $settings['field_order_enabled'], __( 'Show the order number field', 'rh-order-track' ) );
			$this->row_text( 'field_order_label', __( 'Order number label', 'rh-order-track' ), $settings['field_order_label'], __( 'Order number', 'rh-order-track' ) );
			$this->row_text( 'field_order_ph', __( 'Order number placeholder', 'rh-order-track' ), $settings['field_order_ph'], __( 'e.g. 1042', 'rh-order-track' ) );

			$this->row_checkbox( 'field_phone_enabled', __( 'Phone field', 'rh-order-track' ), $settings['field_phone_enabled'], __( 'Show the phone number field', 'rh-order-track' ) );
			$this->row_text( 'field_phone_label', __( 'Phone label', 'rh-order-track' ), $settings['field_phone_label'], __( 'Phone number', 'rh-order-track' ) );
			$this->row_text( 'field_phone_ph', __( 'Phone placeholder', 'rh-order-track' ), $settings['field_phone_ph'], __( 'The number you ordered with', 'rh-order-track' ) );

			$this->row_checkbox( 'field_email_enabled', __( 'Email field', 'rh-order-track' ), $settings['field_email_enabled'], __( 'Show an optional email field', 'rh-order-track' ) );
			$this->row_text( 'field_email_label', __( 'Email label', 'rh-order-track' ), $settings['field_email_label'], __( 'Email address', 'rh-order-track' ) );
			$this->row_text( 'field_email_ph', __( 'Email placeholder', 'rh-order-track' ), $settings['field_email_ph'], __( 'Optional', 'rh-order-track' ) );
			?>
		</table>

		<h3><?php esc_html_e( 'Behaviour and limits', 'rh-order-track' ); ?></h3>

		<table class="form-table" role="presentation">
			<?php
			$this->row_text( 'not_found_text', __( '"Not found" message', 'rh-order-track' ), $settings['not_found_text'], RHOT_Lookup::not_found_message(), 'large-text' );

			$this->row_number( 'max_results', __( 'Maximum orders shown', 'rh-order-track' ), $settings['max_results'], 1, 20, __( 'Only relevant when a phone number can match several orders.', 'rh-order-track' ) );
			$this->row_number( 'rate_limit_max', __( 'Attempts allowed', 'rh-order-track' ), $settings['rate_limit_max'], 1, 500, __( 'Per visitor IP, within the window below.', 'rh-order-track' ) );
			$this->row_number( 'rate_limit_window', __( 'Rate limit window (seconds)', 'rh-order-track' ), $settings['rate_limit_window'], 60, DAY_IN_SECONDS, __( 'The counter clears after this long with no attempts. 900 = 15 minutes.', 'rh-order-track' ) );

			$this->row_checkbox( 'url_prefill', __( 'Track from a link', 'rh-order-track' ), $settings['url_prefill'], __( 'Fill in and submit the form automatically from ?rhot_order=…&rhot_phone=…', 'rh-order-track' ), __( 'Useful for "track your order" links in SMS messages and emails.', 'rh-order-track' ) );

			$this->row_checkbox( 'enable_index', __( 'Phone index', 'rh-order-track' ), $settings['enable_index'], __( 'Keep a normalised copy of each order\'s phone number for fast lookups', 'rh-order-track' ), __( 'Recommended. Turning this off makes phone lookups slower and less reliable.', 'rh-order-track' ) );
			$this->row_checkbox( 'enable_fallbacks', __( 'Fallback searches', 'rh-order-track' ), $settings['enable_fallbacks'], __( 'If the index finds nothing, also try WooCommerce\'s own phone field', 'rh-order-track' ), __( 'Lets phone lookups work on old orders before the index has been rebuilt.', 'rh-order-track' ) );
			?>
		</table>
		<?php
	}

	private function render_statuses() {
		$settings = RHOT_Settings::get();
		$choices  = RHOT_Settings::get_status_choices();

		$timeline = (array) $settings['timeline_statuses'];
		?>
		<p class="description">
			<?php esc_html_e( 'Only the statuses you tick are ever shown to a customer. Statuses added by other plugins appear here automatically.', 'rh-order-track' ); ?>
		</p>

		<table class="widefat striped rhot-status-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Status', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Visible', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'On timeline', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Step', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Custom label', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Colour', 'rh-order-track' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $choices as $slug => $label ) : ?>
					<?php
					$step = array_search( $slug, $timeline, true );
					$step = ( false === $step ) ? '' : ( $step + 1 );
					?>
					<tr>
						<th scope="row">
							<strong><?php echo esc_html( $label ); ?></strong>
							<code><?php echo esc_html( $slug ); ?></code>
						</th>
						<td>
							<input type="checkbox"
								name="<?php echo esc_attr( RHOT_Settings::OPTION_KEY ); ?>[visible_statuses][]"
								value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( in_array( $slug, (array) $settings['visible_statuses'], true ) ); ?> />
						</td>
						<td>
							<input type="checkbox"
								name="<?php echo esc_attr( RHOT_Settings::OPTION_KEY ); ?>[timeline_statuses][]"
								value="<?php echo esc_attr( $slug ); ?>"
								<?php checked( in_array( $slug, $timeline, true ) ); ?> />
						</td>
						<td>
							<input type="number" class="small-text" min="1" max="20"
								name="<?php echo esc_attr( RHOT_Settings::OPTION_KEY ); ?>[timeline_order][<?php echo esc_attr( $slug ); ?>]"
								value="<?php echo esc_attr( $step ); ?>" />
						</td>
						<td>
							<input type="text" class="regular-text"
								name="<?php echo esc_attr( RHOT_Settings::OPTION_KEY ); ?>[status_labels][<?php echo esc_attr( $slug ); ?>]"
								value="<?php echo esc_attr( isset( $settings['status_labels'][ $slug ] ) ? $settings['status_labels'][ $slug ] : '' ); ?>"
								placeholder="<?php echo esc_attr( $label ); ?>" />
						</td>
						<td>
							<input type="text" class="rhot-color"
								name="<?php echo esc_attr( RHOT_Settings::OPTION_KEY ); ?>[status_colors][<?php echo esc_attr( $slug ); ?>]"
								value="<?php echo esc_attr( isset( $settings['status_colors'][ $slug ] ) ? $settings['status_colors'][ $slug ] : '' ); ?>" />
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Hidden statuses', 'rh-order-track' ); ?></h3>

		<table class="form-table" role="presentation">
			<?php
			$this->row_select(
				'hidden_status_action',
				__( 'When an order is in a hidden status', 'rh-order-track' ),
				array(
					'generic'   => __( 'Show a neutral message', 'rh-order-track' ),
					'not_found' => __( 'Treat it as not found', 'rh-order-track' ),
				),
				$settings['hidden_status_action']
			);

			$this->row_text( 'hidden_status_text', __( 'Neutral message', 'rh-order-track' ), $settings['hidden_status_text'], __( 'Your order is being processed.', 'rh-order-track' ), 'large-text' );
			?>
		</table>
		<?php
	}

	private function render_details() {
		$settings = RHOT_Settings::get();

		$groups = array(
			__( 'Summary', 'rh-order-track' )         => array(
				'show_timeline'        => __( 'Status timeline', 'rh-order-track' ),
				'show_order_number'    => __( 'Order number', 'rh-order-track' ),
				'show_date'            => __( 'Order date', 'rh-order-track' ),
				'show_payment_method'  => __( 'Payment method', 'rh-order-track' ),
				'show_shipping_method' => __( 'Shipping method', 'rh-order-track' ),
			),
			__( 'Products', 'rh-order-track' )        => array(
				'show_items'      => __( 'Product list', 'rh-order-track' ),
				'show_thumbnails' => __( 'Product images', 'rh-order-track' ),
				'show_quantity'   => __( 'Quantity', 'rh-order-track' ),
				'show_item_price' => __( 'Line price', 'rh-order-track' ),
				'show_sku'        => __( 'SKU', 'rh-order-track' ),
				'show_item_meta'  => __( 'Variation / item meta', 'rh-order-track' ),
			),
			__( 'Totals', 'rh-order-track' )          => array(
				'show_subtotal'       => __( 'Subtotal', 'rh-order-track' ),
				'show_discount'       => __( 'Discount', 'rh-order-track' ),
				'show_shipping_total' => __( 'Shipping cost', 'rh-order-track' ),
				'show_tax'            => __( 'Tax', 'rh-order-track' ),
				'show_total'          => __( 'Order total', 'rh-order-track' ),
			),
			__( 'Customer', 'rh-order-track' )        => array(
				'show_billing_name'     => __( 'Name', 'rh-order-track' ),
				'show_billing_phone'    => __( 'Phone number', 'rh-order-track' ),
				'show_billing_email'    => __( 'Email address', 'rh-order-track' ),
				'show_billing_address'  => __( 'Billing address', 'rh-order-track' ),
				'show_shipping_address' => __( 'Shipping address', 'rh-order-track' ),
				'show_customer_note'    => __( 'Customer note', 'rh-order-track' ),
				'show_order_notes'      => __( 'Notes sent to the customer', 'rh-order-track' ),
			),
		);
		?>
		<p class="description">
			<?php esc_html_e( 'Only what you tick here is sent to the browser — anything unticked never leaves the server.', 'rh-order-track' ); ?>
		</p>

		<?php foreach ( $groups as $heading => $fields ) : ?>
			<h3><?php echo esc_html( $heading ); ?></h3>
			<table class="form-table" role="presentation">
				<?php foreach ( $fields as $key => $label ) : ?>
					<?php $this->row_checkbox( $key, $label, $settings[ $key ], __( 'Show', 'rh-order-track' ) ); ?>
				<?php endforeach; ?>
			</table>
		<?php endforeach; ?>

		<h3><?php esc_html_e( 'Privacy', 'rh-order-track' ); ?></h3>
		<table class="form-table" role="presentation">
			<?php
			$this->row_checkbox(
				'mask_contact',
				__( 'Mask contact details', 'rh-order-track' ),
				$settings['mask_contact'],
				__( 'Hide most of the phone number and email (0171*****78)', 'rh-order-track' ),
				__( 'Strongly recommended unless the customer must enter both the order number and the phone number.', 'rh-order-track' )
			);
			?>
		</table>
		<?php
	}

	private function render_courier() {
		$settings = RHOT_Settings::get();
		$rows     = (array) $settings['meta_rows'];

		$suggestions = $this->courier_suggestions();
		?>
		<p class="description">
			<?php esc_html_e( 'Show any extra value stored on the order — a courier consignment number, a delivery date, anything another plugin saves as order meta.', 'rh-order-track' ); ?>
		</p>

		<?php if ( ! empty( $suggestions ) ) : ?>
			<div class="notice notice-info inline">
				<p>
					<strong><?php esc_html_e( 'Detected on this site:', 'rh-order-track' ); ?></strong>
					<?php foreach ( $suggestions as $key => $label ) : ?>
						<button type="button" class="button button-small rhot-suggest"
							data-label="<?php echo esc_attr( $label ); ?>"
							data-key="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $label ); ?> <code><?php echo esc_html( $key ); ?></code>
						</button>
					<?php endforeach; ?>
				</p>
			</div>
		<?php endif; ?>

		<table class="widefat striped rhot-repeater" id="rhot-meta-rows">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Label shown to the customer', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Order meta key', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Link URL (optional)', 'rh-order-track' ); ?></th>
					<th scope="col" class="rhot-repeater__actions"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $index => $row ) : ?>
					<?php $this->repeater_row( $index, $row ); ?>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<td colspan="4">
						<button type="button" class="button" id="rhot-add-row"><?php esc_html_e( 'Add row', 'rh-order-track' ); ?></button>
					</td>
				</tr>
			</tfoot>
		</table>

		<script type="text/template" id="rhot-row-template">
			<?php $this->repeater_row( '__INDEX__', array() ); ?>
		</script>

		<p class="description">
			<?php esc_html_e( 'In the link URL, {value} is replaced by the meta value — for example https://steadfast.com.bd/t/{value}', 'rh-order-track' ); ?>
		</p>
		<?php
	}

	private function repeater_row( $index, $row ) {
		$name = RHOT_Settings::OPTION_KEY . '[meta_rows][' . $index . ']';
		?>
		<tr class="rhot-repeater__row">
			<td>
				<input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[label]"
					value="<?php echo esc_attr( isset( $row['label'] ) ? $row['label'] : '' ); ?>"
					placeholder="<?php esc_attr_e( 'Tracking number', 'rh-order-track' ); ?>" />
			</td>
			<td>
				<input type="text" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[meta_key]"
					value="<?php echo esc_attr( isset( $row['meta_key'] ) ? $row['meta_key'] : '' ); ?>"
					placeholder="steadfast_consignment_id" />
			</td>
			<td>
				<input type="url" class="regular-text code" name="<?php echo esc_attr( $name ); ?>[url]"
					value="<?php echo esc_attr( isset( $row['url'] ) ? $row['url'] : '' ); ?>"
					placeholder="https://example.com/track/{value}" />
			</td>
			<td class="rhot-repeater__actions">
				<button type="button" class="button-link rhot-remove-row" aria-label="<?php esc_attr_e( 'Remove row', 'rh-order-track' ); ?>">&times;</button>
			</td>
		</tr>
		<?php
	}

	/**
	 * Meta keys worth offering as one-click presets, detected from what is
	 * actually installed rather than guessed.
	 */
	private function courier_suggestions() {
		$suggestions = array();

		if ( class_exists( 'STDF_Courier_Main' ) || function_exists( 'stdf_get_status_by_consignment_id' ) ) {
			$suggestions['steadfast_consignment_id'] = __( 'SteadFast consignment ID', 'rh-order-track' );
			$suggestions['stdf_delivery_status']     = __( 'SteadFast delivery status', 'rh-order-track' );
		}

		/**
		 * Filters the suggested order meta keys shown on the Courier tab.
		 *
		 * @param array $suggestions meta_key => label.
		 */
		return apply_filters( 'rhot_courier_suggestions', $suggestions );
	}

	private function render_design() {
		$settings = RHOT_Settings::get();
		?>
		<table class="form-table" role="presentation">
			<?php
			$this->row_select(
				'color_source',
				__( 'Colours', 'rh-order-track' ),
				array(
					'auto'   => __( 'Inherit from the theme (recommended)', 'rh-order-track' ),
					'custom' => __( 'Use my own colours below', 'rh-order-track' ),
				),
				$settings['color_source'],
				__( 'On "inherit", the form picks up your Elementor global colours or theme palette automatically. Switch to "my own colours" and the plugin\'s colours take priority over the theme.', 'rh-order-track' )
			);
			?>
		</table>

		<table class="form-table rhot-colors" role="presentation">
			<?php
			$colors = array(
				'color_primary'    => __( 'Primary / button', 'rh-order-track' ),
				'color_on_primary' => __( 'Button text', 'rh-order-track' ),
				'color_text'       => __( 'Text', 'rh-order-track' ),
				'color_muted'      => __( 'Muted text', 'rh-order-track' ),
				'color_surface'    => __( 'Card background', 'rh-order-track' ),
				'color_border'     => __( 'Borders', 'rh-order-track' ),
				'color_success'    => __( 'Success', 'rh-order-track' ),
				'color_danger'     => __( 'Error', 'rh-order-track' ),
			);

			foreach ( $colors as $key => $label ) {
				$this->row_color( $key, $label, $settings[ $key ] );
			}
			?>
		</table>

		<table class="form-table" role="presentation">
			<?php
			$this->row_text( 'radius', __( 'Corner radius', 'rh-order-track' ), $settings['radius'], '10px', 'small-text' );

			$this->row_checkbox(
				'force_styles',
				__( 'Force plugin styles', 'rh-order-track' ),
				$settings['force_styles'],
				__( 'Override stubborn theme styles on buttons and inputs', 'rh-order-track' ),
				__( 'Only needed if your theme is still restyling the form. Colours still come from the settings above.', 'rh-order-track' )
			);

			$this->row_checkbox(
				'load_css',
				__( 'Load the plugin stylesheet', 'rh-order-track' ),
				$settings['load_css'],
				__( 'Load rhot-track.css on the front end', 'rh-order-track' ),
				__( 'Turn off only if you are styling the form entirely yourself.', 'rh-order-track' )
			);
			?>
		</table>
		<?php
	}

	private function render_tools() {
		RHOT_Tools::instance()->render_page();
	}

	/* ---------------------------------------------------------------------
	 * Field helpers
	 * ------------------------------------------------------------------ */

	private function field_name( $key ) {
		return RHOT_Settings::OPTION_KEY . '[' . $key . ']';
	}

	private function row_text( $key, $label, $value, $placeholder = '', $class = 'regular-text' ) {
		?>
		<tr>
			<th scope="row"><label for="rhot-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" id="rhot-<?php echo esc_attr( $key ); ?>" class="<?php echo esc_attr( $class ); ?>"
					name="<?php echo esc_attr( $this->field_name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>" />
			</td>
		</tr>
		<?php
	}

	private function row_number( $key, $label, $value, $min, $max, $description = '' ) {
		?>
		<tr>
			<th scope="row"><label for="rhot-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="number" id="rhot-<?php echo esc_attr( $key ); ?>" class="small-text"
					name="<?php echo esc_attr( $this->field_name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="1" />
				<?php $this->description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	private function row_checkbox( $key, $label, $value, $checkbox_label, $description = '' ) {
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>" value="yes" <?php checked( 'yes', $value ); ?> />
					<?php echo esc_html( $checkbox_label ); ?>
				</label>
				<?php $this->description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	private function row_select( $key, $label, $options, $value, $description = '' ) {
		?>
		<tr>
			<th scope="row"><label for="rhot-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<select id="rhot-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( $this->field_name( $key ) ); ?>">
					<?php foreach ( $options as $option_value => $option_label ) : ?>
						<option value="<?php echo esc_attr( $option_value ); ?>" <?php selected( $option_value, $value ); ?>>
							<?php echo esc_html( $option_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<?php $this->description( $description ); ?>
			</td>
		</tr>
		<?php
	}

	private function row_color( $key, $label, $value ) {
		$inherited = RHOT_Styles::describe_inherited( $key );
		?>
		<tr>
			<th scope="row"><label for="rhot-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<input type="text" id="rhot-<?php echo esc_attr( $key ); ?>" class="rhot-color"
					name="<?php echo esc_attr( $this->field_name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>" />

				<?php if ( ! empty( $inherited['color'] ) ) : ?>
					<span class="rhot-inherited">
						<span class="rhot-inherited__swatch" style="background-color: <?php echo esc_attr( $inherited['color'] ); ?>"></span>
						<?php
						printf(
							/* translators: 1: colour value, 2: where it comes from */
							esc_html__( 'Currently inheriting %1$s from %2$s', 'rh-order-track' ),
							esc_html( $inherited['color'] ),
							esc_html( $inherited['source'] )
						);
						?>
					</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	private function description( $text ) {
		if ( '' === $text ) {
			return;
		}

		printf( '<p class="description">%s</p>', esc_html( $text ) );
	}
}
