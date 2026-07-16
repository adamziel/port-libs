# XML/HTML5 DOM Script JSON Review Slice

## Scope

- `XmlHtmlDom` now classifies HTML `<script>` payloads as classic, module, importmap, speculationrules, JSON data, or inert data blocks.
- Script summaries record non-executing reviewer metadata for loading mode, executable/data-block state, CORS/referrer/fetch-priority validity, and `blocking` token counts.
- Import maps, speculation rules, and JSON data blocks get bounded inert JSON review summaries, including top-level keys, import-map object counts, speculation-rule set diagnostics, and syntax-error provenance.
- Deterministic raw HTML serialization and WordPress raw block handoff remain unchanged; this slice does not execute JavaScript, invoke Pandoc, use browser renderers, call online sanitizers, or run external validators.

## Status Delta

- `phpPass`: `3498 -> 3499`
- `phpFail`: `0`
- Added `mappedXmlHtmlDomScriptJsonReviewCases = 1`
- Added `xmlHtmlDomScriptJsonReviewAssertions = 39`

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- `jq empty lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc/lane-status.json lanes/pandoc/src/XmlHtmlDom.php lanes/pandoc/tests/XmlHtmlDomTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php` -> 1 file, 3995 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 46 files, 82289 assertions, 0 failures
