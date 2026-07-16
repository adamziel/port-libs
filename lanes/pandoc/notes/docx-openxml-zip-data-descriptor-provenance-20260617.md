# DOCX OpenXML ZIP Data Descriptor Provenance

Bead: plib-7rkwx
Date: 2026-06-17
Base: c8e7b93d15

## Scope

This slice keeps DOCX/OpenXML package ingestion native and bounded while preserving ZIP data descriptor provenance from `ZipPackage` into `DocxOpenXmlReader` package metadata.

## Change

- `DocxOpenXmlReader` now carries `ZipPackage::dataDescriptorPreflight()` into `packageProvenance.zipPackage.dataDescriptors`.
- Per-entry ZIP provenance and package part inventory now include data descriptor flags, offsets, lengths, CRC hex, local-header placeholder state, matched-central-directory state, and issue lists.
- Package summary now rolls up signed and unsigned data descriptor counts, matched descriptor counts, issue codes, and descriptor byte length.
- `DocxOpenXmlReaderTest` adds one focused DOCX fixture with signed and unsigned data descriptors and no external zip/unzip or office tooling.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> 1 file, 5361 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests` -> 258 files, 177129 assertions, 0 failures
