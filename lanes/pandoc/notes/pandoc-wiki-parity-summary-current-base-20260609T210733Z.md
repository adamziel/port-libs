# Pandoc wiki parity summary current-base slice

## Scope

Adds one bounded native PHP format-registry review slice for Pandoc wiki formats.
The slice stays under `lanes/pandoc` and does not implement or claim direct wiki
reader/writer parity.

## Implementation

- `PandocFormatRegistry::wikiFormatParitySummary()` now preserves the current
  packet-derived count API and also exposes compact review counts for wiki
  input/output formats, unique formats, direction buckets, support-status
  buckets, extension-inference buckets, unsupported input/output counts, direct
  reader/writer parity booleans, and unsupported direct parity status.
- The summary keeps direct wiki reader and writer parity explicitly
  `unsupported`; no native PHP wiki reader or writer implementation is
  registered.
- `PandocFormatRegistryTest.php` adds one focused PASS case covering the
  compact summary surface alongside the existing parity-count summary case.

## Accounting

- `lane-status.json` `phpPass`: `2915 -> 2916`
- `lane-status.json` `suiteProgress`: `818 -> 819`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3100 -> 3101`
- Updated `mappedPandocFormatRegistryWikiParitySummaryCases: 1 -> 2`
- Updated `pandocFormatRegistryWikiParitySummaryAssertions: 9 -> 25`

## Verification

- `php -l lanes/pandoc/src/PandocFormatRegistry.php`
- `php -l lanes/pandoc/tests/PandocFormatRegistryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PandocFormatRegistryTest.php`
  - `1 test files, 815 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `42 test files, 58564 assertions, 0 failures`

No Pandoc, wiki renderer, Cabal/Haskell runner, TeX/PDF engine, browser
renderer, zip/unzip, external validator, online service, live provider test, or
live-service provider test was executed.
