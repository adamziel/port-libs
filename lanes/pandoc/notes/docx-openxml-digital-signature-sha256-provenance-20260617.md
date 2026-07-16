# DOCX OpenXML Digital Signature SHA-256 Provenance

Slice: `plib-48c28`, DOCX/OpenXML package ingestion.

## Behavior

- `DocxOpenXmlReader` carries already-landed per-item SHA-256 digests for
  existing digital signature origin package parts and XML signature package
  parts into aggregate package provenance.
- Digital signature package summaries now expose origin/signature SHA-256
  counts and hash lists while missing and external signature relationships
  keep null digests.
- Signature origin and XML signature bytes remain metadata-only and are not
  exposed as document media.

This is native PHP package provenance only. It does not invoke Pandoc,
cmark/commonmark runners, Cabal/Haskell runners, Word, LibreOffice, office
suites, zip/unzip, TeX/PDF engines, browser renderers, Node tooling, external
validators, online services, live provider tests, or live-service provider
tests.

## Accounting

- `phpPass`: `17042 -> 17043`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `16628 -> 16629`
- Root mapped inventory: `16597 -> 16598`
- Benchmark denominator mapped cases: `3766 -> 3767`
- `mappedDocxOpenXmlDigitalSignatureSha256Cases = 2`
- `docxOpenXmlDigitalSignatureSha256Assertions = 31`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1` file, `4797` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258` files, `176565` assertions, `0` failures
