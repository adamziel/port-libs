# Pandoc DOCX OpenXML Digital Signature Metadata

## Scope

Mapped one native DOCX/OpenXML package-ingestion case for XMLDSig package
signature parts. The slice stays metadata-only: `cryptographicValidation`
remains `false`, and no signature trust, canonicalization, digest validation, or
external package validator is invoked.

## Implementation

- `DocxOpenXmlReader` now preserves XMLDSig signature metadata for package review:
  SignedInfo reference rows, Manifest reference rows, transform algorithms,
  digest-value SHA-256 hashes, object and manifest IDs, KeyInfo clause names,
  key names, and X.509 certificate SHA-256 hashes.
- Package-level `digitalSignatures` and `packageProvenance.summary` aggregate
  reference, transform, digest-value, object, manifest, KeyInfo, and certificate
  counts for reviewer handoff.
- `DocxOpenXmlReaderTest` adds one package fixture with a digital-signature
  origin and XML signature part covering SignedInfo, Object/Manifest, KeyInfo,
  and certificate metadata.

## Accounting

- `phpPass`: `3161 -> 3162`
- `benchmarkDenominator.mapped`: `3227 -> 3228`
- `mappedDocxOpenXmlDigitalSignatureMetadataCases`: `1`
- `docxOpenXmlDigitalSignatureMetadataAssertions`: `58`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test file, 1424 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 68474 assertions, 0 failures`

Verified after refresh onto `origin/main` `c0352fcc6a`.
