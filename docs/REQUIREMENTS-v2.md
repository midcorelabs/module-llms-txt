# Magento 2 module: llms.txt v2 (requirements)

## 1. Status / owner / date / relationship to v1

For v1 (shipped) see [REQUIREMENTS.md](REQUIREMENTS.md). This file is the v2 scope lock only.

- Status: **draft** (not an implementation go)
- Owner: Karan · Team Manager (MidCore Magento Team)
- Product lock: Jigar Sir / Magento Team, 2026-09-10
- Last updated: 2026-09-10

v1 `docs/REQUIREMENTS.md` remains the source of truth for shipped behavior. v2 adds the IN list below. It must not break the v1 architecture locks in §4. Implementation starts only after Priya + Ananya lane review and sign-off; Vikram builds after that.

## 2. Problem / goal for v2

v1 generates and serves `llms.txt` + `llms-full.txt` offline under `var/llms/{website_code}/{store_code}/`. Gaps that block a clean merchant rollout:

- Index order is first-N by `entity_id`, not a merchant-curated set.
- One global dirty bit regenerates every store when any store changes.
- Large catalogs can overrun Cloud PHP time/memory; there is no resume.
- Admin shows last-run status but has no "Generate now" that is safe on HTTP.
- `composer require midcore/module-llms-txt` is not live on Packagist yet.
- Optional `llms.jsonl` and noindex / sitemap-parity were left for a later phase.

Goal: ship those eight IN items on the same Cloud-native generate/serve path, then run Maya’s enablement session. No new serve model. No generate-on-request.

## 3. Non-goals (explicit OUT)

- Fastly / edge live smoke (still needs a live env; keep deferred).
- Soft B2B company / shared catalog extras (v1 feature-detect note stays; no extra company-context listing in v2).
- Replacing Google product feeds / ChatGPT Shopping feeds (v1 non-goal, still out).
- Guaranteeing ranking in any AI engine (v1 non-goal, still out).
- Full site HTML to markdown mirroring of every URL (v1 non-goal, still out).
- Training MidCore’s own LLMs on merchant data (v1 non-goal, still out).
- Changing license, module name, or storefront theme coupling.

Do not add product features that are not in §5.

## 4. Locked decisions (v1 + v2)

Inherited from v1 (`REQUIREMENTS.md` §6.4 / §8, `ADR-001`, `CLOUD.md`). Do not break.

| Lock | Decision |
|------|----------|
| Brand / module | `MidCore_LlmsTxt`, MIT, Hyvä / Luma / Blank neutral |
| Platform | Magento Open Source **and** Adobe Commerce 2.4.x (PHP 8.1+) |
| Generate | Offline CLI `midcore:llms:generate` + cron group `midcore` into `var/llms/{website_code}/{store_code}/` |
| Write | Atomic temp + rename. Crawlers never see a half file. |
| Serve | Magento frontend routes read prebuilt files. **Never generate on HTTP request.** Missing file = 404. |
| `pub/` | `PathHelper` hard-blocks `pub/`. Canonical files are never written to `pub/` or `pub/media`. |
| Store resolution | Host → store via Magento / Cloud `routes.yaml`. One artifact set per store view. |
| Cron | Group `midcore` is primary. Cloud crontab that calls `midcore:llms:generate` is override only. Never both. |
| Dirty cron | `only_when_dirty` default **1**. CLI still generates the requested store(s) even if clean. |
| Analytics | No sync DB write on serve. Edge / Fastly logs preferred. Origin miss counter uses cache tag `midcore_llmstxt_analytics` (not `midcore_llmstxt`). |
| v1 outputs | `llms.txt` + `llms-full.txt` stay required (full file still disable-able in config). |
| Index caps | Admin caps stay. `PathHelper::INDEX_SAFETY_CAP` (2000) still applies to `llms.txt`. |
| Composer vs GitHub | Packagist / Composer package is `midcore/module-llms-txt`. GitHub org is `midcorelabs`. The split is **intentional**. Do not publish as `midcorelabs/module-llms-txt`. |

v2 product lock (this file): IN items in §5. OUT items in §3 and §11.

## 5. Feature requirements (IN)

Each item is in scope for v2. Lanes: Vikram = build, Priya = Cloud review, Ananya = SEO review, Rohan = architecture, Maya = enablement (non-code).

### 5.1 noindex / sitemap-parity filter

**Lanes:** Vikram (build), Ananya (SEO review), Rohan (filter vs collector design)

