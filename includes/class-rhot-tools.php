<?php
/**
 * Tools tab: phone-index rebuild, settings reset/export/import, and a
 * shortcode reference.
 *
 * @package RH_Order_Track
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RHOT_Tools {

	const BACKFILL_ACTION = 'rhot_backfill';
	const RESET_ACTION    = 'rhot_reset_settings';
	const IMPORT_ACTION   = 'rhot_import_settings';

	/**
	 * Orders per AJAX step. Deliberately batched rather than a single
	 * limit => -1 pass, which exhausts memory on any store with real order
	 * volume.
	 */
	const BATCH_SIZE = 100;

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_' . self::BACKFILL_ACTION, array( $this, 'handle_backfill_step' ) );
		add_action( 'admin_post_' . self::RESET_ACTION, array( $this, 'handle_reset' ) );
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
	}

	/* ---------------------------------------------------------------------
	 * Phone index rebuild
	 * ------------------------------------------------------------------ */

	/**
	 * Processes one batch and reports progress. Idempotent and resumable:
	 * an order whose index already matches is skipped, so re-running the tool
	 * costs nothing and an interrupted run can simply be started again.
	 */
	public function handle_backfill_step() {
		check_ajax_referer( self::BACKFILL_ACTION, 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'rh-order-track' ) ) );
		}

		if ( ! RHOT_Compat::has_woocommerce() ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce is not active.', 'rh-order-track' ) ) );
		}

		$page = isset( $_POST['page'] ) ? max( 1, absint( $_POST['page'] ) ) : 1;

		$total = $this->count_orders();

		$ids = wc_get_orders(
			array(
				'limit'   => self::BATCH_SIZE,
				'page'    => $page,
				'type'    => 'shop_order',
				'status'  => 'all',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);

		$updated = 0;

		foreach ( (array) $ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order instanceof WC_Order ) {
				continue;
			}

			$digits  = RHOT_Order_Index::normalize( $order->get_billing_phone() );
			$current = (string) $order->get_meta( RHOT_Order_Index::META_KEY, true );

			if ( $digits === $current ) {
				continue;
			}

			if ( '' === $digits ) {
				$order->delete_meta_data( RHOT_Order_Index::META_KEY );
			} else {
				$order->update_meta_data( RHOT_Order_Index::META_KEY, $digits );
			}

			$order->save();
			$updated++;
		}

		$processed = ( $page - 1 ) * self::BATCH_SIZE + count( (array) $ids );

		wp_send_json_success(
			array(
				'page'      => $page + 1,
				'processed' => min( $processed, $total ),
				'total'     => $total,
				'updated'   => $updated,
				'done'      => count( (array) $ids ) < self::BATCH_SIZE,
			)
		);
	}

	private function count_orders() {
		$result = wc_get_orders(
			array(
				'limit'    => 1,
				'type'     => 'shop_order',
				'status'   => 'all',
				'paginate' => true,
				'return'   => 'ids',
			)
		);

		return isset( $result->total ) ? (int) $result->total : 0;
	}

	private function count_indexed() {
		$result = wc_get_orders(
			array(
				'limit'        => 1,
				'type'         => 'shop_order',
				'status'       => 'all',
				'meta_key'     => RHOT_Order_Index::META_KEY,
				'meta_compare' => 'EXISTS',
				'paginate'     => true,
				'return'       => 'ids',
			)
		);

		return isset( $result->total ) ? (int) $result->total : 0;
	}

	/* ---------------------------------------------------------------------
	 * Reset / import
	 * ------------------------------------------------------------------ */

	public function handle_reset() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rh-order-track' ) );
		}

		check_admin_referer( self::RESET_ACTION );

		delete_option( RHOT_Settings::OPTION_KEY );

		$this->redirect( 'reset' );
	}

	public function handle_import() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'rh-order-track' ) );
		}

		check_admin_referer( self::IMPORT_ACTION );

		$raw = isset( $_POST['rhot_import'] ) ? wp_unslash( $_POST['rhot_import'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitised -- JSON is validated and every value is sanitized below.

		$decoded = json_decode( (string) $raw, true );

		if ( ! is_array( $decoded ) ) {
			$this->redirect( 'import_failed' );
		}

		// Routed through the normal sanitizer with no _tab key, so every
		// setting is rebuilt and whitelisted exactly as a form save would be.
		unset( $decoded['_tab'] );

		update_option( RHOT_Settings::OPTION_KEY, RHOT_Settings::sanitize( $decoded ) );

		$this->redirect( 'imported' );
	}

	private function redirect( $notice ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'        => RHOT_Admin::PAGE_SLUG,
					'tab'         => 'tools',
					'rhot_notice' => $notice,
				),
				admin_url( 'admin.php' )
			)
		);

		exit;
	}

	/* ---------------------------------------------------------------------
	 * Screen
	 * ------------------------------------------------------------------ */

	public function render_page() {
		$this->render_notice();
		$this->render_shortcode_reference();
		$this->render_backfill();
		$this->render_status();
		$this->render_transfer();
	}

	private function render_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Display only.
		$notice = isset( $_GET['rhot_notice'] ) ? sanitize_key( wp_unslash( $_GET['rhot_notice'] ) ) : '';

		$messages = array(
			'reset'         => array( 'success', __( 'Settings reset to their defaults.', 'rh-order-track' ) ),
			'imported'      => array( 'success', __( 'Settings imported.', 'rh-order-track' ) ),
			'import_failed' => array( 'error', __( 'That did not look like valid settings JSON. Nothing was changed.', 'rh-order-track' ) ),
		);

		if ( ! isset( $messages[ $notice ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			esc_attr( $messages[ $notice ][0] ),
			esc_html( $messages[ $notice ][1] )
		);
	}

	private function render_shortcode_reference() {
		?>
		<h2><?php esc_html_e( 'Shortcode', 'rh-order-track' ); ?></h2>
		<p><?php esc_html_e( 'Use this on any page or widget. On Elementor sites you can use the "Order Tracking" widget instead — both render exactly the same form.', 'rh-order-track' ); ?></p>

		<p>
			<input type="text" class="large-text code" readonly value="[rh_order_track]"
				onfocus="this.select();" />
		</p>

		<h3><?php esc_html_e( 'Optional attributes', 'rh-order-track' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Anything you leave out simply follows the settings on the other tabs.', 'rh-order-track' ); ?></p>

		<table class="widefat striped">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Attribute', 'rh-order-track' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Accepts', 'rh-order-track' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$atts = array(
					'mode'        => 'both | either | order_only | phone_only',
					'layout'      => 'stacked | inline',
					'title'       => __( 'Any text', 'rh-order-track' ),
					'subtitle'    => __( 'Any text', 'rh-order-track' ),
					'button_text' => __( 'Any text', 'rh-order-track' ),
					'order_label' => __( 'Any text', 'rh-order-track' ),
					'order_ph'    => __( 'Any text', 'rh-order-track' ),
					'phone_label' => __( 'Any text', 'rh-order-track' ),
					'phone_ph'    => __( 'Any text', 'rh-order-track' ),
					'show_email'  => 'yes | no',
					'class'       => __( 'Extra CSS class', 'rh-order-track' ),
				);

				foreach ( $atts as $att => $accepts ) :
					?>
					<tr>
						<td><code><?php echo esc_html( $att ); ?></code></td>
						<td><?php echo esc_html( $accepts ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<p class="description">
			<?php esc_html_e( 'Example:', 'rh-order-track' ); ?>
			<code>[rh_order_track mode="phone_only" layout="inline" title="Where is my parcel?"]</code>
		</p>
		<?php
	}

	private function render_backfill() {
		$indexed = $this->count_indexed();
		$total   = $this->count_orders();
		?>
		<hr />
		<h2><?php esc_html_e( 'Rebuild phone index', 'rh-order-track' ); ?></h2>

		<p>
			<?php esc_html_e( 'New orders are indexed automatically. Run this once to index the orders that already existed before the plugin was installed.', 'rh-order-track' ); ?>
		</p>

		<p>
			<strong>
				<?php
				printf(
					/* translators: 1: indexed order count, 2: total order count */
					esc_html__( '%1$s of %2$s orders indexed.', 'rh-order-track' ),
					esc_html( number_format_i18n( $indexed ) ),
					esc_html( number_format_i18n( $total ) )
				);
				?>
			</strong>
		</p>

		<p>
			<button type="button" class="button button-primary" id="rhot-backfill"><?php esc_html_e( 'Rebuild index', 'rh-order-track' ); ?></button>
			<span id="rhot-backfill-status" class="rhot-backfill-status"></span>
		</p>

		<div class="rhot-progress" id="rhot-progress" hidden>
			<div class="rhot-progress__bar" id="rhot-progress-bar"></div>
		</div>
		<?php
	}

	private function render_status() {
		?>
		<hr />
		<h2><?php esc_html_e( 'System status', 'rh-order-track' ); ?></h2>

		<table class="widefat striped">
			<tbody>
				<?php
				$rows = array(
					__( 'WooCommerce version', 'rh-order-track' )  => defined( 'WC_VERSION' ) ? WC_VERSION : __( 'Not detected', 'rh-order-track' ),
					__( 'Order storage', 'rh-order-track' )        => RHOT_Compat::is_hpos_active()
						? __( 'High-Performance Order Storage (HPOS)', 'rh-order-track' )
						: __( 'Legacy post storage', 'rh-order-track' ),
					__( 'Elementor', 'rh-order-track' )            => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : __( 'Not active — the shortcode still works', 'rh-order-track' ),
					__( 'Plugin version', 'rh-order-track' )       => RHOT_VERSION,
					__( 'Template overrides', 'rh-order-track' )   => $this->describe_overrides(),
				);

				foreach ( $rows as $label => $value ) :
					?>
					<tr>
						<td style="width:240px"><strong><?php echo esc_html( $label ); ?></strong></td>
						<td><?php echo esc_html( $value ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function describe_overrides() {
		$templates = array( 'form.php', 'result.php', 'order-card.php', 'timeline.php', 'preview.php' );

		$overridden = array();

		foreach ( $templates as $template ) {
			$located = RHOT_Template::locate( $template );

			if ( 0 !== strpos( $located, RHOT_PLUGIN_DIR ) ) {
				$overridden[] = $template;
			}
		}

		if ( empty( $overridden ) ) {
			return __( 'None — using the plugin templates', 'rh-order-track' );
		}

		return implode( ', ', $overridden );
	}

	private function render_transfer() {
		$settings = RHOT_Settings::get();

		unset( $settings['_tab'] );
		?>
		<hr />
		<h2><?php esc_html_e( 'Export, import and reset', 'rh-order-track' ); ?></h2>

		<h3><?php esc_html_e( 'Export', 'rh-order-track' ); ?></h3>
		<p class="description"><?php esc_html_e( 'Copy this to move your configuration to another site.', 'rh-order-track' ); ?></p>
		<textarea class="large-text code" rows="4" readonly onfocus="this.select();"><?php echo esc_textarea( wp_json_encode( $settings ) ); ?></textarea>

		<h3><?php esc_html_e( 'Import', 'rh-order-track' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>" />
			<?php wp_nonce_field( self::IMPORT_ACTION ); ?>
			<textarea name="rhot_import" class="large-text code" rows="4" placeholder='{"match_mode":"both", …}'></textarea>
			<?php submit_button( __( 'Import settings', 'rh-order-track' ), 'secondary' ); ?>
		</form>

		<h3><?php esc_html_e( 'Reset', 'rh-order-track' ); ?></h3>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
			onsubmit="return confirm('<?php echo esc_js( __( 'Reset every setting to its default? Order data is never touched.', 'rh-order-track' ) ); ?>');">
			<input type="hidden" name="action" value="<?php echo esc_attr( self::RESET_ACTION ); ?>" />
			<?php wp_nonce_field( self::RESET_ACTION ); ?>
			<?php submit_button( __( 'Reset all settings', 'rh-order-track' ), 'delete' ); ?>
		</form>
		<?php
	}
}
