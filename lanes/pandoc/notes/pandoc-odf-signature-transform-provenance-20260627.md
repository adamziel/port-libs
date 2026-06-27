# Pandoc ODF Signature Transform Provenance

Slice: `plib-51dc5`
Date: 2026-06-27 UTC
Area: Pandoc ODF/ODT OpenDocument package ingestion

## Behavior

`OdfReader` now preserves metadata-only XMLDSig transform provenance for ODF
signature references. Each reference keeps the existing transform algorithm list
and also exposes structured transform rows with XPath filter expression counts
and normalized XPath expressions where present.

The signature metadata summaries now include aggregate transform counts, XPath
transform counts, and XPath expression counts at reference, signature-part, and
package levels. Package part bytes, signed payload bytes, and signature
validation remain out of scope for this bounded ingestion slice.

No Pandoc binary, office suite, zip/unzip CLI, browser renderer, external
validator, online service, live provider test, or live-service provider test was
invoked.

## Accounting

- Focused PHP behavior coverage: `+1` ODF/ODT package-ingestion test case.
- Focused assertions: `+27`.
- Note-only `phpPass` movement for this slice: `445 -> 446`.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php`:
  1 test file, 27 assertions, 0 failures.
