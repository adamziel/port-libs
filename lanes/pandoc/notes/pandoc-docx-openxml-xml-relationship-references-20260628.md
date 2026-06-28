# DOCX/OpenXML XML Relationship References

Slice: `pandoc-docx-openxml-xml-relationship-references-20260628`

## What Changed

- Added metadata-only XML relationship-reference provenance to `DocxOpenXmlReader`.
- XML-inspectable DOCX package parts now report relationship-namespace attributes
  (`r:id`, `r:embed`, `r:link`) through `packageProvenance.xmlRelationshipReferences`
  and `packageProvenance.summary`.
- The provenance resolves each reference through the source part's OPC `.rels`
  sidecar and records resolved/unresolved IDs, relationship sidecar names,
  internal/external target state, target parts, content types, query/fragment
  metadata, and issue codes.
- XML text, raw relationship target payloads, and package bytes remain unexposed.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  passed with `1 test files, 11977 assertions, 0 failures`.

## Accounting

- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2311 -> 2312`.
- Added `mappedDocxPackageXmlRelationshipReferenceCases = 1`.
- Added `docxPackageXmlRelationshipReferenceAssertions = 39`.

No Pandoc, office suite, `zip`/`unzip`, browser, external validator, network
fetch, or external converter was invoked.
