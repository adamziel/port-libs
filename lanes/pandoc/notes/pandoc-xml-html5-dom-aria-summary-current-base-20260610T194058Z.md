# Pandoc XML/HTML5 DOM ARIA Summary Slice

## Scope

Added bounded native `XmlHtmlDom::summarizeHtmlFragment()` accessibility metadata for HTML reviewer handoff. Element summaries now include ARIA role tokens, `aria-label` text, IDREF target/missing inventories, common ARIA state tokens, and numeric value/property metadata.

This does not change HTML serialization and does not invoke Pandoc, browser renderers, online sanitizers, external validators, or live services.

## Accounting

- Rebased stale `plib-3jyc7` over `origin/main` `3866414a3872bc8b19eaf933ca45b4725ec4b2f0`.
- `lane-status.json` `phpPass`: `3021 -> 3022`; `phpFail` remains `0`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `3167 -> 3168`.
- Added `mappedXmlHtmlDomAriaSummaryCases: 1`.
- Added `xmlHtmlDomAriaSummaryAssertions: 11`.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` passed `1` file / `407` assertions / `0` failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed `44` files / `61619` assertions / `0` failures.
