# DOCX ZIP Source Record Role Buckets

Slice: `plib-mke69`

## What Changed

- Added DOCX package provenance rollups for loaded ZIP source-record byte spans grouped by package inventory role.
- Exposes compact summary counters:
  - `partZipSourceRecordRoleCount`
  - `partZipSourceRecordRoleCounts`
  - `partZipSourceRecordRoleBytes`
  - `partZipSourceRecordRoleOccurrenceCount`
  - `partZipSourceRecordRoleDataDescriptorOccurrenceCount`
  - `partZipSourceRecordRoleIssueOccurrenceCount`
- Exposes rich `partZipSourceRecordRoles` buckets with local header, compressed data, central directory, content type, directory root, compression method, and largest-part metadata.

## Parity Accounting

- Direct-format scope: native PHP DOCX/OpenXML package ingestion.
- No shell-outs to Pandoc, office suites, unzip/zip, Node tooling, or external validators.
- Byte exposure remains metadata-only; role summaries carry lengths, hashes, and counts, not raw ZIP bytes.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordRolesTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlZipSourceRecordDirectoryRootsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
