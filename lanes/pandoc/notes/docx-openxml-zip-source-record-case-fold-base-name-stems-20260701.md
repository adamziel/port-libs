# DOCX OpenXML ZIP source-record case-fold base-name stems

## Slice

- `DocxOpenXmlReader` now derives `packageProvenance.summary.partZipSourceRecordPackagePartCaseFoldBaseNameStem*` from the loaded ZIP source-record package-part base-name stem inventory.
- The detailed `partZipSourceRecordPackagePartCaseFoldBaseNameStems` list preserves folded stem keys, exact stem variants, exact base-name variants, extension/content-type/compression/role rollups, source-record byte totals, local/central-directory byte totals, duplicate folded-stem buckets, and largest source-record part metadata.
- `packageProvenance.packageIdentity` mirrors the folded source-record stem counts, byte maps, duplicate buckets, and detailed summaries for compact package-review handoff.

## Parity accounting

- `UPSTREAM_TEST_MANIFEST.json` records `mappedDocxZipSourceRecordPackagePartCaseFoldBaseNameStemCases: 1` and `docxZipSourceRecordPackagePartCaseFoldBaseNameStemAssertions: 47`.
- This keeps DOCX/OpenXML package ingestion source-record path review aligned with existing case-folded package-part and relationship stem summaries without exposing package bytes.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartCaseFoldBaseNameStemsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartCaseFoldBaseNameStemsTest.php` with 1 file, 47 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartCaseFoldBaseNameStemsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartBaseNameStemsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartRawExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordExpansionRatioBucketsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` with 6 files, 12,741 assertions, 0 failures.

No external Pandoc, office suite, TeX/browser engine, Jupyter, Node, zip/unzip, validators, or live services were invoked.
