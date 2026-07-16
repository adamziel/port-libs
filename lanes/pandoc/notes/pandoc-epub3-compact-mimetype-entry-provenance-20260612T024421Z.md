# Pandoc EPUB3 Compact Mimetype Entry Provenance

Slice: `plib-ddofr`, EPUB3 package ingestion core blocker.

Implemented bounded native PHP compact EPUB package mimetype-entry provenance in `EpubPackage`.

## Behavior

- `EpubPackage::fromPackage()` now validates the required OCF `mimetype` entry through the shared ZIP stored-first-entry preflight.
- Added `EpubPackage::mimetypeEntry()` so compact EPUB import callers can inspect the selected validation/provenance record directly.
- `summary()` and `summary()['wordpressImport']` now expose `mimetypeEntry` with local-entry order, compression method/name, data-descriptor status, extra-field IDs, byte counts, content match, validity, and diagnostics.
- The change keeps validation bounded to the existing native ZIP reader and does not introduce EPUBCheck, Pandoc, zip/unzip, browser, Node, or external service dependencies.

## Verification

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `git diff --check`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 test file, 1878 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 69475 assertions, 0 failures

No Pandoc, EPUBCheck, office suite, browser renderer, zip/unzip, Node tooling, external validator, online service, or live provider test was run.
