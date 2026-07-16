# pandoc-epub-compact-date-event-namespace

Hook: `plib-cby81`, Pandoc EPUB3 package ingestion core blocker slice.

## Summary

Compact EPUB package ingestion now preserves namespaced OPF `dc:date` event
metadata. `EpubPackage` reads `opf:event` with the same namespace-aware policy
already used for `opf:scheme`, carrying the event through `dateDetails`,
`datesByEvent`, `dateSummary`, and WordPress import `metadataDetails`.

This is bounded native PHP EPUB package support only. It does not invoke
Pandoc, EPUBCheck, `zip`/`unzip`, `ZipArchive`, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

## Verification

- Red-first focused run failed before the reader change:
  `Expected: 'publication'`, `Actual: NULL`.
- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`
  - `1 test files, 3207 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 87923 assertions, 0 failures`

## Accounting

- `phpPass`: `3712 -> 3713`
- `phpFail`: `0`
- mapped upstream cases: `3735 -> 3736`
- `mappedEpubCompactDateEventNamespaceCases`: `0 -> 1`
- `epubCompactDateEventNamespaceAssertions`: `0 -> 10`
