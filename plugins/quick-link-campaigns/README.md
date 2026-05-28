# Quick.Link Campaigns YOURLS Plugin

Adds campaign-aware short link creation and campaign analytics endpoints to a YOURLS install.

Repository: https://github.com/one-thirteen/quicklink/tree/main/plugins/quick-link-campaigns

## Install

1. Copy `plugins/quick-link-campaigns` to `YOURLS_ROOT/user/plugins/quick-link-campaigns`.
2. In YOURLS Admin, open **Manage Plugins**.
3. Activate **Quick.Link Campaigns**.

The plugin uses the normal YOURLS API authentication, so Quick.Link can call these endpoints with the same `signature` token already saved in the app.

## Endpoints

All endpoints are called through `yourls-api.php` with `format=json`.

### `quicklink_campaign_info`

Capability check for Quick.Link clients.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_campaign_info&format=json
```

### `quicklink_create_campaign_link`

Creates a short link after appending campaign parameters to the destination URL.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_create_campaign_link&url=https%3A%2F%2Fexample.com&campaign=launch&source=qr&medium=print&keyword=launch-qr&format=json
```

Supported parameters:

- `url`: required destination URL.
- `keyword`: optional custom alias.
- `title`: optional YOURLS title.
- `source` or `utm_source`
- `medium` or `utm_medium`
- `campaign` or `utm_campaign`
- `term` or `utm_term`
- `content` or `utm_content`

Response:

```json
{
  "statusCode": 200,
  "message": "success",
  "shorturl": "https://sho.rt/launch-qr",
  "url": "https://example.com?utm_source=qr&utm_medium=print&utm_campaign=launch",
  "campaign": {
    "source": "qr",
    "medium": "print",
    "campaign": "launch",
    "term": "",
    "content": ""
  }
}
```

### `quicklink_campaign_overview`

Returns campaign links and click breakdowns for a date range.

```text
GET /yourls-api.php?signature=TOKEN&action=quicklink_campaign_overview&date=2026-04-17&date_end=2026-05-16&limit=10&format=json
```

Includes total campaign link count, range clicks, top campaigns, top sources, top mediums, and recent campaign links.

## Notes

- This plugin does not create custom tables.
- Campaign data is stored in the destination URL as normal UTM parameters.
- Analytics are derived from YOURLS core URL and click log tables.
- Links are considered campaign links when their destination URL contains `utm_source`, `utm_medium`, or `utm_campaign`.
