=== Mr. J Dev Dynamic Checkout Fields ===
Contributors: ajay2188, iamdeveloperajay
Tags: woocommerce, checkout, custom fields, conditional logic, validation
Requires at least: 5.8
Tested up to: 6.9
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Add custom fields to the WooCommerce checkout based on cart contents, value, or country. Saves data to order meta and displays it in the admin.

== Description ==

Mr. J Dev Dynamic Checkout Fields lets store owners add fully configurable custom fields to the WooCommerce checkout page. Fields can be shown conditionally based on cart total, shipping country, or product presence — all managed via a clean split-panel admin UI without writing any code.

**Field Types:**

* Text — single-line text input
* Textarea — multi-line text area (4 rows, resizable)
* Dropdown — select from a list of predefined options (you define one option per line)
* Checkbox — a simple yes/no toggle

**Conditional Display (show field only when):**

* Always — field is always visible at checkout
* Cart total is greater than ₹X — e.g. show a "Gift wrapping note" field only for orders above ₹500
* Shipping country equals — show field only for specific countries (2-letter ISO code, e.g. IN, US, GB)
* Product ID is in cart — show a field only when a specific product is being purchased

Note: Conditions apply to the classic shortcode checkout only. In the block checkout, all registered fields are always shown.

**Validation:**

* Mark any field as required — WooCommerce will block checkout if left empty
* Optional regex pattern — e.g. `^[0-9]{10}$` to enforce a 10-digit phone format
* Regex is applied to Text fields only; Checkbox and Dropdown use WooCommerce's built-in validation

**Checkout Compatibility:**

* Classic Checkout (shortcode `[woocommerce_checkout]`) — full support including conditions
* Block Checkout (WooCommerce 8.9+) — fields registered via the Additional Checkout Fields API and appear in the "Order" section of the block checkout automatically. Textarea type falls back to a single-line text field in block mode (the Block API has no native textarea type)

**Data Handling:**

* Field values are saved as order meta on checkout submit
* Displayed in the admin order detail page (below billing address)
* Included in WooCommerce order emails — both HTML and plain-text formats

**Admin UI (split-panel layout):**

* Left panel — lists all saved fields as cards with type badge, condition summary, and required indicator
* Right panel — inline create / edit form; clicking a card loads it for editing
* Fields are created, updated, and deleted without leaving the page
* The form auto-shows/hides the Dropdown Options textarea and Condition Value input based on your selections

== Installation ==

1. Upload the `mr-j-dev-dynamic-checkout-fields` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Go to **WooCommerce → Checkout Fields** to create and manage your fields.

== Usage ==

1. Go to **WooCommerce → Checkout Fields**.
2. Click **Add New Field**.
3. Fill in:
   * **Field ID** — a unique slug (lowercase, underscores only), e.g. `pan_number`
   * **Label** — what customers see at checkout, e.g. "PAN Number"
   * **Field Type** — Text, Textarea, Dropdown, or Checkbox
   * **Dropdown Options** — one option per line (only shown when type is Dropdown)
   * **Required** — tick to block checkout if the field is left empty
   * **Show When** — the condition that must be true for the field to appear
   * **Condition Value** — the value for your condition (amount / country code / product ID)
   * **Validation Regex** — optional regex for Text and Textarea fields
4. Click **Save Field**.

The field will appear on the checkout page whenever its condition is met. Submitted values are stored with the order and visible in the admin order screen and in all WooCommerce order emails.

== Condition Examples ==

**Gift message field for large orders:**
* Type: Text | Show When: Cart total > | Value: 1000

**PAN card field for Indian customers only:**
* Type: Text | Show When: Shipping country = | Value: IN
* Validation Regex: `^[A-Z]{5}[0-9]{4}[A-Z]{1}$`

**Delivery preference for a specific product:**
* Type: Dropdown | Show When: Product ID in cart | Value: 42
* Options: Morning&#10;Afternoon&#10;Evening

**Simple gift wrap option:**
* Type: Checkbox | Show When: Always | Required: No

== Block Checkout Notes ==

To use with the WooCommerce Block Checkout:

* WooCommerce 8.9 or newer is required
* Fields are registered in the "Order" section of the block checkout
* Conditional display is not supported in block mode — all your fields will always show
* Field values are saved and displayed in orders automatically by WooCommerce

== Frequently Asked Questions ==

= Can I have multiple fields? =
Yes — there is no limit. Each field needs a unique ID.

= Does it work with HPOS (High-Performance Order Storage)? =
Yes. Field values are saved via `update_post_meta` / the blocks API, both of which are compatible with HPOS.

= Can I edit a field after creating it? =
Yes. Click any field card in the left panel to load it into the edit form. Change the details and click **Update Field**.

= What happens if I delete a field? =
The field definition is removed and it will no longer appear at checkout. Existing order meta from past orders is not deleted.

== Changelog ==

= 1.0.0 =
* Initial release.
* Split-panel admin UI with inline create/edit.
* Text, Textarea, Dropdown, and Checkbox field types.
* Conditional display: always, cart total, shipping country, product in cart.
* Required field validation and regex pattern support (Text and Textarea).
* Classic checkout hooks (woocommerce_after_order_notes + fallback).
* Block Checkout support via Additional Checkout Fields API (WC 8.9+) — Textarea falls back to text in block mode.
* Order meta storage, admin order display, and email integration.
