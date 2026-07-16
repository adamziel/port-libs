# ODT settings package ingestion slice

Bead: `plib-rpbgw`

Base: current `origin/main` `879a565238bee43250900c80cc13db10ad5c7728`

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
  - `44 test files, 65303 assertions, 0 failures`

Lane status: `phpPass` moves `3098 -> 3099`.
