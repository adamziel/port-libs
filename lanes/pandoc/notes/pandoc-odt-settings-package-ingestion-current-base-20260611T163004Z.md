# ODT settings package ingestion slice

Bead: `plib-rpbgw`

Base: current `origin/main` `e6767f509`

## Scope

- Added native `settings.xml` ingestion to `OdtReader::readPackage()`.
- Preserves OpenDocument `config:config-item-set` records, direct config items, typed values, indexed maps, named maps, and map entries.
- Exposes the settings summary in the top-level read result, document attributes, and `importReport['settings']`.
- Keeps the path native PHP only; no Pandoc, office suite, `zip`/`unzip`, browser, external validator, online service, live provider, or live-service provider calls.

## Verification

- `php -l lanes/pandoc/src/OdtReader.php`
- `php -l lanes/pandoc/tests/OdtReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdtReaderTest.php`
  - `1 test file, 167 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65600 assertions, 0 failures`

Lane status: ODT settings package ingestion remains accounted with
`mappedOdtSettingsPackageIngestionCases = 1`,
`odtSettingsPackageIngestionAssertions = 22`, and current `phpPass = 3107`.
