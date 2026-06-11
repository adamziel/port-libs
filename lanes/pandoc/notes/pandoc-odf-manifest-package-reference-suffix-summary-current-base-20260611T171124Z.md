# pandoc-odf-manifest-package-reference-suffix-summary-current-base-20260611T171124Z

Slice: `plib-mck5g`, ODF/ODT OpenDocument package ingestion.
Base: current `origin/main` `0091a9f731`.

## Change

`OdfReader` now exposes `packageReferenceSuffixSummary` in both the document
manifest metadata and import-report manifest metadata. The summary records ODF
manifest declarations whose `manifest:full-path` includes query or fragment
suffixes while preserving stripped ZIP package-part lookup for media ingestion.

No Pandoc, office suites, zip/unzip, Cabal/Haskell runners, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  passed: 1 test file, 3856 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 64239 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3078 -> 3079`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3199 -> 3200`.
- Added one focused `OdfReaderTest` case with 16 assertions.
