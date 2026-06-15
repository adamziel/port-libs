# EPUB3 Reader Identity Report

Bead: `plib-h2id2`
Base: `8bf3b51ea3`
Date: 2026-06-15 UTC

This slice keeps EPUB3 package ingestion native-PHP-only while making OPF
package identity review data explicit in `EpubPackageReader`.

The reader now exposes `packageReport` and the `identityReport` alias on EPUB
document attrs. The report preserves package `id`, `version`,
`unique-identifier`, language, direction, `xml:base`, prefix, selected
identifier details, identifier refinements, duplicate unique-identifier ID
diagnostics, and duplicate `dc:identifier` value diagnostics. The reader also
promotes the OPF `unique-identifier` value to `meta.identifier` when present.

Focused coverage adds a package fixture with duplicate identifier IDs, duplicate
identifier values, and identifier-type refinements to assert the selected
identifier, report aliases, summary counters, and diagnostics.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 test file, 1042 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 85479 assertions, 0 failures

Accounting:

- New mapped row: `mappedEpubReaderIdentityReportCases = 1`
- New assertion row: `epubReaderIdentityReportAssertions = 50`

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were run.
