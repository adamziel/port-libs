# DOCX/OpenXML mail merge external-target policy

Implemented a bounded DOCX/OpenXML package-ingestion slice for mail-merge source relationships. `DocxOpenXmlReader` now rolls up safe versus unsafe external targets from `w:mailMerge` data-source and header-source relationships into both the settings `mailMerge` metadata and `packageProvenance.summary`.

The metadata includes internal/external relationship counts, allowed and unsafe external-target counts, external and unsafe target lists, target kind/scheme buckets, and external-target issue codes. External data sources remain metadata-only and are not fetched, opened, or exposed as document media.

Validation:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` passed with 1 file, 10,453 assertions, and 0 failures.
