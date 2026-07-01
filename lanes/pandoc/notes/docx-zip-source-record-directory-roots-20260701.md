# DOCX ZIP Source Record Directory Roots

Slice: `plib-ztkzf`

## What Changed

- Added DOCX package provenance rollups for loaded ZIP source-record byte spans grouped by ZIP directory root.
- Exposes compact summary counters:
  - `partZipSourceRecordDirectoryRootCount`
  - `partZipSourceRecordDirectoryRootCounts`
  - `partZipSourceRecordDirectoryRootBytes`
  - `partZipSourceRecordPartCount`
  - `partZipSourceRecordByteLength`
  - `partZipSourceRecordLocalRecordByteLength`
  - `partZipSourceRecordCentralDirectoryRecordByteLength`
- Exposes rich `partZipSourceRecordDirectoryRoots` buckets with local header, compressed data, central directory, content type, compression method, role, and largest-part metadata.

## Parity Accounting

- Direct-format scope: native PHP DOCX/OpenXML package ingestion.
- No shell-outs to Pandoc, office suites, unzip/zip, Node tooling, or external validators.
- Byte exposure remains metadata-only; source-record summaries carry offsets, lengths, hashes, and counts, not raw ZIP bytes.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
