# ZIP64 EOCD field resolution plan

Work item: `plib-133ou`

## Summary

`ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight()` now exposes a
field-level EOCD-to-ZIP64 resolution plan before bounded package import. The
plan records each classic EOCD accounting field, its sentinel value, the ZIP64
record value when present, whether the field is sentinel-backed, mirrored from
classic EOCD, mismatched, or missing its ZIP64 record.

This remains a preflight-only ZIP/OPC package primitive. ZIP64 packages are
still rejected by the bounded reader; the new metadata makes the rejection
actionable for DOCX, EPUB, ODT, and OPC import gates without reading package
payload bytes or invoking external archive tools.

## Non-overlap

This slice does not add ZIP64 package import, central-directory repair, ZIP64
local-header compatibility, data-descriptor handling, split archive support,
or package manifest path/CRC/basename rollups. It only extends existing ZIP64
EOCD accounting with deterministic field-resolution metadata.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`

No Pandoc binary, office suite, TeX/browser engine, unzip/zip command, Node
tooling, external validator, or live service was invoked.
