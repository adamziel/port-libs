# DOCX OpenXML ZIP Package Manifest General-Purpose Flags

## Slice

- Surface ZIP package manifest general-purpose flag aggregates through DOCX package ingestion.
- The nested `packageProvenance.zipPackage` record now carries `packageManifestGeneralPurpose*` counts and summaries.
- The top-level `packageProvenance.summary` now carries matching `zipPackageManifestGeneralPurpose*` counts and summaries.

## Fixture

- Extended the existing DOCX source ZIP general-purpose flag fixture.
- The fixture covers the ordinary UTF-8 flag bucket, a deflate-option flag bucket, and a data-descriptor flag bucket.
- Assertions verify that `ZipPackage::packageManifestPreflight()` values survive into both nested ZIP provenance and summary provenance.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
