# TIGON Merchant Feeds

**Multi-Marketplace Product Feed Generator & Google Merchant API for WooCommerce**

Generate and serve product feed URLs for Google Merchant Center, eBay, Amazon, Walmart, TikTok Shop, Facebook/Meta Commerce — plus unlimited custom marketplace feeds. Push products directly to Google Merchant Center via the Merchant API. Built specifically for Golf Cart dealers powered by WooCommerce.

---

## Features

- **Google Merchant Feed** — XML feed with full Google Product Data Specification compliance including `<g:shipping>` block. All products auto-categorized as `Vehicles & Parts > Vehicles > Golf Carts` (category 3101).
- **Google Merchant API** — Direct API integration to push products to Google Merchant Center. OAuth2 service account auth, data source creation, bulk sync, and scheduled auto-sync via WP-Cron.
- **Google Merchant Reviews Feed** — XML feed compliant with Google Merchant Reviews schema v5.0, pulls WooCommerce product reviews with 1-5 star ratings.
- **Facebook / Meta Feed** — CSV feed for Meta Commerce Manager product catalog.
- **Amazon Feed** — Tab-delimited feed for Amazon Seller Central bulk upload.
- **eBay Feed** — CSV feed for eBay File Exchange / Seller Hub.
- **Walmart Feed** — XML feed for Walmart Marketplace bulk upload.
- **TikTok Shop Feed** — CSV feed for TikTok Shop product upload.
- **Unlimited Custom Feeds** — Create as many additional feeds as you need (CSV, XML, or TSV) with configurable column mapping.
- **Location-Based Sync** — Products use the existing `location` taxonomy (States > Cities). Sync to Google filtered by any location.
- **Secure Feed URLs** — Each feed URL is protected with a secret key. Dual-routing (rewrite rules + `parse_request` fallback) ensures feeds always resolve.
- **Full Field Mapping** — Override any WooCommerce product field with custom meta keys for 100% mapping accuracy.
- **Variable Product Support** — Automatically expands variable products into individual variations with item group IDs.
- **TIGON Branded Admin** — Full-width dark maroon header with TIGON tiger/database/network SVG logo on every admin page. Custom sidebar menu icon.

## Requirements

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+ (with OpenSSL extension for Google API auth)

## Installation

1. Upload the `tigon-merchant-feeds` folder to `/wp-content/plugins/`.
2. Activate the plugin through the WordPress **Plugins** menu.
3. Go to **Settings > Permalinks** and click **Save Changes** (flushes rewrite rules).
4. Navigate to **TIGON Feeds** in the admin sidebar.
5. Enable the feeds you need and copy the feed URLs.
6. Submit each URL to the corresponding marketplace platform.

## Admin Pages

| Page | Path | Purpose |
|------|------|---------|
| Dashboard | `admin.php?page=tigon-merchant-feeds` | Feed URLs, product/feed counts, quick links |
| Feed Settings | `admin.php?page=tigon-feed-settings` | Enable/disable feeds, Google category, shipping defaults, security key |
| Field Mapping | `admin.php?page=tigon-field-mapping` | Override WooCommerce fields with custom meta keys |
| Custom Feeds | `admin.php?page=tigon-custom-feeds` | Create additional marketplace feeds (CSV/XML/TSV) |
| Google API | `admin.php?page=tigon-google-api` | Merchant API credentials, data source, sync schedule, manual sync |
| Locations | `admin.php?page=tigon-stores` | View products by location taxonomy (States > Cities), link to taxonomy editor |

## Feed URLs

After activation, your feeds are available at:

```
https://yoursite.com/tigon-feed/google/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/google-reviews/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/facebook/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/amazon/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/ebay/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/walmart/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/tiktok/?key=YOUR_SECRET_KEY
https://yoursite.com/tigon-feed/{custom-slug}/?key=YOUR_SECRET_KEY
```

### Feed URL Troubleshooting

If Google Merchant Center reports "File not found" when fetching your feed:

1. **Flush rewrite rules** — Go to **Settings > Permalinks** in WordPress admin and click **Save Changes**.
2. **Update the plugin** — The plugin auto-flushes rewrite rules once per version update. Deactivate and reactivate if needed.
3. **Check your CDN** — Ensure `/tigon-feed/(.*)` is excluded from caching (see CDN section below).
4. **Test the URL directly** — Open the feed URL in your browser. You should see XML output, not a WordPress 404 page.
5. **Fallback routing** — The plugin uses a `parse_request` fallback that catches `/tigon-feed/` URLs even when rewrite rules are missing. If the URL still 404s, check that the plugin is activated and WooCommerce is running.

