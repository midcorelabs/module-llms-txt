# MidCore_LlmsTxt

Magento 2 / Adobe Commerce module that generates and serves [`llms.txt`](https://llmstxt.org/) (and `llms-full.txt`) so AI agents can read your catalog as clean markdown.

**Status:** repository + requirements. Implementation starts after MidCore go decision.

- Vendor / module: `MidCore_LlmsTxt`
- Composer (planned): `midcore/module-llms-txt`
- License: MIT
- Requirements draft: see MidCore internal `REQUIREMENTS.md`

## Planned behavior

- Generate offline (CLI + cron) into `var/`
- Serve `/llms.txt` and `/llms-full.txt` via Magento routes (Cloud-safe; not written to read-only `pub/` root)
- Open Source + Adobe Commerce compatible
- Lean AI crawl analytics via edge/Fastly logs (optional partial origin miss counter)

## Install (when released)

```bash
composer require midcore/module-llms-txt
bin/magento module:enable MidCore_LlmsTxt
bin/magento setup:upgrade
```
