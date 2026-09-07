=== Sales by State Report for Shopify ===
Contributors: BusinessBloomer
Donate link: https://salesbystate.com/
Tags: sales-report, sales-by-state, shopify, analytics, sales-tax
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See a yearly breakdown of Shopify sales by state / county / province for a given country, filterable by order status.

== Description ==

Sales by State Report for Shopify adds a report showing net and gross sales grouped by state, county, or province, for a chosen year and a chosen set of order statuses.

It appears under **Shopify → Sales by State**.

Use it when you need to know how much each state bought in a given year, counting only the order statuses that matter for sales tax or territory planning.

This plugin requires the official [Shopify](https://wordpress.org/plugins/shopify/) WordPress plugin, plus a Shopify Admin API token (or Client ID + Client secret) so orders can be read from Shopify Cloud. The WordPress plugin's Storefront token cannot read orders.

Documentation: [salesbystate.com](https://salesbystate.com/)

= How to use =

1. Install and activate the official Shopify WordPress plugin, connect a store, then install and activate this plugin.
2. Provide a Shopify Admin API token, or a Client ID and Client secret from a Dev Dashboard app installed on the store.
3. Go to **Shopify → Sales by State**.
4. Choose a **country**, a **year**, and the **order statuses** that should count.
5. The table lists Net Sales and Gross Sales for every state in that country.

If the store already has orders, the plugin imports them from the Shopify Admin API into its report table in the background. A progress bar appears until that finishes. You can leave the page; the import continues on its own.

= What the report shows =

* Net Sales and Gross Sales for every state in the selected country
* A summary of both figures across all states
* Sortable columns and paginated results
* States with no sales, shown as zero rather than hidden

= Filters =

* **Country** — United States, Canada, and United Kingdom. Defaults to the United States.
* **Year** — a rolling list that starts ten years back and gains a year each January without dropping one. Defaults to the current year.
* **Order status** — a checkbox list of Shopify financial statuses. Defaults to Paid.

= How the figures are calculated =

Gross Sales is the order total including tax. Net Sales is that total minus tax and shipping. Both use the values Shopify stores on the order.

Refunds are not modelled as separate records. An order that has been fully refunded is controlled by the status filter. A partial refund is not deducted from its order's total.

= Performance =

Sales for a whole year are answered by one indexed query that returns one row per state. The response size does not grow with the number of orders.

Shopify keeps orders on its API, not in a local WordPress table. Existing orders are imported from the Shopify Admin API into the report table once. After that import, opening or changing the report filters does not call the API. New orders are picked up by a background poll of the most recent orders.

= Data and privacy =

The plugin creates one custom database table holding, per order: the order ID, order status, creation and payment dates, billing and shipping country and state codes, currency, and the order, tax, shipping and net totals. It stores no names, addresses, email addresses or any other personal data.

During the one-off import, and when recent orders are polled, the plugin reads order data from the Shopify Admin API. It does not send data to any other service, includes no third-party analytics, and collects no telemetry.

Deleting the plugin removes the table and its options.

== Installation ==

1. Upload the plugin to `/wp-content/plugins/sales-by-state-report-for-shopify`, or install it through the Plugins screen.
2. Activate the plugin. The official Shopify WordPress plugin must already be installed and active, with a store connected.
3. Provide a Shopify Admin API token (or Client ID + Client secret from a Dev Dashboard app installed on the store).
4. Go to **Shopify → Sales by State**.

On a store that already has orders, those orders are read from the Shopify Admin API into the report table once. This starts on its own. If it has not finished when you open the report, a progress bar shows how far along it is.

== Frequently Asked Questions ==

= The report shows zeros but I have orders. =

Your existing orders are still being read into the report table. Open the report and the progress bar will show how far along it is. It continues on its own; you can leave the page.

If only Paid is selected, tick any other statuses that should count.

To confirm how much of the import has finished, open **Tools → Site Health → Info → Sales by State Report for Shopify**.

= Where does the report appear? =

Under **Shopify → Sales by State**. Users who can manage options can open it.

= Which address does it group by? =

The shipping address, falling back to the billing address for orders that have no shipping address (for example digital / country-only orders).

= Are refunds deducted? =

The status filter decides whether an order counts. Partial refunds are not deducted from the order's total.

= Which date does the year filter use? =

The paid date when the order is in a paid status, otherwise the created date.

= Can I change the default order status? =

Yes, with the `sbss_default_statuses` filter.

= Where can I get support? =

Use the [WordPress.org support forum](https://wordpress.org/support/plugin/sales-by-state-report-for-shopify/) for this plugin.

== Changelog ==

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
