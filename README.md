# MidCore_LlmsTxt

Magento 2 / Adobe Commerce module that generates and serves [`llms.txt`](https://llmstxt.org/) and `llms-full.txt` so AI agents can read a store catalog as markdown instead of theme HTML.

- Vendor / module: `MidCore_LlmsTxt`
- Composer: `midcore/module-llms-txt` (GitHub org is `midcorelabs`; the Composer vendor prefix is intentional)
- License: MIT
- Magento: Open Source and Adobe Commerce 2.4.x (PHP 8.1+)
- Storefront: Hyvä / Luma / Blank **neutral** (no theme, layout, or JS)

Product source of truth: [docs/REQUIREMENTS.md](docs/REQUIREMENTS.md). Cloud serve decision: [docs/ADR-001-cloud-native-serve.md](docs/ADR-001-cloud-native-serve.md).

## What it does

| URL | Content |
|-----|---------|
| `/llms.txt` | Curated llmstxt.org v2 index (H1, optional summary, H2 link lists) |
| `/llms-full.txt` | Fuller catalog listing (names, SKUs, URLs, short descriptions) |

Files are generated **offline** into `var/llms/{website_code}/{store_code}/` and **only read** on the storefront. Magento resolves the store from the request host.

Checkout, cart, customer, wishlist, REST/GraphQL, and admin paths are never included.

## Install

From a Magento project root (with Magento Composer credentials configured):

```bash
composer require midcore/module-llms-txt
bin/magento module:enable MidCore_LlmsTxt
bin/magento setup:upgrade
bin/magento cache:flush
```

Enable per store view: **Stores → Configuration → MidCore → llms.txt**. Then generate:

```bash
bin/magento midcore:llms:generate
bin/magento midcore:llms:generate --store=default
```

Until the command has run, `/llms.txt` returns 404 (by design — no generation on HTTP).

## Admin configuration

Stores → Configuration → MidCore → llms.txt (store-view scoped unless noted):

- Enable / generate `llms-full.txt` / optional robots.txt comment / CDN TTL
- Store identity (name, blockquote summary, detail paragraphs)
- Categories, products (optional exclude out-of-stock), CMS pages, manual markdown
- Cross-link sibling store views on the same website (Optional section)
- Default-scope cron: enable, schedule, “only when dirty” (default **Yes**), batch size
- Last generation status + **partial** origin-miss counter (survives regenerate)

## CLI and cron

```bash
bin/magento midcore:llms:generate [--store=code]
bin/magento cron:run --group=midcore
```

- Cron group `midcore` is the **recommended** scheduler (separate process so a large catalog does not block `default` Magento cron). A Cloud crontab that calls `midcore:llms:generate` is an optional override only — do not run both.
- Observers set a dirty flag on product, category, CMS, store, and this config save (best effort). CLI always generates; cron **only when dirty** is the default.
- Writes are atomic (temp file + rename). Product/category/CMS queries use `entity_id > lastId` batches (no OFFSET walk).

Logs: `var/log/midcore_llms.log`.

## Adobe Commerce Cloud

See [docs/CLOUD.md](docs/CLOUD.md). Short version:

- Canonical files live under **`var/`** (writable). They are **not** written to read-only `pub/` root.
- Magento routes serve `/llms.txt`; `var/` is not a public web root.
- Fastly: cache the routes; purge uses Magento cache tags `midcore_llmstxt` / `midcore_llmstxt_{storeId}`.
- Run generate on deploy, then rely on Magento cron group `midcore`. A Cloud crontab that calls `midcore:llms:generate` is an optional override — do not run both.

## Analytics (v1)

There is **no sync database write on the serve path**.

- **Preferred:** Fastly / edge logs for real crawler hits on `/llms.txt` and `/llms-full.txt`.
- **Optional / partial:** origin 404 miss counter in admin. CDN cache hits never increment it. Stored under cache tag `midcore_llmstxt_analytics` so regenerate / Fastly purge of `/llms.txt` does not reset it.

## SEO notes

- Do **not** `Disallow` `/llms.txt` or `/llms-full.txt` in robots.txt. If you use a broad `Disallow: /` (or similar), add explicit `Allow: /llms.txt` and `Allow: /llms-full.txt`.
- Curate CMS pages for the index with the admin CMS page multiselect. Do not dump every thin landing page.
- Generated links should be absolute `https://` URLs from the store base URL. Confirm on a live store (admin base URL can be `http://` or a placeholder host; that is not cheap to enforce in code).

## Multi-store and B2B

- One artifact set per store view. Sibling language/store files are linked under `## Optional` when more than one store view exists on the website.
- Adobe Commerce B2B (shared catalog / company) is **feature-detected**. Magento Open Source installs without those modules. When B2B is present, a note is added that the file lists guest/default-catalog products.

## Tests

Pure PHP unit tests (no Magento bootstrap):

```bash
phpunit -c phpunit.xml.dist
```

## License

MIT. See [LICENSE](LICENSE).
