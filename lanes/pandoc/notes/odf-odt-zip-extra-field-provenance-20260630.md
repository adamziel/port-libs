# ODF/ODT ZIP Extra Field Provenance

2026-06-30 plib-0rouj adds metadata-only ZIP extra-field provenance to ODF/ODT package ingestion.

## Scope

- `OpenDocumentPackage` now carries `ZipPackage::extraFieldPreflight()` through compact `packageInventory`.
- `OdfReader` now carries the same summary through rich `packageProvenance`.
- Package parts now expose central/local extra-field IDs, duplicate ID flags, central/local mismatch flags, and value-mismatch flags without exposing extra-field bytes.
- Package identity inputs include the extra-field summary counters and per-entry normalized extra-field provenance.

## Validation

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php` passed with 1,927 assertions and 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with 5,098 assertions and 0 failures.

No Pandoc, office suite, ZIP CLI, browser, TeX, Node, or external validator was invoked.
