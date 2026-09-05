=== RH Order Track ===
Contributors: rubelhossain
Tags: woocommerce, order tracking, order status, elementor, shortcode
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL-2.0+
License URI: http://www.gnu.org/licenses/gpl-2.0.txt

Let customers track a WooCommerce order with their order number and phone number. Elementor widget and shortcode, fully configurable.

== Description ==

WooCommerce ships an order tracking shortcode that asks for an order number and a billing **email**. On cash-on-delivery stores most customers never give an email — they give a phone number. RH Order Track fills that gap, and makes everything about the form configurable from one dashboard screen.

**Two ways to place it**

* Elementor widget — "Order Tracking", in its own panel category.
* `[rh_order_track]` shortcode — for sites that don't use Elementor.

Both render the same markup through the same templates, so a site can use either or both.

**What you control**

* Which fields the customer fills in: order number, phone, or both. Requiring both is the default and the safest option.
* Which order statuses are visible at all, with your own wording, colour and position on the status timeline. Statuses registered by other plugins appear automatically.
* Exactly which order details are shown — products, images, SKU, quantities, prices, totals, addresses, notes. Anything you untick never leaves the server.
* Extra rows read from order meta, so a courier consignment number or delivery date can appear alongside the status. SteadFast's meta keys are detected and offered as one-click presets.

**Colours**

By default the form inherits your Elementor global colours or your theme's palette, so it looks like it belongs. Set your own colours in the Design tab and the plugin's colours take priority over the theme instead. Elementor's own style controls override both, per widget.

**Privacy note**

A phone-only lookup lets anyone who knows a customer's phone number see that customer's recent orders — the same trade-off courier tracking tools make. Requiring the order number as well (the default) closes that. Contact details are masked by default, only ticked details are ever sent to the browser, and lookups are rate limited per IP.

== Installation ==

1. Upload the `rh-order-track` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **RH Order T** in the dashboard menu.
4. On the **Shortcode & tools** tab, run **Rebuild phone index** once so orders that existed before installation can be found by phone.
5. Add the Elementor widget or the `[rh_order_track]` shortcode to a page.

== Frequently Asked Questions ==

= Do I have to run the index rebuild? =

Only for orders placed before the plugin was installed, and only if you want them found by phone number. New orders are indexed automatically, and looking up by order number plus phone works immediately either way.

= Does it work without Elementor? =

Yes. The shortcode and the tracking itself are completely independent of Elementor; only the widget requires it.

= Does it support High-Performance Order Storage? =

Yes. Every order read goes through the WooCommerce CRUD layer, and compatibility is declared explicitly. It works identically on legacy post storage.

= Can I change the layout? =

Copy any file from the plugin's `templates/` folder into `yourtheme/rh-order-track/` and edit it there. Your copy survives plugin updates.

== Changelog ==

= 1.0.0 =
* Initial release.
