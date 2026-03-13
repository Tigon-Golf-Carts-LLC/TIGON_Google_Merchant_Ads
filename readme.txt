=== TIGON Merchant Feeds ===
Contributors: noahjaslow
Tags: woocommerce, product feed, google merchant, ebay, amazon, walmart, tiktok, facebook, golf carts
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.0.0
License: Proprietary. All rights reserved. © TIGON Golf Carts.

Multi-marketplace product feed generator for WooCommerce — Google Merchant, eBay, Amazon, Walmart, TikTok, Facebook, and unlimited custom feeds.

== Description ==

TIGON Merchant Feeds generates product feed URLs from your WooCommerce store for submission to multiple online marketplaces. Built specifically for Golf Cart dealers, all products are automatically categorized under Google's "Vehicles & Parts > Vehicles > Golf Carts" (category 3101) for 100% acceptance into Google Merchant Center.

**Supported Marketplaces:**

* Google Merchant Center (XML)
* Facebook / Meta Commerce Manager (CSV)
* Amazon Seller Central (TSV)
* eBay File Exchange / Seller Hub (CSV)
* Walmart Marketplace (XML)
* TikTok Shop (CSV)
* Unlimited custom feeds (CSV, XML, or TSV)

**Key Features:**

* Separate, secure feed URL for each marketplace
* 100% field mapping with custom meta key overrides
* Variable product support with automatic variation expansion
* Item group IDs, GTIN/UPC, MPN, brand, and full attribute mapping
* Secret-key protected feed URLs
* TIGON branded admin interface

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress Plugins menu.
3. Go to TIGON Feeds in the admin sidebar.
4. Enable feeds and copy the secure URLs.
5. Submit each URL to the corresponding marketplace.

== Frequently Asked Questions ==

= What Google Product Category is used? =
All products default to category 3101 — Vehicles & Parts > Vehicles > Golf Carts. This can be changed in Feed Settings.

= Can I create additional feeds? =
Yes. Navigate to Custom Feeds to create unlimited additional marketplace feeds in CSV, XML, or TSV format.

= How do I override product field values? =
Go to Field Mapping and enter a custom meta key for any field. The feed will use that meta value instead of the WooCommerce default.

= Are feed URLs secure? =
Yes. Each URL includes a secret key parameter. Only requests with the correct key will receive feed data.

== Changelog ==

= 1.0.0 =
* Initial release.
* Google Merchant, Facebook, Amazon, eBay, Walmart, and TikTok feed generators.
* Custom feed builder with CSV, XML, and TSV support.
* Full field mapping system with meta key overrides.
* TIGON branded admin dashboard.

== License ==

Proprietary. All rights reserved. © TIGON Golf Carts.

== Developer ==

Noah Jaslow © Jaslow Digital — jaslowdigital.com
PH: 215-789-1955
