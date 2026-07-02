# DOCX ZIP Source-Record Local Header Offset Buckets

Date: 2026-07-02
Bead: plib-2qcgd

## Slice

DOCX/OpenXML package provenance now groups loaded ZIP source-record local header offsets into metadata-only buckets:

- `start-of-archive`
- `1-to-255-bytes`
- `256-to-1023-bytes`
- `1024-plus-bytes`
- `unknown`

The bucket rollups are exposed through `packageProvenance.summary`, mirrored into `packageIdentity`, and attached to per-part identity entries as `zipSourceRecordLocalHeaderOffsetBucket` metadata. The summaries reuse the existing ZIP source-record byte accounting and do not expose package payload bytes.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordLocalHeaderOffsetBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordLocalHeaderOffsetBucketsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordLocalHeaderOffsetBucketsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordExpansionRatioBucketsTest.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordCompressionMethodsTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`

No external Pandoc, office suite, ZIP tools, validators, or live services were invoked.