## Google Merchant API Setup

1. Go to **TIGON Feeds > Google API** in the WordPress admin.
2. Enter your **Merchant Center Account ID**.
3. Create a service account in [Google Cloud Console](https://console.cloud.google.com/iam-admin/serviceaccounts):
   - Enable the **Merchant API** in your Google Cloud project.
   - Create a service account and download the JSON key file.
   - Grant the service account **Admin** or **Standard** access in Merchant Center under **Settings > Account access**.
4. Paste the entire JSON key file contents into the credentials field.
5. Click **Create API Data Source** to create a data source named for this plugin.
6. Set a sync schedule (every 6 hours, 12 hours, daily, or twice daily).
7. Click **Sync Now** to push all products immediately.

### API Data Flow

```
WooCommerce Products
        |
   TMF_Field_Mapper::map()
        |
   TMF_Google_Merchant_API::insert_product()
        |
   POST merchantapi.googleapis.com/products/v1/accounts/{id}/productInputs:insert
        |
   Google Merchant Center
```

Each product is mapped to Google's format with:
- Prices in `amountMicros` (e.g., $15.99 = 15990000)
- Availability as API enums (`IN_STOCK`, `OUT_OF_STOCK`, `BACKORDER`)
- GTIN, MPN, brand, condition, shipping weight/dimensions
- Product highlights, structured details, custom labels
- Location taxonomy terms as custom attributes

## Location Taxonomy

Products are organized by the existing **Location** taxonomy (hierarchical: States > Cities). This is used for:

- **Google API sync filtering** — Push products from a specific state or city only
- **Locations admin page** — View product counts per location
- **Product custom attributes** — Location terms are sent to Google as custom attributes

Locations are managed via the standard WordPress taxonomy editor at **Products > Location** (or the link on the Locations page).

Example hierarchy:
```
Florida
  ├── Lecanto
Pennsylvania
  ├── Hatfield
  ├── Long Pond
  ├── Pocono
  ├── Scranton
  └── Philadelphia
New Jersey
  ├── Bayville
  ├── Ocean View
  ├── Pleasantville
  ├── Rio Grande
  └── Waretown
Virginia
  ├── Gloucester Point
  ├── Portsmouth
  └── Virginia Beach
```

---

## CDN Configuration (WP Rocket / LiteSpeed / Cloudflare / etc.)

Product feeds are dynamically generated from live WooCommerce data and must **never** be served from cache. Serving a stale cached feed to Google, Facebook, or any marketplace will cause inventory mismatches, price errors, and potential account suspensions.

### Never Cache URL(s)

Add these URL patterns to your CDN or caching plugin's **"Never Cache URLs"** list (one per line):

```
/tigon-feed/(.*)
/wp-admin/admin.php?page=tigon(.*)
/wp-json/tmf/(.*)
```

**What each rule does:**

| Pattern | Purpose |
|---------|---------|
| `/tigon-feed/(.*)` | All product feed endpoints (Google, Facebook, Amazon, eBay, Walmart, TikTok, custom feeds, reviews). These must return real-time product data. |
| `/wp-admin/admin.php?page=tigon(.*)` | Plugin admin pages (dashboard, settings, Google API, locations). Prevents stale form data and CSRF token issues. |
| `/wp-json/tmf/(.*)` | Any REST API endpoints the plugin registers. |

### Never Cache Cookies

No special cookies are required. The plugin uses standard WordPress admin cookies which most caching plugins already exclude.

### Never Cache User Agent(s)

Add these user agents to prevent caching for marketplace crawlers that fetch your feeds:

```
Googlebot
Feedfetcher-Google
Google-Merchant-Center
Facebot
FacebookExternalHit
AmazonAdBot
```

### Always Purge URL(s)

When any WooCommerce product is updated, these URLs should be purged automatically so the next marketplace fetch gets fresh data. Add:

```
/tigon-feed/(.*)
```

Most caching plugins already purge on post/product save, but adding this ensures feed URLs are included in the purge. If your cache plugin supports **purge by tag/group**, tag all feed URLs for purge when `product` post type is modified.

### Cache Query String(s)

By default, most CDNs and caching plugins strip or ignore query strings. The `key` parameter in feed URLs is required for authentication. You have two options:

**Option A (Recommended): Exclude feed URLs from cache entirely**
If you added `/tigon-feed/(.*)` to "Never Cache URLs" above, you don't need to do anything here — the query string is irrelevant because the page is never cached.

**Option B: If your CDN caches all URLs by default**
Add the `key` query string parameter to **"Cache Query Strings"** so each unique key generates a separate cache entry:

```
key
```

However, Option A is strongly preferred since feeds should always return live data.

### CDN-Specific Configuration

#### WP Rocket

1. Go to **Settings > WP Rocket > Advanced Rules**.
2. Under **Never Cache URL(s)**, add: `/tigon-feed/(.*)`
3. Under **Always Purge URL(s)**, add: `/tigon-feed/(.*)`
4. Save and clear cache.

#### LiteSpeed Cache

1. Go to **LiteSpeed Cache > Cache > Excludes**.
2. Under **Do Not Cache URIs**, add: `/tigon-feed/`
3. Under **Do Not Cache Query Strings**, add: `key`
4. Save.

#### Cloudflare

1. Go to **Rules > Page Rules** (or Cache Rules in the new dashboard).
2. Create a rule:
   - **URL match:** `*yoursite.com/tigon-feed/*`
   - **Setting:** Cache Level = **Bypass**
3. If using Cloudflare APO, add `/tigon-feed/*` to the exclusion list.

#### Nginx FastCGI Cache (server-level)

Add to your Nginx configuration inside the `server` block:

```nginx
# Skip cache for TIGON Merchant Feed endpoints
if ($request_uri ~* "/tigon-feed/") {
    set $skip_cache 1;
}
```

#### Varnish

Add to your VCL configuration:

```vcl
if (req.url ~ "^/tigon-feed/") {
    return (pass);
}
```

### Verifying Your CDN Configuration

After configuring, test that feeds are not cached:

1. Visit any feed URL in your browser (e.g., `https://yoursite.com/tigon-feed/google/?key=YOUR_KEY`).
2. Check the response headers:
   - `Cache-Control` should show `no-cache, no-store, must-revalidate`
   - `X-Cache` (if present) should show `MISS` or `BYPASS`, not `HIT`
   - `X-Robots-Tag` should show `noindex, nofollow`
3. Update a product's price in WooCommerce, then reload the feed — the new price should appear immediately.
4. If the price is stale, your cache is still serving the feed. Double-check the exclusion rules above.

---

## File Structure

```
tigon-merchant-feeds/
├── tigon-merchant-feeds.php          # Main plugin file, loader, activation/deactivation
├── uninstall.php                     # Cleanup on plugin deletion
├── README.md
├── admin/
│   ├── class-admin.php               # All admin pages (dashboard, settings, mapping, feeds, API, locations)
│   ├── css/admin.css                 # Admin styles (TIGON branding, cards, tables, buttons)
│   ├── js/admin.js                   # Admin JS (copy-to-clipboard, etc.)
│   └── img/tigon-logo.svg            # TIGON tiger+database+network SVG logo
└── includes/
    ├── class-field-mapper.php         # Maps WooCommerce product data to feed fields
    ├── class-feed-generator.php       # Abstract base class for feed generators
    ├── class-feed-endpoint.php        # URL routing (rewrite rules + parse_request fallback)
    ├── class-google-feed.php          # Google Merchant XML feed
    ├── class-google-reviews-feed.php  # Google Merchant Reviews XML feed
    ├── class-google-merchant-api.php  # Google Merchant API (OAuth, sync, data sources, locations)
    ├── class-facebook-feed.php        # Facebook/Meta CSV feed
    ├── class-amazon-feed.php          # Amazon TSV feed
    ├── class-ebay-feed.php            # eBay CSV feed
    ├── class-walmart-feed.php         # Walmart XML feed
    ├── class-tiktok-feed.php          # TikTok Shop CSV feed
    ├── class-custom-feed.php          # User-defined custom feeds
    └── class-tigon-merchant-feeds.php # Feed registry / helper
```

---

## License

Proprietary. All rights reserved. &copy; TIGON Golf Carts.

## Developer

**Noah Jaslow** &copy; Jaslow Digital — [jaslowdigital.com](https://jaslowdigital.com)
PH: 215-789-1955
