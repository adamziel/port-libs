# Pandoc ODF Compact Manifest Reference Suffix Summary

Slice: `plib-zk1pj`
Date: 2026-06-11 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Behavior

`OpenDocumentPackage` now exposes compact manifest full-path suffix provenance in
`manifestReview`:

- per-entry `manifestIndex` and `manifestFileEntryOrder` rows;
- aggregate suffix, query, and fragment counts;
- suffix-bearing manifest reference rows with stripped ZIP part targets;
- corrected ZIP inventory declaration accounting for suffixed or URI-decoded
  manifest paths so stripped package parts are not marked undeclared.

This is native PHP package review only. It does not invoke Pandoc, office suites,
zip/unzip, browser renderers, external validators, online services, live provider
tests, or live-service provider tests.

## Accounting

- `phpPass`: `3133 -> 3134`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3217 -> 3218`
- `mappedOdfManifestReferenceSuffixSummaryCases`: `+1`
- `odfManifestReferenceSuffixSummaryAssertions`: `+35`

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 418 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 66667 assertions, 0 failures`