**Requirement**

- Products, categories, and CMS pages that Magento sitemap generation would omit for that store must not appear in `llms.txt`, `llms-full.txt`, or `llms.jsonl`.
- Entities with robots / meta robots **noindex** must not appear in those files.
- Existing v1 filters stay: disabled / not visible products; checkout, cart, customer, wishlist, API, admin paths; optional OOS exclude.
- Do not scrape a generated `sitemap.xml` file on disk. Mirror Magento sitemap **eligibility** (status, visibility, store assignment, robots) in collectors.

**Acceptance**

- A product that is enabled but `NOINDEX` is absent from all three artifacts after generate.
- A product Magento sitemap would skip (disabled, not visible, wrong store) is absent.
- Same for category and CMS page cases Ananya lists in review (attribute names differ by entity; lock the matrix in the SEO review, then Vikram implements that matrix only).
- Eligible entities still appear. Caps and pin list still apply to the curated index.
- Unit tests cover the filter; no live Fastly required.

### 5.2 Admin “Generate now” + last-run status (async)

**Lanes:** Vikram (build), Priya (Cloud / HTTP timeout), Rohan (queue vs cron)

**Requirement**

- Admin (Stores → Configuration → MidCore → llms.txt) gets a **Generate now** control plus last-run status.
- The Adminhtml request must **not** run a full generate on the HTTP thread. Large catalogs must not hit gateway / PHP timeouts.
- Work uses the same Generator path as CLI / cron (var write, atomic rename, never on storefront request).
- Explicit Generate now bypasses `only_when_dirty` for the target store(s), same as CLI.
- Do not add a second Cloud crontab. Do not require RabbitMQ. Priya + Rohan pick a Cloud-safe async mechanism (Magento queue with default MySQL consumer, or a generate-request flag drained by group `midcore` on a short interval when a request is pending). Must work on OS and Cloud.

**Status fields (per store view)**

- Last run: success / error / never generated (v1 already shows this; keep).
- Add: queued / running / resumed (checkpoint) / bytes per artifact including jsonl when enabled.
- Per-store dirty yes/no (replaces the single global dirty line).
- Origin miss counter stays partial; tag `midcore_llmstxt_analytics` unchanged.

**Acceptance**

- Clicking Generate now returns immediately with a queued/running state. Magento access log for that POST is not a multi-minute generate.
- A 50k+ SKU store completes via async + budget/resume, not via the browser request.
- Last-run status updates when the job finishes or errors; errors stay visible (v1 status `error` field).
- Store-scope config: button targets that store. Default/website scope: enabled stores in that scope. Disabled stores are skipped.

### 5.3 Packagist publish (`composer require midcore/module-llms-txt`)

**Lanes:** Vikram (package/tag hygiene), Priya (install smoke from a clean Magento root), Maya (enablement waits on this)

**Requirement**

- Public Packagist package name **must** match `composer.json`: `midcore/module-llms-txt`.
- GitHub repository stays `midcorelabs/module-llms-txt`. Composer vendor `midcore/` vs GitHub org `midcorelabs` is intentional. Document it (README already does; keep that sentence).
- Do not register `midcorelabs/module-llms-txt` on Packagist.
- Tagged releases so Composer can resolve a stable constraint. Packagist GitHub hook / auto-update enabled.
- MIT, type `magento2-module`, existing Magento 2.4 constraints unchanged.

**Acceptance**

- From a Magento project **without** a VCS `repositories` entry for this module: `composer require midcore/module-llms-txt` installs, `module:enable MidCore_LlmsTxt`, `setup:upgrade` succeeds.
- Packagist page shows GitHub `midcorelabs/module-llms-txt` as source.
- This is a publish/ops deliverable, not a PHP feature. Still required to close v2.

### 5.4 Per-store dirty flags

**Lanes:** Vikram (build), Rohan (observer scoping), Priya (cron behavior)

**Requirement**

- Replace the single flag `midcore_llmstxt_dirty` with a dirty bit **per store view**.
- Observers mark only the store(s) affected: product/category/CMS save and delete, store change, this module’s config save.
- Config save at default or website scope dirties every store in that scope.
- Cron with `only_when_dirty=1` generates **only** stores that are dirty or have an incomplete checkpoint (§7). Other stores are skipped.
- CLI / Generate now still generate the requested store(s) even if clean.
- A successful generate for store S clears **only** S. Other stores stay dirty if they were dirty.
- A failed generate for S leaves S dirty.

**Acceptance**

