# ODF signature KeyInfo provenance

Date: 2026-07-01
Slice: `plib-l8xvy`

ODF/ODT package ingestion now preserves XML Signature `KeyInfo` review
metadata from `META-INF/*signatures.xml` sidecars through `OdfReader`
`signatureMetadata`.

- Per-signature metadata records KeyInfo child element names and counts,
  KeyName lengths and SHA-256 hashes, and X.509 data, certificate, subject,
  issuer, and serial counts.
- Per-signature-part and package-level signature summaries aggregate the same
  KeyInfo and X.509 counters for package review.

This remains metadata-only provenance. Raw KeyName, certificate, subject,
issuer, serial, signature, and digest values are not exposed, and the reader
does not validate signatures or invoke Pandoc, office suites, TeX/browser
engines, `zip`/`unzip`, Node tooling, Jupyter, live services, or external
validators.

Validation:

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderSignatureKeyInfoProvenanceTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderSignatureKeyInfoProvenanceTest.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php`
  - 2 files, 71 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdfReaderSignatureKeyInfoProvenanceTest.php lanes/pandoc/tests/OdfReaderSignatureTransformProvenanceTest.php lanes/pandoc/tests/OdfReaderSignaturePackageBytePolicyTest.php`
  - 4 files, 5,317 assertions, 0 failures

Direct-format parity remains active in lane status.
