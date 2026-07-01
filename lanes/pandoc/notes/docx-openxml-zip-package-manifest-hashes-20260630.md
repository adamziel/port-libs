# DOCX OpenXML ZIP Package Manifest Hashes - 2026-06-30

Slice: `plib-r63bb`, DOCX/OpenXML package ingestion core blocker.

## Summary

`DocxOpenXmlReader` now carries the native `ZipPackage::packageManifestPreflight()`
result through DOCX package provenance. Source ZIP entries expose metadata-only
package manifest evidence for:

- local header lengths and SHA-256 hashes;
- compressed payload byte offsets, ends, and SHA-256 hashes;
- central-directory record offsets, ends, and SHA-256 hashes;
- package manifest version, manifest SHA-256, and hash coverage counts in the
  DOCX package summary.

Loaded DOCX part inventory mirrors the same source ZIP hash/offset fields, while
uncompressed part digests remain separate. No DOCX package bytes are exposed by
this slice.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - Result: `1 test files, 9984 assertions, 0 failures`

## Accounting

- `lane-status.json` `phpPass`: `469 -> 470`
- Direct-format parity remains active; this is a bounded DOCX package-ingestion
  provenance slice, not a full direct DOCX reader parity claim.

No Pandoc, office suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node
tooling, live services, or external validators were invoked.
