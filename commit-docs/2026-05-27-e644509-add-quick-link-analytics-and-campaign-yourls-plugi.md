# Add Quick.Link analytics and campaign YOURLS plugins

**Commit:** `e644509`
**Branch:** main
**Date:** 2026-05-27

## Summary

Adds two new YOURLS plugins for Quick.Link integrations: one for analytics and one for campaign link management. Each plugin includes a README documenting installation, supported API actions, request parameters, and example JSON responses.

The Quick.Link Analytics plugin registers API endpoints for plugin capability checks, per-shortlink analytics, an overview dashboard, and a recent-click feed. It reads from the existing YOURLS URL and click-log tables, computes date-ranged daily series and breakdowns, and exposes normalized browser/referrer/country labels without creating new tables.

The Quick.Link Campaigns plugin adds endpoints for capability checks, creating short links with UTM parameters appended to the destination URL, and campaign analytics over a date range. It validates destination URLs, preserves existing query parameters, derives campaign metadata from stored URLs, and aggregates campaign/source/medium breakdowns from YOURLS core tables.

## Files Changed

- **plugins/quick-link-analytics/README.md** — Added a README describing installation, API actions, and example responses for the analytics plugin.
- **plugins/quick-link-analytics/plugin.php** — Implemented new analytics API actions and supporting helpers for YOURLS click and URL table data.
- **plugins/quick-link-campaigns/README.md** — Added a README describing installation, API actions, and example responses for the campaigns plugin.
- **plugins/quick-link-campaigns/plugin.php** — Implemented campaign link creation and campaign analytics API actions plus supporting helpers.

## Checklist

### Needs Review
- [ ] Review the YOURLS database/query assumptions in both plugins, especially response shapes and table availability checks.
- [ ] Confirm the overview and per-endpoint click totals are derived consistently.
- [ ] Validate that campaign link creation preserves existing query parameters and handles unexpected YOURLS add-link responses safely.

### In Progress
- [ ] No automated tests are included with these new plugins.

### Considerations
- [ ] Consider adding targeted integration tests for the new API actions.

---

Created with better-gh