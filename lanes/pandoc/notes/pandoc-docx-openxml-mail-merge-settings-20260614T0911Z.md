# DOCX OpenXML Mail Merge Settings Package Provenance

Hook: `plib-5jd43`, Pandoc DOCX OpenXML package ingestion core blocker slice.
Rebased for submission onto current main `47bbdbc61f`.

Implemented a bounded native PHP `DocxOpenXmlReader` mail-merge settings package
handoff. Relationship-selected settings parts now preserve `w:mailMerge`
metadata, redact raw connection strings to length and SHA-256, preflight
external data-source relationships, preserve internal header-source
query/fragment/content-type/byte/hash provenance, propagate package summary
counts, and assign semantic package inventory roles for mail-merge source
relationships.

No Pandoc, Word, LibreOffice, office suite, zip/unzip, ZipArchive, browser
renderer, external validator, online service, live provider test, or
live-service provider test was run.

Verification:

```bash
php -l lanes/pandoc/src/DocxOpenXmlReader.php
php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php
php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php
php tools/run-tests.php lanes/pandoc/tests
```

Result: focused `DocxOpenXmlReaderTest.php` passed `1 test files, 2788
assertions, 0 failures`; full `lanes/pandoc/tests` passed `46 test files,
82417 assertions, 0 failures`.

Lane accounting:

- `phpPass`: `3500 -> 3501`
- `phpFail`: `0`
- `mappedDocxOpenXmlMailMergeSettingsCases`: `1`
- `docxOpenXmlMailMergeSettingsAssertions`: `52`
