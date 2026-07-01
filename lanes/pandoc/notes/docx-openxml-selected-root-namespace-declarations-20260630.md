# DOCX/OpenXML Selected Root Namespace Declarations

Slice: `plib-179ah`, DOCX/OpenXML package ingestion.

`DocxOpenXmlReader` now reports metadata-only selected XML root namespace
declaration provenance in `packageProvenance.selectedXmlParts` and mirrors it
through `packageProvenance.summary`. The slice records default versus prefixed
declaration counts, prefix and namespace URI buckets, URI byte lengths and
digests, and review rows for selected core DOCX XML roots without exposing
selected XML bytes or non-namespace root attribute values.

Accounting:
- `phpPass`: `482 -> 483`
- `phpFail`: `0`
- Focused `DocxOpenXmlReaderTest.php` assertions: `14082 -> 14130`
- `mappedDocxSelectedXmlRootNamespaceDeclarationCases = 1`
- `docxSelectedXmlRootNamespaceDeclarationAssertions = 48`

Validation:
- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `jq empty lanes/pandoc/lane-status.json lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check HEAD~1..HEAD`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed: 1 file, 14130 assertions, 0 failures.

No Pandoc, office suite, TeX/browser engine, `zip`/`unzip`, external validator,
or network-backed converter was invoked for this slice.
