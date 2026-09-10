# Magento 2 / Adobe Commerce Cloud notes — MidCore_LlmsTxt

Cloud is the reason this module writes **only** to `var/llms/` and serves `/llms.txt` through Magento, instead of dropping files into `pub/`.

## Writable vs read-only

| Path | Cloud typical | This module |
|------|----------------|-------------|
| `var/` | Writable (shared mount) | Canonical artifacts: `var/llms/{website_code}/{store_code}/llms.txt` and `llms-full.txt` |
| `pub/` root | Often **read-only** after build | Never used for canonical files |
| `pub/media` | Writable and public | Not used (`/media/llms.txt` is the wrong URL) |

`var/` is not HTTP-reachable. The frontend router is what makes `https://{store-host}/llms.txt` work.

## Generate offline

**Recommended:** Magento cron group `midcore` (separate process so a large catalog does not block `default` cron). Magento’s usual `cron:run` already schedules this group. Set the expression under Stores → Configuration → MidCore → llms.txt → Cron schedule. Cron **only when dirty** is on by default.

```bash
php bin/magento midcore:llms:generate
php bin/magento midcore:llms:generate --store=default
php bin/magento cron:run --group=midcore
```

Do **not** generate during a storefront request.

**Optional override:** if Magento cron cannot run this job (for example a dedicated worker that must call the CLI), add **one** Cloud crontab entry and **disable** the module’s Magento cron (Stores → Configuration → MidCore → llms.txt → Enable cron = No). Do not run Magento group `midcore` and a Cloud crontab that calls `midcore:llms:generate` at the same time — that double-generates.

```text
# Optional Cloud crontab — only when Magento cron for this module is disabled
0 2 * * * php bin/magento midcore:llms:generate
```

Post-deploy: run generate once so the first crawler does not see a 404.

## Fastly / CDN

Responses send:

- `Content-Type: text/markdown; charset=UTF-8`
- `Cache-Control: public, max-age={ttl}, s-maxage={ttl}`
- `ETag` / `Last-Modified` (304 when possible)
- `X-Magento-Tags: midcore_llmstxt,midcore_llmstxt_{storeId}`

After regenerate, the module dispatches Magento `clean_cache_by_tags` so Fastly purge-by-tag can drop stale `/llms.txt` (when Magento Cloud Fastly is wired as usual).

Confirm `routes.yaml` sends `/llms.txt` to the Magento upstream (default Magento Cloud routing already does). Do not add a static file at `pub/llms.txt`.

## Analytics (v1)

Real crawler volume lives in **Fastly / edge logs**, not Magento. Filter URL path `/llms.txt` or `/llms-full.txt` and bot user-agents (GPTBot, ClaudeBot, PerplexityBot, Google-Extended, and similar).

The admin “origin misses (partial)” counter increments only when Magento serves a 404 because the file is missing. It is stored under cache tag `midcore_llmstxt_analytics`, not `midcore_llmstxt`, so regenerate (which cleans `midcore_llmstxt`) does not reset it. Long edge TTLs mean successful crawler hits often never reach origin. There is **no database write on the serve path**.
