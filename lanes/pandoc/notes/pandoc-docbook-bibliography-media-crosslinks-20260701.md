# Pandoc DocBook bibliography media crosslinks

Bead: `plib-lco69`
Date: 2026-07-01 UTC
Area: Pandoc DocBook XML review packets

`XmlHtmlDom::summarizeDocBookStructure()` now includes focused review-only
summaries that connect DocBook bibliography entries to media targets without
claiming direct reader parity or loading media payloads.

The `summarizes docbook bibliography media crosslink diagnostics` fixture covers
bibliography entries that link to media targets, inline media objects inside
bibliography-like blocks, resolved media target crosslinks, missing media target
diagnostics, duplicate bibliography-to-media crosslink diagnostics, duplicate
media target id diagnostics, contributor/title/year entry context, and media
target manifest references.

This slice is recorded as a lane note instead of touching
`lane-status.json` or `UPSTREAM_TEST_MANIFEST.json`, because earlier merge
attempts for this bead conflicted in those aggregate files. No Pandoc binary,
XML validator, browser renderer, Node tooling, online service, live provider,
or external validator was invoked.

## Verification

- `php -l lanes/pandoc/src/XmlHtmlDom.php`
- `php -l lanes/pandoc/tests/XmlHtmlDomTest.php`
- focused `XmlHtmlDomTest` case:
  `summarizes docbook bibliography media crosslink diagnostics`
  - `focused 48 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/XmlHtmlDomTest.php`
  - `1 test files, 6356 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - current checkout remains baseline-red outside this slice:
    `534 test files, 142294 assertions, 8912 failures`
