# RH Order Track

Customer-facing WooCommerce order tracking — an Elementor widget **and** a shortcode, with every field, status, detail and colour configurable from one dashboard screen.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-HPOS%20ready-96588a.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-777bb4.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0%2B-green.svg)](LICENSE)

---

## Why this exists

WooCommerce ships an order-tracking shortcode that asks for an order number and a billing **email**. On cash-on-delivery stores most customers never give an email — they give a phone number. And that shortcode has no settings at all: you cannot choose which statuses customers see, which order details are exposed, or what any of it looks like.

RH Order Track fills that gap.

## Features

- **Two ways to place it** — an Elementor widget in its own panel category, or `[rh_order_track]` for sites that don't use Elementor. Both render identical markup through the same templates.
- **Configurable lookup** — order number, phone, or both. Requiring both is the default, and the safest option.
- **Status control** — pick exactly which order statuses are visible, give each one your own wording, colour and position on the status timeline. Statuses registered by other plugins appear automatically.
- **Detail control** — around 25 individual toggles for products, images, SKU, quantities, prices, totals, addresses and notes. Anything unticked never leaves the server.
- **Courier / custom rows** — surface any order meta (a consignment number, a delivery date) as an extra row, optionally as a clickable tracking link. SteadFast's meta keys are detected and offered as one-click presets.
- **Theme-aware colours** — inherits your Elementor global colours or theme palette by default; set your own and the plugin's colours take priority over the theme.
- **Theme-overridable templates** — copy any file from `templates/` into `yourtheme/rh-order-track/`.
- **HPOS ready** — compatibility is declared explicitly, and every order read goes through the WooCommerce CRUD layer. Works identically on legacy post storage.
- **Bengali translation included** (`bn_BD`), with English as the default.

## Requirements

| | |
|---|---|
| WordPress | 6.0+ |
| WooCommerce | 8.0+ (tested on 11.0.1, HPOS enabled) |
| PHP | 7.4+ |
| Elementor | Optional — only the widget needs it |

## Installation

1. Copy the `rh-order-track` folder into `wp-content/plugins/`, or upload a zip of it via **Plugins → Add New → Upload**.
2. Activate the plugin.
3. Open **RH Order T** in the dashboard menu.
4. On **Shortcode & tools**, run **Rebuild phone index** once, so orders that existed before installation can be found by phone.
5. Drop the Elementor widget or the shortcode onto a page.

## Usage

### Shortcode

```
[rh_order_track]
```

Every attribute is optional; anything you leave out follows the dashboard settings.

| Attribute | Accepts |
|---|---|
| `mode` | `both` · `either` · `order_only` · `phone_only` |
| `layout` | `stacked` · `inline` |
| `title` | Any text |
| `subtitle` | Any text |
| `button_text` | Any text |
| `order_label` / `order_ph` | Any text |
| `phone_label` / `phone_ph` | Any text |
| `email_label` / `email_ph` | Any text |
| `show_order` / `show_phone` / `show_email` | `yes` · `no` |
| `class` | Extra CSS class on the wrapper |

```
[rh_order_track mode="phone_only" layout="inline" title="Where is my parcel?"]
```

### Elementor

Add the **Order Tracking** widget (category: *RH Order Track*). Content controls left empty inherit the global settings, so a freshly dropped widget already matches your dashboard configuration.

### Tracking from a link

Enable **Track from a link** in General, then send customers straight to their result:

```
https://example.com/track/?rhot_order=1042&rhot_phone=01712345678
```

## How the colours work

Two independent layers, which is what makes "inherit the theme" and "the plugin wins" compatible rather than contradictory:

**Value layer** — a CSS custom-property fallback chain decides *which* colour:

```css
--rhot-primary: var(--rhot-primary-ovr,
                var(--e-global-color-primary,
                var(--wp--preset--color--primary, #2563eb)));
```

**Application layer** — selector specificity decides *which element gets it*. Everything is scoped to `.rhot-scope`, so theme rules on bare `button` / `input` / `ul` lose without a single `!important`.

Priority, highest first:

1. Elementor widget style control (`{{WRAPPER}}`)
2. Plugin settings → Design tab
3. Elementor kit global colours
4. `theme.json` palette presets
5. Built-in fallback

Because the widget controls set the same `--rhot-*-ovr` variables rather than concrete properties, per-widget overrides beat global settings automatically — there is no override logic to maintain. A **Force plugin styles** toggle adds a hardening block for unusually aggressive themes; it still resolves through the same variables, so it forces the *application* without discarding inheritance.

## Template overrides

Copy any of these into `yourtheme/rh-order-track/` and edit your copy:

```
templates/form.php         The tracking form
templates/result.php       Results wrapper
templates/order-card.php   One order
templates/timeline.php     Status stepper
templates/preview.php      Sample result shown in the Elementor editor
```

**Shortcode & tools → System status** reports which templates a theme is currently overriding.

## Hooks

| Filter | Purpose |
|---|---|
| `rhot_lookup_order_id` | Translate a display order number into an order ID |
| `rhot_order_view_model` | Adjust the data passed to templates |
| `rhot_locate_template` | Change where a template is loaded from |
| `rhot_courier_suggestions` | Add meta-key presets to the Courier tab |

WooCommerce's own `woocommerce_shortcode_order_tracking_order_id` is also applied, so an installed sequential-order-number plugin keeps working here without extra integration.

## Security and privacy

Order tracking is unauthenticated by nature, so the defaults are deliberately conservative:

- **Default mode requires both** the order number and the phone number. Order IDs are sequential, so an order-number-only form is an oracle for reading anybody's order — the plugin ships that option, but not as the default, and warns about it on the settings screen.
- **Failures are indistinguishable.** "No such order" and "order exists but the phone doesn't match" return the same message, so the form cannot be used to probe which order numbers are real.
- **Rate limited per IP** (20 attempts / 15 minutes by default), counted *before* validation so malformed probing gets no free attempts.
- **Contact details are masked** by default (`0171*****78`), and only ticked details are ever serialised — unticked fields never leave the server.
- **Phone-only lookups are an inherent trade-off**: anyone who knows a customer's phone number can see that customer's recent orders, the same trade-off courier tracking tools make. Requiring the order number as well closes it.

## Development notes

No build step, no Composer, no npm — plain PHP, vanilla JS, one stylesheet.

```
rh-order-track.php     Bootstrap: constants, HPOS declaration, singleton
includes/              One class per concern (RHOT_ prefix)
widgets/               Elementor Widget_Base subclass
templates/             Theme-overridable output
assets/                css/ and js/
languages/             bn_BD .po and .mo
```

Two details worth knowing before changing the lookup code:

- `meta_query` and `field_query` are **never** used. The legacy (non-HPOS) order data store does not support them and *silently drops the clause* instead of erroring, which returns the newest orders on the store regardless of the filter. Only query arguments that behave identically in both storage engines are used.
- An empty phone string must never reach a query. The HPOS query builder treats an empty `meta_value` as "argument not set" and drops the filter entirely — the same failure mode. `RHOT_Lookup::find_by_phone()` hard-guards this.

## Changelog

### 1.0.0
- Initial release.

## Credits

**Rubel Hossain** — [rubelhossain.online](https://rubelhossain.online)

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
