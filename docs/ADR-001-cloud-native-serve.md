# ADR 001: Cloud-native llms.txt serve path

Status: Accepted  
Date: 2026-09-10  
Module: MidCore_LlmsTxt

## Context

Adobe Commerce Cloud (and similar PaaS) treats the built `pub/` tree as read-only. Several Magento llms.txt modules write `pub/llms.txt` (site-root static file). That fails on Cloud, fights Magento routing, and still needs a PHP fallback when the file is absent.

`llms.txt` must be available at `https://{store-host}/llms.txt` per [llmstxt.org](https://llmstxt.org/) v2, with one artifact set per store view.

## Decision

1. **Generate offline** (CLI `midcore:llms:generate` and cron group `midcore`) into `var/llms/{website_code}/{store_code}/` using temp file + rename.
2. **Never generate on a storefront request.** Missing artifacts return 404.
3. **Serve** `/llms.txt` and `/llms-full.txt` with a Magento frontend router + controllers. Store scope comes from the request host (Magento/Cloud `routes.yaml`).
4. **Do not** write canonical files to `pub/` or `pub/media`.
5. Send cache-friendly headers (`Cache-Control`, `ETag`, `X-Magento-Tags`) so Fastly can cache and purge.

## Consequences

- Works on Magento Open Source, Adobe Commerce, and Cloud without a writable `pub/` root.
- Operators must run generate (cron or CLI) before the URL is useful.
- `var/` disk and cron runtime become the scale limits for large catalogs (cursor-batched queries; streamed writes).
- Analytics for real bot hits belong at the CDN; origin counters undercount by design.
