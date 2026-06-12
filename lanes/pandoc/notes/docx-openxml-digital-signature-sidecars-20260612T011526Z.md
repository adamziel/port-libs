# DOCX OpenXML Digital Signature Sidecars

Slice: `plib-4ojej`, DOCX OpenXML package ingestion core blocker.
Base: current main `b3f2aa2a05`.

## Change

`DocxReader` now surfaces OPC package digital-signature sidecars as
metadata-only DOCX provenance. Package-root
`digital-signature/origin` relationships are reported in document metadata and
import reports, and origin-local `digital-signature/signature` relationships
carry target query/fragment suffixes, content types, ZIP byte/CRC metadata,
external/missing target diagnostics, XMLDSig root/reference algorithm summaries,
and `cryptographicValidation=false`.

The reader reuses the shared OPC relationship graph preflight and does not
perform cryptographic signature validation or expose signature sidecars as
document media/content.

No Pandoc, Word, LibreOffice, office suites, zip/unzip, XMLDSig validators,
browser renderers, external validators, online services, live provider tests,
or live-service provider tests are executed.

## Verification

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  passed: 1 test file, 5095 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 44 test files, 68315 assertions, 0 failures.

## Movement

- `lanes/pandoc/lane-status.json` `phpPass`: `3159 -> 3160`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3225 -> 3226`.
- Added one focused `DocxReaderTest` case with 118 assertions for digital
  signature sidecar provenance.
