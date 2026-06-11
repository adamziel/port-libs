# ODF Embedded Object Package Inventory

Bead: `plib-47x3e`

## Behavior

`OdfReader` now reports embedded object subpackages as inert package-review
metadata. The report includes referenced object roots, missing declared object
roots, contained package parts, byte/CRC/compression provenance, replacement
preview records under `ObjectReplacements/`, and explicit non-exposure policy
for object payloads.

`ObjectReplacements/*` preview images are no longer surfaced as ordinary
document media. They remain visible in `embeddedObjects.replacements` with
metadata-only policy and byte provenance.

## Accounting

- Added one focused `OdfReaderTest` PASS case.
- `lanes/pandoc/lane-status.json` `phpPass`: `3073 -> 3074`.
- No Pandoc, office suite, `zip`/`unzip`, browser renderer, external validator,
  online service, live provider test, or live-service provider test was invoked.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - 1 test file, 3882 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64131 assertions, 0 failures
