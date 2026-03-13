# TIGON Merchant Feeds

**Multi-Marketplace Product Feed Generator for WooCommerce**

Generate and serve product feed URLs for Google Merchant Center, eBay, Amazon, Walmart, TikTok Shop, Facebook/Meta Commerce — plus unlimited custom marketplace feeds. Built specifically for Golf Cart dealers powered by WooCommerce.

---

## Features

- **Google Merchant Feed** — XML feed with full Google Product Data Specification compliance. All products auto-categorized as `Vehicles & Parts > Vehicles > Golf Carts` (category 3101) for 100% acceptance.
- **Facebook / Meta Feed** — CSV feed for Meta Commerce Manager product catalog.
- **Amazon Feed** — Tab-delimited feed for Amazon Seller Central bulk upload.
- **eBay Feed** — CSV feed for eBay File Exchange / Seller Hub.
- **Walmart Feed** — XML feed for Walmart Marketplace bulk upload.
- **TikTok Shop Feed** — CSV feed for TikTok Shop product upload.
- **Unlimited Custom Feeds** — Create as many additional feeds as you need (CSV, XML, or TSV) with configurable column mapping.
- **Secure Feed URLs** — Each feed URL is protected with a secret key.
- **Full Field Mapping** — Override any WooCommerce product field with custom meta keys for 100% mapping accuracy.
- **Variable Product Support** — Automatically expands variable products into individual variations with item group IDs.
- **TIGON Branded Admin** — Clean dashboard with TIGON brand colors (Red, Blue, Silver).

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+

## Installation

1. Upload the `tigon-merchant-feeds` folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress **Plugins** menu.
3. Navigate to **TIGON Feeds** in the admin sidebar.
4. Enable the feeds you need and copy the feed URLs.
5. Submit each URL to the corresponding marketplace platform.

## Feed URLs

After activation, your feeds are available at:

```
https://yoursite.com/tigon-feed/google/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/facebook/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/amazon/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/ebay/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/walmart/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/tiktok/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/{custom-slug}/?key=YOUR_SECRET_KEY
```

## License

Proprietary. All rights reserved. © TIGON Golf Carts.

## Developer

**Noah Jaslow** © Jaslow Digital — [jaslowdigital.com](https://jaslowdigital.com)
PH: 215-789-1955
