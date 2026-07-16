# EPUB3 Reader Package Prefix Report

Bead: `plib-9axcx`
Base: `d9a52ded95`
Date: 2026-06-15 UTC

This slice keeps EPUB3 package ingestion native-PHP-only while making OPF
package `prefix` declarations explicit in `EpubPackageReader`.

The reader now exposes normalized package prefix declaration review data through
`packageReport.prefixReport` and top-level EPUB aliases:

- `packagePrefixReport`
- `packagePrefixBindings`
- `packagePrefixDiagnostics`

The report preserves the raw prefix string, ordered declarations, retained
bindings by prefix, duplicate-prefix diagnostics, malformed declaration
diagnostics, and package-level diagnostic rollups. Duplicate declarations retain
the later binding, matching the compact `EpubPackage` preflight behavior.

Focused coverage adds a reader fixture with duplicate `schema` declarations,
a `review` declaration, a malformed trailing token, and an `xml:base` package
attribute. The test asserts normalized bindings, duplicate and malformed
diagnostics, package-level validity, summary counters, and top-level aliases.

Verification:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 test file, 1074 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 85654 assertions, 0 failures

Accounting:

- `phpPass`: `3638 -> 3639`
- `phpFail`: `0`
- mapped upstream cases: `3675 -> 3676`
- New mapped row: `mappedEpubReaderPackagePrefixCases = 1`
- New assertion row: `epubReaderPackagePrefixAssertions = 32`

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider tests
were run.
