# Quick.Link Analytics YOURLS Plugin

Adds Quick.Link-specific analytics endpoints to a YOURLS install.

Repository: https://github.com/one-thirteen/quicklink/tree/main/plugins/quick-link-analytics

## Install

1. Copy `plugins/quick-link-analytics` to `YOURLS_ROOT/user/plugins/quick-link-analytics`.
2. In YOURLS Admin, open **Manage Plugins**.
3. Activate **Quick.Link Analytics**.

The plugin uses the normal YOURLS API authentication, so Quick.Link can call these endpoints with the same `signature` token already saved in the app.

## Endpoints

All endpoints are called through `yourls-api.php` with `format=json`.

### `quicklink_plugin_info`

Capability check for Quick.Link clients.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_plugin_info&format=json
```

Response:

```json
{
  "statusCode": 200,
  "message": "success",
  "plugin": {
    "name": "Quick.Link Analytics",
    "version": "0.1.0",
    "features": ["shorturl_analytics", "overview", "recent_clicks"],
    "log_table_available": true
  }
}
```

### `shorturl_analytics`

Per-link click trend for a date range. This matches the endpoint already used by the macOS and iOS apps.

```text
GET /yourls-api.php?signature=TOKEN&action=shorturl_analytics&shorturl=abc123&date=2026-04-17&date_end=2026-05-16&format=json
```

Response:

```json
{
  "statusCode": 200,
  "message": "success",
  "stats": {
    "keyword": "abc123",
    "total_clicks": 42,
    "range_clicks": 12,
    "daily_clicks": {
      "2026-05-14": 3,
      "2026-05-15": 4,
      "2026-05-16": 5
    }
  }
}
```

### `quicklink_analytics_overview`

Global dashboard data for a date range.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_analytics_overview&date=2026-04-17&date_end=2026-05-16&limit=10&format=json
```

Includes totals, range clicks, daily clicks, active/zero-click link counts, top links, top referrers, top countries, and top browsers.

### `quicklink_recent_clicks`

Recent click activity feed.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_recent_clicks&limit=25&format=json
```

Includes clicked date, keyword, short URL, destination URL, title, referrer, country code, and browser.

## Notes

- This first version does not create custom tables.
- It reads from YOURLS core URL and click log tables.
- Browser names are intentionally coarse. User-agent parsing can become a dedicated parser later if needed.
- If a YOURLS install has click logging disabled or an empty log table, the plugin will return valid empty trend/breakdown data.
