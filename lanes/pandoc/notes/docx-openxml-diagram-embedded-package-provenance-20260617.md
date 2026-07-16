# DOCX OpenXML Diagram Embedded Package Provenance

Slice: `plib-3prk5`, DOCX/OpenXML package ingestion.

## Behavior

- `DocxOpenXmlReader` now records package relationships declared by SmartArt
  diagram part relationship sidecars.
- Diagram package summaries distinguish referenced and unreferenced package
  relationship IDs, existing/missing/external targets, target suffixes,
  content-type provenance, byte counts, CRC32, and SHA-256 digests.
- Embedded diagram package bytes remain metadata-only and are not exposed as
  document media.

This is native PHP package provenance only. It does not invoke Pandoc, Word,
LibreOffice, office suites, zip/unzip, external zip tools, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

## Accounting

- `phpPass`: `17029 -> 17030`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `16615 -> 16616`
- Root mapped inventory: `16584 -> 16585`
- Benchmark denominator mapped cases: `3753 -> 3754`
- `mappedDocxOpenXmlDiagramEmbeddedPackageCases = 1`
- `docxOpenXmlDiagramEmbeddedPackageAssertions = 58`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1` file, `4437` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258` files, `176205` assertions, `0` failures
