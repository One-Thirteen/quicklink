# Quick.Link YOURLS Plugins

YOURLS plugins for [Quick.Link](https://github.com/one-thirteen/quick-link-code), an iOS app that connects to a user's self-hosted YOURLS server.

These plugins add optional Quick.Link-specific API endpoints for richer analytics and campaign link workflows. They use the normal YOURLS API authentication flow, so the app can call them with the same `signature` token a user already stores for their YOURLS server.

## Plugins

| Plugin | Path | Purpose |
| --- | --- | --- |
| Quick.Link Analytics | `plugins/quick-link-analytics` | Adds link analytics, dashboard overview data, recent click activity, and breakdowns by referrer, country, and browser. |
| Quick.Link Campaigns | `plugins/quick-link-campaigns` | Adds campaign-aware short link creation, UTM parameter handling, and campaign/source/medium analytics. |

Both plugins read from YOURLS core URL and click-log tables. They do not create custom database tables.

## Requirements

- A working [YOURLS](https://yourls.org/) installation.
- YOURLS API access enabled.
- A YOURLS signature token for the user connecting Quick.Link.
- YOURLS click logging enabled for analytics endpoints that depend on the click log table.

## Installation

Install either plugin independently, or install both if you want the full Quick.Link integration.

1. Copy the plugin directory into your YOURLS plugins folder:

   ```bash
   cp -R plugins/quick-link-analytics /path/to/yourls/user/plugins/
   cp -R plugins/quick-link-campaigns /path/to/yourls/user/plugins/
   ```

2. Open the YOURLS admin dashboard.
3. Go to **Manage Plugins**.
4. Activate **Quick.Link Analytics** and/or **Quick.Link Campaigns**.

After activation, the endpoints are available through `yourls-api.php` with `format=json`.

## Quick Checks

Use the plugin info endpoints to confirm that Quick.Link can see the installed capabilities:

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_plugin_info&format=json
GET /yourls-api.php?signature=TOKEN&action=quicklink_campaign_info&format=json
```

Successful responses include `statusCode: 200`, `message: "success"`, the plugin name, version, and supported features.

## Analytics Endpoints

Provided by `plugins/quick-link-analytics`.

### `shorturl_analytics`

Returns per-link analytics for a date range.

```text
GET /yourls-api.php?signature=TOKEN&action=shorturl_analytics&shorturl=abc123&date=2026-04-17&date_end=2026-05-16&format=json
```

Includes:

- Keyword.
- Total clicks.
- Clicks inside the requested range.
- Daily click counts.

### `quicklink_analytics_overview`

Returns global analytics for a date range.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_analytics_overview&date=2026-04-17&date_end=2026-05-16&limit=10&format=json
```

Includes:

- Total links and total clicks.
- Range click totals.
- Active and zero-click link counts.
- Daily click counts.
- Top links.
- Top referrers, countries, and browsers.

### `quicklink_recent_clicks`

Returns recent click activity.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_recent_clicks&limit=25&format=json
```

Includes clicked date, keyword, short URL, destination URL, title, referrer, country code, and browser.

## Campaign Endpoints

Provided by `plugins/quick-link-campaigns`.

### `quicklink_create_campaign_link`

Creates a YOURLS short link after appending UTM campaign parameters to the destination URL.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_create_campaign_link&url=https%3A%2F%2Fexample.com&campaign=launch&source=qr&medium=print&keyword=launch-qr&format=json
```

Supported parameters:

- `url`: required destination URL.
- `keyword`: optional custom short link keyword.
- `title`: optional YOURLS title.
- `source` or `utm_source`.
- `medium` or `utm_medium`.
- `campaign` or `utm_campaign`.
- `term` or `utm_term`.
- `content` or `utm_content`.

The endpoint preserves existing query parameters and stores campaign data as standard UTM parameters on the destination URL.

### `quicklink_campaign_overview`

Returns campaign link analytics for a date range.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_campaign_overview&date=2026-04-17&date_end=2026-05-16&limit=10&format=json
```

Includes:

- Total campaign links.
- Range click totals.
- Top campaigns.
- Top sources.
- Top mediums.
- Recent campaign links.

Links are treated as campaign links when their destination URL contains `utm_source`, `utm_medium`, or `utm_campaign`.

## Repository Structure

```text
quicklink/
|-- plugins/
|   |-- quick-link-analytics/
|   |   |-- README.md
|   |   `-- plugin.php
|   `-- quick-link-campaigns/
|       |-- README.md
|       `-- plugin.php
|-- commit-docs/
`-- README.md
```

## Development Notes

- These plugins are plain YOURLS plugins written in PHP.
- Endpoint responses are JSON-compatible arrays returned through the YOURLS API system.
- Date range parameters use `YYYY-MM-DD`; invalid or missing dates fall back to the plugin defaults.
- `limit` parameters are clamped by each endpoint.
- If the YOURLS click log table is unavailable, analytics endpoints return a `503` response payload instead of creating fallback tables.

See each plugin's README for more detailed endpoint examples:

- [Quick.Link Analytics](plugins/quick-link-analytics/README.md)
- [Quick.Link Campaigns](plugins/quick-link-campaigns/README.md)
