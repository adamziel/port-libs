# DOCX OpenXML Package Part Raw Extensions

Slice: `plib-3zezk`

DOCX/OpenXML package ingestion now exposes metadata-only raw package part
extension buckets without normalizing case away.

## Handoff

- `packageProvenance.summary.partRawExtension*` reports exact raw extension
  counts, entry-name lookups, extensionless package part totals, uppercase
  counts, normalization counts, and per-bucket metadata rows.
- `packageProvenance.packageIdentity` mirrors the raw extension buckets and now
  includes `partExtension`, `rawPartExtension`,
  `partExtensionHasUppercase`, `partExtensionWasNormalized`, and
  `extensionlessPackagePart` on package identity entries.
- Existing normalized `partExtensions` and ZIP source-record extension summaries
  are unchanged; the new bucket is for provenance review where exact casing is
  significant.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlPackagePartRawExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartRawExtensionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlPackagePartRawExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordPackagePartExtensionsTest.php lanes/pandoc/tests/DocxOpenXmlContentTypeDefaultExtensionUsageTest.php lanes/pandoc/tests/DocxOpenXmlPackageIdentityTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