- Edit a product on store A only: cron does not rebuild store B.
- Default-scope config save: all enabled stores dirty.
- Multi-store cron log / status shows per-store skip vs run.
- Upgrade from v1 global flag: if the old global bit is set, mark all active stores dirty once, then stop using the global bit (§10).

### 5.5 Soft time/memory budget + resume checkpoints

**Lanes:** Vikram (build), Priya (Cloud PHP limits), Rohan (checkpoint vs atomic rename)

**Requirement**

- Generate runs under a **soft** time and memory budget (config, default-scope). When the budget is hit, the process stops cleanly and can resume later. It is not a hard kill with a half-renamed file.
- Checkpoint is **per store**: collector phase (categories / products / CMS / etc.) + last `entity_id` (or equivalent cursor) + which artifacts are still open.
- Writes go to a temp file. **Atomic rename of the canonical file happens only at the end of that store’s successful run.** If the budget hits mid-store, do not rename. The previously published file stays in place.
- Serve path never reads `.tmp.*` or checkpoint metadata. Half files are never served (404 only if no prior canonical file exists).
- Resume continues from last `entity_id` for that store. Cursor remains `entity_id > last` batches (no OFFSET).
- `llms-full.txt` (and jsonl when enabled) may still walk the catalog by `entity_id` cursor; the budget applies to the whole store run, not only the curated index.

**Acceptance**

- Kill/stop at budget mid-catalog: `/llms.txt` still returns the last complete file (or 404 if none). Never a truncated body.
- Next cron/CLI/Generate-now for that store resumes and eventually renames; status shows resumed then success.
- Two stores: budget stop on store A does not skip or corrupt store B’s checkpoint.
- Unit or integration test: rename is not called until the producer finishes that artifact set.

### 5.6 Smarter curated `llms.txt` order (caps stay)

**Lanes:** Vikram (build), Ananya (what merchants should pin), Rohan (rank vs cursor)

**Requirement**

- Curated **`llms.txt` product order is not first-N by `entity_id`.**
- Fill the product section in this order, then stop at the existing caps (`max_products_index`, safety cap 2000):
  1. Merchant **pin list** (admin, store-view scoped, ordered SKUs or product entity IDs). Skip pins that fail §5.1 or v1 eligibility.
  2. Remaining slots: Magento **category featured** and/or **bestseller** signals (Magento reports / featured category products already in the catalog). No new custom ranking attribute required for v2.
- Category section may use featured / catalog position rather than `entity_id` ASC; category cap unchanged (`max_categories_index` + safety cap).
- `entity_id` remains the **batch cursor**, not the rank key for the curated index.
- If pins + signals yield fewer items than the cap, the index is shorter. **Do not backfill the curated index with first-N `entity_id`.**
- `llms-full.txt` stays a fuller listing (not a ranked index). Caps do not apply the same way (v1: full lists all eligible).

**Acceptance**

- Two eligible products, ids 2 and 99: pinning 99 lists 99 before 2 in `llms.txt`.
- Unpinned catalog: featured / bestseller order is used; test fixture does not assert `entity_id` ASC as the index order.
- Raising or lowering max products / categories still truncates the index; safety cap still blocks “unlimited” dumps.
- Pinning a noindex or disabled SKU does not leak it into the file.

### 5.7 Optional `llms.jsonl` (same Cloud-native path)

**Lanes:** Vikram (build), Priya (Cloud path + headers), Rohan (ADR-001 apply), Ananya (SEO: do not Disallow the URL)

**Requirement**

- Optional JSONL export, **off by default**.
- Generate offline into `var/llms/{website_code}/{store_code}/llms.jsonl` during the same generate run as the txt files.
- Magento frontend route **`/llms.jsonl`**. Store from host. **Never generate on request.** Missing or disabled = 404.
- `PathHelper` allows `llms.jsonl` and still hard-blocks `pub/`.
- Same filters as txt (v1 eligibility + §5.1). Same atomic temp + rename at end of store run. Same cron / CLI / Generate now / budget / dirty flags.
- Cache headers follow ADR-001 (`Cache-Control`, `ETag`, `X-Magento-Tags` including `midcore_llmstxt` / `midcore_llmstxt_{storeId}`).
- No sync DB write on serve. Origin miss counter may increment on jsonl 404 the same way as txt (still partial; tag `midcore_llmstxt_analytics`).
- Content: one JSON object per line for included entities (identity fields already collected: name, url, sku when product, short text, type). No extra embedding / vector product.

