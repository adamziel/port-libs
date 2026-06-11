# EPUB Container Rootfile Duplicate Package Part Provenance

Bead: `plib-yd27y`

Current base: `origin/main` at `9c821d42a`.

## Scope

This slice keeps EPUB3 package ingestion focused on OCF `META-INF/container.xml`
rootfile handoff. It preserves duplicate rootfile package-part declarations in
`EpubReader` without changing the selected OPF rootfile used for normal reads.

## Behavior

- Adds aggregate container fields for duplicate rootfile package parts:
  `duplicateRootfilePartCount`, `duplicateRootfileParts`,
  `duplicateRootfilePartItems`, and `rootfileDiagnostics`.
- Adds per-rootfile duplicate provenance:
  `duplicatePackagePart`, `duplicatePackagePartIndexes`,
  `duplicatePackagePartMediaTypes`, and `diagnostics`.
- Keeps the first selected OPF rootfile selected while duplicate same-path
  rootfiles remain visible as alternate rendition review records.
- Propagates the enriched container payload through `importReport`.

## Verification

- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 test file, 4067 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 66783 assertions, 0 failures.

No Pandoc, EPUBCheck, zip/unzip, browser renderers, external validators, online
services, live provider tests, or live-service provider tests were used.
