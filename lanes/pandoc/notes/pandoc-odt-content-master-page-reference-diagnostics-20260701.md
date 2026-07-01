# Pandoc ODT Content Master Page Reference Diagnostics

## Scope

- Added bounded native `OdfReader` style diagnostics for content-level
  `text:master-page-name` references that point at missing ODT master pages.
- Reports missing content references as
  `odf-content-missing-master-page` with source part, element, attribute, and
  master-page name metadata.
- Preserves parsed note-configuration master-page names and existing
  metadata-only package provenance behavior without exposing package bytes.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderContentMasterPageReferenceDiagnosticsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderContentMasterPageReferenceDiagnosticsTest.php`
  - 1 test file, 16 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 5310 assertions, 0 failures

No Pandoc executable, Cabal solver/build/test command, Haskell runner, office
suite, zip/unzip command, TeX/PDF engine, browser renderer, external validator,
online service, live provider test, or live-service provider test was executed.

## Accounting

- The mapped denominator moves from 2879 to 2880 with one focused ODT content
  master-page reference diagnostics pass case and 16 focused assertions.