**Acceptance**

- Disabled: `/llms.jsonl` is 404; no jsonl file required under `var/llms/`.
- Enabled + generated: file exists only under `var/llms/{website}/{store}/`; GET `/llms.jsonl` returns it; `pub/llms.jsonl` is not created.
- Storefront request with empty `var/` does not start generate.
- Fastly live smoke is **not** required to accept this item (deferred, §11).

### 5.8 Enablement session (Maya)

**Lanes:** Maya (owner), after §5.3 Packagist **and** §5.2 Admin generate are done

**Requirement**

- Process / training deliverable, **not product code**.
- Session covers: Composer install from Packagist, enable per store, Generate now + cron group `midcore`, dirty vs checkpoint, Cloud `var/` vs `pub/`, what not to Disallow in robots.txt (include `/llms.jsonl` when enabled).
- No new module features from the training deck.

**Acceptance**

- Session scheduled and delivered after Packagist + Admin generate land. Tracked as a team deliverable, not a GitHub code checkbox.

## 6. Serving / Cloud constraints

Inherit [ADR-001](ADR-001-cloud-native-serve.md) and [CLOUD.md](CLOUD.md). jsonl uses the same pattern as txt.

- Generate: CLI / cron / async admin job only. Output `var/llms/{website_code}/{store_code}/`.
- Serve: Magento routes `/llms.txt`, `/llms-full.txt`, `/llms.jsonl`. `var/` is not a public web root.
- Never write canonical files to `pub/` or `pub/media`. `PathHelper::assertWritableRelative()` stays the gate.
- Never generate inside `FileServer` / frontend controllers. 404 if the file is missing.
- Cache tags for artifact purge: `midcore_llmstxt`, `midcore_llmstxt_{storeId}`. Analytics counter stays on `midcore_llmstxt_analytics`.
- Cron group `midcore` is the scheduler. Optional Cloud crontab is **override only** (module cron disabled). Do not run both.
- Post-deploy: generate once (CLI or Generate now async) so the first crawler is not a 404.
- `routes.yaml` must keep these paths on the Magento upstream (default Cloud routing). Do not add static `pub/llms.jsonl`.

## 7. Dirty flags + checkpoint resume (must compose)

Per-store dirty (§5.4) and resume checkpoints (§5.5) are one state machine. Do not implement them as independent globals.

| State for store S | Meaning | Cron (`only_when_dirty=1`) | Serve |
|-------------------|---------|----------------------------|--------|
| clean, no checkpoint | Published file is current | Skip S | Canonical file or 404 |
| dirty, no checkpoint | Stale file; no run in progress | Start S from the beginning | Last canonical file until rename |
| checkpoint present, S **not** dirtied after that checkpoint started | Budget pause; temp is still valid | Resume S from last `entity_id` | Last canonical file (temp not served) |
| checkpoint present, S **was** dirtied after that checkpoint started | Catalog/CMS/config changed mid-run; temp is invalid | **Drop checkpoint + temp**, restart S | Last canonical file |
| success (rename done) | S is current | Clear S dirty and S checkpoint only | New canonical file |
| error | Failed this attempt | Leave S dirty; do not rename a partial. Rohan picks drop vs keep checkpoint; keep is only allowed if the temp is still valid | Last canonical file |

Rules:

1. Dirty = published artifacts for S are stale. Checkpoint = an unfinished generate for S that may resume. Store a `dirty_at` timestamp and a `checkpoint_started_at` timestamp. "Dirtied after checkpoint started" means `dirty_at` > `checkpoint_started_at` (a new observer mark, not the dirty bit that started the run).
2. Budget stop: leave S dirty, persist checkpoint, **do not rename**. This dirty bit is the original one; do not treat it as mid-run invalidation.
3. Mid-run dirty (observer fires for S after `checkpoint_started_at`): restart, do not resume. Resume would mix old and new catalog batches.
4. Completing S never clears dirty/checkpoint for store T.
5. `only_when_dirty=0`: cron still uses per-store budget/resume; it may start stores that are clean (v1: generate all enabled). Prefer not to restart a clean store that already has a fresh file unless there is no checkpoint and a full run was requested (CLI / Generate now).
6. Generate now / CLI for S: run even if clean; if a valid checkpoint exists and S was **not** dirtied after that checkpoint started, resume; if S was dirtied after, restart.
7. Temp name stays unguessable (v1 `.tmp.{random}`). Router must not map `/llms.txt.tmp.*`.
8. Cron should also run S when a valid checkpoint exists even if you would otherwise skip (unfinished work). A budget-paused store must not wait for a second catalog edit.

