# pandoc-pdf-typst-timings-boundary-current-base-20260611T164639Z

Slice: `plib-7yyq7`, PDF/Typst boundary provenance.
Base: current `origin/main` `ac1f74a84`.

## Change

`PdfEngineHandoff` now preserves Typst `--timings` output artifact boundary
provenance without executing Typst. Plans record the selected timing output,
unsafe timing-output history, duplicate timing-output overrides, and a
`typst-timings-output:*` diagnostic.

When the selected `--timings` value is a safe relative path, it is also tracked
as an expected engine artifact so fake-run artifact hashes and artifact review
metadata can account for the sidecar. Unsafe prior values remain visible as
review provenance instead of being silently dropped.

No Pandoc, Typst, TeX/PDF engines, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 1689 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64061 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3074 -> 3075`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3195 -> 3196`.
- Added one focused `PdfEngineHandoffTest` PASS case for Typst timing output
  boundary provenance.
