# Magento 2 module: llms.txt (planning / requirements)

For v2 scope see docs/REQUIREMENTS-v2.md (draft).

Status: v1 implemented in this repository (go decision taken).
Note: §6.4 serving locked (Rohan). §8 product decisions locked (Jigar Sir).
Owner: Karan · Team Manager (MidCore Magento Team)
Last updated: 2026-09-10

## 1. Problem

AI agents and AI search tools need clean, structured store content. Magento HTML storefronts are heavy (theme, JS, chrome). Agents waste tokens guessing from rendered pages.

`llms.txt` (llmstxt.org, v2) is a markdown file (usually at `/llms.txt`) that gives agents a curated overview plus links to LLM-friendly detail. It sits beside `robots.txt` and `sitemap.xml`, not instead of them.

Merchants want Magento to generate and serve this from catalog + CMS data, with admin control and without hurting storefront performance.

## 2. Goal (product)

Ship a Composer Magento 2 / Adobe Commerce module that:

1. Generates spec-aware `llms.txt` (and optional fuller exports) from store data
2. Serves it at a stable URL per store view
3. Runs generation off the request path (CLI + cron)
4. Gives merchants clear admin controls for what is included
5. Is Cloud-safe (read-only FS constraints considered)

Decision gate: finalize this doc → Jigar Sir go/no-go → only then implement.

## 3. Non-goals (v1)

- Replacing Google product feeds / ChatGPT Shopping feeds
- Guaranteeing ranking in any AI engine (adoption is uneven; Perplexity is the clearest public reader today)
- Full site HTML → markdown mirroring of every URL in v1 (optional later)
- Training MidCore’s own LLMs on merchant data

## 4. Spec summary (llmstxt.org v2)

Required shape for `llms.txt`:

- H1 site/project name (required)
- Optional blockquote summary
- Optional detail paragraphs (no extra headings in that block)
- Zero or more H2 sections with markdown link lists: `- [name](url): optional notes`
- Optional H2 `Optional` for secondary links

Also recommended by the proposal (phase 2 for us): markdown alternates for key pages (`.md`) and `Link:` / `rel` hints. v1 can stay file-generation focused.

## 5. Market scan (competitors exist)

Existing Magento offerings (as of R&D):

| Player | Notes |
|--------|--------|
| angeo/module-llms-txt (MIT) | Free; llms.txt, llms-full.txt, JSONL; CLI/cron; multi-store; Page Builder aware |
| rkd/module-llms-txt | Spec validation, inventory-aware, robots.txt inject, large-catalog pagination |
| Webkul LLMs TXT Generator | Commercial; entity selection; analytics dashboard for AI crawlers |
| Aheadworks LLMs.txt Generator | Commercial; chunking for large catalogs; policy/business notes for AI |

Implication: space is not empty. MidCore’s product must win on Adobe Commerce / Cloud fit, Hyvä-neutral storefront, clean architecture, consulting-grade docs, and a clear ICP angle (mid-market Magento merchants who already trust MidCore).

Differentiation ideas (to validate):

- First-class Adobe Commerce Cloud story (writable mounts, cron groups, Fastly cache headers for `/llms.txt`)
- Strict llmstxt.org v2 compliance + validation report in admin
- Opinionated “safe defaults” (exclude checkout, customer, API routes; OOS handling)
- MidCore consulting playbook: install, tune, measure AI bot hits

## 6. Proposed v1 scope

### 6.1 Outputs

- `llms.txt` per store view (required)
- `llms-full.txt` per store view (required for v1; may still allow disable in config for tiny stores)
- Optional JSONL export (phase 1.1 if cheap after core)

### 6.2 Content sources (configurable)

Include toggles for:

- Store identity (name, base URL, short merchant blurb from config)
- Categories (name, URL, optional short description)
- Products (name, URL, SKU, optional short description; stock filter)
- CMS pages (selected list or all enabled)
- Manual markdown sections (admin textarea for custom H2 blocks / policy notes)

Exclude by default:

- Checkout, cart, customer account, wishlist, API, admin paths
- Disabled / not visible products
- Optional: out-of-stock products

### 6.3 Generation

- CLI: `bin/magento midcore:llms:generate [--store=...]`
- Cron: scheduled regenerate + dirty-flag on catalog/CMS changes (best effort)
- Admin: “Generate now” (queue/async; never long sync on HTTP if catalog is large)
- Atomic write (temp file then rename) so crawlers never see half files

### 6.4 Serving