Rohan signs this table before Vikram codes it. Priya checks it against Cloud cron duration and `var/` disk.

## 8. Admin UX additions

Stores → Configuration → MidCore → llms.txt (additive; do not reshuffle v1 groups without need).

- **Generate now** (async) + visible job state (§5.2).
- Last generation: per store, including dirty, checkpoint/resume, jsonl bytes when enabled, errors.
- **Pin list** for curated `llms.txt` (ordered SKUs or IDs), store-view scoped (§5.6).
- **Enable `llms.jsonl`** (default No), store-view scoped (§5.7).
- Soft time / memory budget fields, default scope, next to batch size (§5.5).
- Cron: keep enable, schedule, `only when dirty` default Yes, batch size. Comment must say dirty is **per store**.
- Analytics comment unchanged in intent: no DB write on serve; edge preferred; origin miss is partial.

No storefront UI. No new Magento admin menu outside this config section unless ACL for Generate now requires a controller under the existing `MidCore_LlmsTxt::config` resource.

## 9. Packagist / Composer publish checklist

- [ ] `composer.json` `name` is `midcore/module-llms-txt` (already true; do not change to `midcorelabs/...`).
- [ ] README states GitHub org `midcorelabs` vs Composer vendor `midcore/` is intentional (already true; keep).
- [ ] GitHub repo `midcorelabs/module-llms-txt` is the Packagist source.
- [ ] Create Packagist package **once** as `midcore/module-llms-txt`.
- [ ] Enable Packagist auto-update (GitHub service / webhook).
- [ ] Push a git tag for the release Composer should install.
- [ ] Confirm a clean Magento 2.4 root with Magento Composer credentials can `composer require midcore/module-llms-txt` with **no** extra VCS repository.
- [ ] `module:enable MidCore_LlmsTxt` + `setup:upgrade` on OS and (when available) Commerce.
- [ ] Do not also publish under the GitHub org vendor name.

Maya’s session (§5.8) waits until this checklist is done and Generate now is in the tagged release.

## 10. Migration / upgrade notes from v1 installs

- Artifacts already in `var/llms/{website}/{store}/` remain valid. Next successful generate replaces them via atomic rename.
- Global dirty flag: if set at upgrade, mark all active stores dirty once; then use per-store flags only.
- New config (`llms.jsonl` enable, pin list, budgets) defaults: jsonl **off**, pin list **empty**, budgets conservative. Empty pin + empty signals → shorter curated index, not a silent revert to first-N `entity_id` (§5.6).
- noindex / sitemap-parity may **drop URLs** that v1 listed. Expected. Note in changelog for Ananya to brief merchants.
- Admin Generate now is new; merchants who only used CLI keep working.
- `only_when_dirty` stays default 1. Cron group stays `midcore`. Cloud crontab rule unchanged: not both.
- PathHelper still rejects `pub/`. jsonl is an extra allowed filename under `var/llms/` only.
- Composer: merchants who required the module via VCS `dev-main` can switch to Packagist version constraints; document in README when the package is live. No Magento data patch should fail OS installs.
- B2B: keep v1 feature-detect guest/default-catalog note. No new shared-catalog generate path in v2.

## 11. Out of scope / deferred

- Fastly / edge live smoke and Fastly log dashboard work (needs env).
- Soft B2B company / shared catalog extras (company-specific files, shared-catalog-only SKUs as a first-class index).
- Heatmaps / advanced AI crawl recommendations (v1 already deferred past lean analytics).
- Magento Marketplace listing (not in the v2 IN list).
- Changing analytics to sync DB writes on serve.
- Generate-on-request fallback when `var/` is empty.
- Writing `pub/llms.txt` or `pub/llms.jsonl` “for non-Cloud”.

## 12. Sign-off

Draft for **Priya** (Cloud: async Generate now, budgets, cron, jsonl path) and **Ananya** (SEO: noindex / sitemap-parity matrix, pin/featured guidance, robots Allow for `/llms.jsonl`).

**Rohan** confirms §4 locks and §7 state machine before coding.

**Vikram** builds only after this file is signed for those lanes.

**Maya** enablement session is after Packagist + Admin generate, not a code merge gate.

Go/no-go for v2 implementation: Jigar Sir after Priya + Ananya (and Rohan on §7) sign. This document is not a go by itself.
