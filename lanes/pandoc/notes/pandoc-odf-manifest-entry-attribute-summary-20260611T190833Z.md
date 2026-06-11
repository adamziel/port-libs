# pandoc-odf-manifest-entry-attribute-summary-20260611T190833Z

Slice: `plib-v966k` ODF/ODT OpenDocument package ingestion core blocker.

`OpenDocumentPackage` now carries compact ODF manifest file-entry attributes
through package review summaries. Manifest `version`, `preferred-view-mode`,
and inert encryption metadata are preserved in media summaries,
`manifestReview` rows, encrypted-item review rows, and ZIP package inventory
records. Encrypted package bytes remain blocked.

This does not invoke Pandoc, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

Verification on current main `a886765f4`:

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 1 file, 360 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 65440 assertions, 0 failures

Metric:

- `phpPass`: `3101 -> 3102`
- `phpFail`: `0`
- `mappedOdfManifestEntryAttributeSummaryCases`: `1`
- `odfManifestEntryAttributeSummaryAssertions`: `21`