**Decision:** generate offline into `var/`; serve `/llms.txt` via Magento frontend route. Do not write canonical files into `pub/` root on Cloud.

Details:

- Generate via CLI/cron into `var/llms/{website_code}/{store_code}/` with atomic write (temp + rename)
- Never generate on storefront request
- Serve `/llms.txt` (optional `/llms-full.txt`, `/llms.jsonl`) through a Magento frontend controller; resolve store from host
- Optional Fastly cache + ETag on those routes
- Do not rely on `var/` being HTTP-reachable by itself
- `pub/media` is writable/public but becomes `/media/...` unless rewritten; not preferred as source of truth
- One artifact set per store view / unique base-URL host; cross-link sibling language files in the index
- Host → store stays Magento/Cloud routing (`routes.yaml` / run code)

**Differentiation:** Cloud-native I/O (var + root Magento route, Fastly-friendly). Several market modules lean Admin UX or “write to site root,” which fights Cloud’s read-only `pub/`.


### 6.5 Multi-store

- One file set per store view
- Cross-links between language store views when configured (discoverability)

### 6.6 Admin UX

Stores → Configuration → MidCore → llms.txt

- Enable / disable
- Entity toggles + filters
- Cron schedule
- Preview last generation time / status / errors
- Optional robots.txt note (link mention; careful not to break existing robots modules)

### 6.7 Tech constraints

- Magento Open Source / Adobe Commerce 2.4.x
- PHP 8.1+ (confirm target with Jigar Sir)
- No storefront theme dependency (Hyvä / Luma / Blank neutral)
- Prefer plugins/observers/cron/CLI; no core hacks
- Batched / cursor queries for large catalogs (no OFFSET death spirals)

## 7. Non-functional requirements

- Generation must not run on storefront page render
- Memory-bounded on 50k–100k SKU catalogs (design target)
- Fastly/CDN: cache `/llms.txt` with sensible TTL; purge or version on regenerate
- Logging: `var/log/midcore_llms.log` (or monolog channel)
- Spec validation: optional admin “validate against llmstxt.org shape”

## 8. Decisions from Jigar Sir (locked 2026-09-10)

| Question | Decision |
|----------|----------|
| Brand / namespace | `MidCore_LlmsTxt` |
| License | Free / MIT (lead-gen / ecosystem start) |
| v1 outputs | Both `llms.txt` and `llms-full.txt` day one |
| Platform target | Compatible with Magento Open Source **and** Adobe Commerce; include B2B fields where practical without blocking OS installs |
| AI crawl analytics | In scope for v1 (keep lean: bot hits on llms endpoints, not a full Webkul-style suite) |

### Implications for v1 scope

- Module name / Composer: e.g. `midcore/module-llms-txt`, PHP namespace `MidCore\LlmsTxt`
- Ship `llms.txt` + `llms-full.txt` generation and Magento routes for both
- Soft-depend or feature-detect Adobe Commerce B2B (shared catalogs, company context) so Open Source installs cleanly
- Analytics v1: prefer Fastly/edge logs for real crawler hits; optional origin miss-only counter labeled partial; no sync DB write on serve. Defer heatmaps / advanced recommendations to v2.




### Analytics (v1, team lock)

- Prefer Fastly / edge logs for real crawler hits on `/llms.txt` and `/llms-full.txt`
- Optional origin miss-only counter, labeled as partial (CDN long TTL undercounts at origin)
- No sync DB write on the serve path

## 9. Team R&D lanes (in progress)

| Person | Focus |
|--------|--------|
| Rohan · Solution Architect | Serve strategy, Cloud FS, competitor architecture, recommendation |
| Vikram · Platform & Dev | Module skeleton, routes/cron/CLI/config extension points |
| Priya · Deploy & Ops | Cron groups, Cloud mounts, Fastly caching, perf risks |
| Ananya · Frontend & SEO | robots/sitemap coexistence, entity copy quality, AEO notes |
| Maya · Team Trainer | Later training session after go decision |

## 10. Ready for go/no-go

Requirements are locked enough to build a free MIT `MidCore_LlmsTxt` v1: Cloud-native serve path, both llms files, OS + Adobe Commerce compatible, lean AI crawl analytics. Remaining work after **go**: ADR polish, repo scaffold, Cloud runbook, merchant guide.

## 11. Next step after approval

v1 is implemented in-repo: ADR (`docs/ADR-001-cloud-native-serve.md`), Cloud runbook (`docs/CLOUD.md`), merchant install/CLI notes (`README.md`).

