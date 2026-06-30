# ODF Configuration Package Invalid Declared Size

Hook: `plib-8sfgi`

Slice: native ODF/ODT package ingestion parity for `Configurations/` and
`Configurations2/` package sidecars.

Change:
- `OdfReader` now reports configuration package entries with malformed
  `manifest:size` values using
  `odf-configuration-package-invalid-declared-size`.
- `OpenDocumentPackage` carries the same compact summary fields and issue code
  as `odf-configuration-invalid-declared-size`.
- Configuration package items now expose `declaredSizeRaw`,
  `declaredSizeValid`, and `declaredSizeInvalid`, and the summaries expose
  `invalidDeclaredSizeCount` while keeping configuration bytes metadata-only.

Validation:
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php` (1 file, 81 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php` (2 files, 2,240 assertions, 0 failures)
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` (1 file, 5,141 assertions, 0 failures)
- Post-rebase `php tools/run-tests.php lanes/pandoc/tests/OdfReaderLegacyConfigurationPackageTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php` (3 files, 7,381 assertions, 0 failures)

Parity accounting:
- ODF/ODT package-ingestion coverage gains one focused configuration sidecar
  provenance case. No Pandoc binary, office suite, zip/unzip tool, converter,
  browser engine, validator, or network service was invoked.
