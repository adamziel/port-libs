# ZIP package byte layout source hashes

Hook: `plib-b8uz8`, Pandoc shared ZIP/OPC package core blocker slice.

## Summary

- `ZipPackage::packageByteLayoutPreflight()` now carries SHA-256 provenance for the
  exact ZIP layout regions it already accounts for:
  - full archive bytes;
  - package prefix bytes when present;
  - local header/payload region;
  - declared central directory region;
  - central-directory-to-EOCD gap bytes, including unverified central-directory
    signature records;
  - EOCD fixed header, package comment, and full EOCD record bytes.
- Raw strict import and instantiated strict import inherit the same layout hash
  packet through their existing `packageByteLayout` summaries.
- The change remains metadata-only: no package payload bytes are exposed, no entry
  contents are inflated for these hashes, and no external ZIP tools or validators
  are invoked.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 4,904 assertions, 0 failures

Direct-format parity remains active in lane status; this slice only closes shared
ZIP/OPC package provenance needed by DOCX, EPUB, and ODF/ODT package readers.
