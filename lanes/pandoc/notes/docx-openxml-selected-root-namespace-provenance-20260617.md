# DOCX OpenXML Selected Root Namespace Provenance

Slice: `plib-1klm4`, DOCX/OpenXML package ingestion.

## Behavior

- `DocxOpenXmlReader` now records selected XML part root qualified names,
  prefixes, root non-namespace attribute counts, and root namespace declaration
  counts/prefixes in `packageProvenance.selectedXmlParts`.
- Package summary now rolls up selected XML root prefix, attribute, namespace
  declaration, and namespace-prefix metadata.
- Missing or invalid optional selected parts remain metadata-only with null root
  qualified-name/prefix fields and zero root declaration counts.

This is native PHP package provenance only. It does not invoke Pandoc, Word,
LibreOffice, office suites, zip/unzip, ZipArchive, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

## Accounting

- `phpPass`: `17024 -> 17025`
- `phpFail`: `0`
- `UPSTREAM_TEST_MANIFEST.json` mapped cases: `16610 -> 16611`
- Root mapped inventory: `16579 -> 16580`
- Benchmark denominator mapped cases: `3748 -> 3749`
- `mappedDocxOpenXmlSelectedRootNamespaceCases = 1`
- `docxOpenXmlSelectedRootNamespaceAssertions = 31`

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1` file, `4160` assertions, `0` failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - `258` files, `175928` assertions, `0` failures
