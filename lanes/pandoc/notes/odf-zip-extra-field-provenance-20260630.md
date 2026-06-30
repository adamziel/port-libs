# ODF ZIP extra-field provenance

Slice: `plib-g9920`

Date: 2026-06-30

## Scope

- `OpenDocumentPackage::packageInventory()` now carries `ZipPackage::extraFieldPreflight()` into compact ODT package review.
- `OdfReader::packageProvenance()` now carries the same ZIP extra-field summary into rich import-report and document manifest provenance.
- Per-package-part metadata now includes central/local extra-field IDs, duplicate ID flags, central/local-only ID flags, value-mismatch flags, and stable `hasZipExtraFieldProvenance` booleans.
- Metadata-only package identity now includes the same per-entry extra-field fields and package-level ID usage counters.

The slice is metadata-only. It does not expose package bytes and does not invoke Pandoc, office suites, TeX, browsers, ZIP validators, or external services.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderTest.php`: 2 files, 7,676 assertions, 0 failures.
