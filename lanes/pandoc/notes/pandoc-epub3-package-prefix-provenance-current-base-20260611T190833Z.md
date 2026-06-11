# EPUB3 package prefix provenance slice 2026-06-11T190833Z

Scope: `plib-dghqw` / EPUB3 package ingestion.

This slice extends the compact native PHP `EpubPackageReader` path to preserve
OPF package `prefix` declarations for package review. The document `epub`
attribute now exposes:

- `packagePrefix` raw declaration text;
- `packagePrefixes` as the latest binding map by prefix;
- `packagePrefixBindings` as ordered duplicate-aware declarations;
- `packagePrefixDiagnostics` for duplicate and malformed declarations.

Focused coverage keeps the shared EPUB3 fixture valid with `schema` and
`rendition` declarations, then copies the fixture into a temporary package to
verify duplicate-prefix latest-wins behavior and invalid declaration
diagnostics without making the base fixture intentionally malformed.

Verification on current main `a886765f4`:

- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  passed 1 test file, 118 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed 44 test files, 65438 assertions, 0 failures.

Status:

- Adds one `EpubPackageReaderTest` PASS case and 19 focused assertions.
- Moves `phpPass` `3101 -> 3102`; `phpFail` remains `0`.
- Does not invoke Pandoc, EPUBCheck, zip/unzip, browser renderers, external
  validators, online services, live provider tests, or live-service provider
  tests.
