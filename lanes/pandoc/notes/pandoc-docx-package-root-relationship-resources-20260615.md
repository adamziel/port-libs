# DOCX/OpenXML Package Root Relationship Resources

Slice: `plib-95q0o`, DOCX OpenXML package ingestion.

This slice adds metadata-only provenance for non-standard package-root
relationships in `DocxOpenXmlReader`. Dedicated package-root surfaces such as
the office document, core/custom properties, thumbnails, and digital signatures
remain on their existing reports; remaining package-root relationships now
surface as `docx.packageRootRelationshipResources` and
`docx.packageProvenance.packageRootRelationshipResources`.

The report preserves relationship ids, target query/fragment suffixes,
existing/missing/external counts, sidecar relationship flags, content-type
parameter provenance, byte lengths, CRC32, SHA-256, and summary issue codes.
Bytes remain blocked from document media handoff through
`package-root-relationship-bytes-blocked` and
`package-root-relationship-metadata-only` review policy metadata.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 2953 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 88904 assertions, 0 failures

No Pandoc, Word, LibreOffice, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was invoked.
