# Pandoc HTML Microdata Meta Content Current Base

Slice: `pandoc-html-microdata-meta-content-current-base-20260609T223433Z`

## Status

This worker slice is already covered by current `main`.

- Current `Html5DomFragment` already preserves hidden
  `<meta itemprop="..." content="...">` values as inert microdata review spans.
- The current implementation also records `data-pandoc-microdata-source="meta"`,
  rejects invalid or empty content as unsafe metadata, and counts preserved
  values in scoped microdata summaries.
- Current `Html5DomFragmentTest` already covers valid meta itemprop content,
  invalid itemprop tokens, empty content, WordPress output, filtered source
  attributes, diagnostics, and scoped summary counts.

## Resolution

No code, test, manifest, or lane-status counter change is applied by this merge.
The current-base implementation is broader than the worker branch version, so
the MR is closed with this note only to avoid double counting an already-mapped
PHP pass.

## Verification

- `php -l lanes/pandoc/src/Html5DomFragment.php`
- `php -l lanes/pandoc/tests/Html5DomFragmentTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/Html5DomFragmentTest.php`
  - 1 file, 2537 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 58152 assertions, 0 failures.

## Scope and exclusions

No Pandoc, Cabal/Haskell runner, browser renderer, external validator, online
service, live provider test, or live-service provider test is needed for this
superseded current-base resolution.
