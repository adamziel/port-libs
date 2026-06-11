# pandoc-docx-custom-xml-data-store-package-20260611T181603Z

Slice: `plib-sixjz` DOCX/OpenXML package ingestion core blocker.

`DocxOpenXmlReader` now exposes DOCX package-root custom XML data store item
provenance as inert reviewer metadata. The handoff includes:

- root `customXml` relationship target/query/fragment provenance;
- target part content-type, byte, root element, namespace, and text preview
  metadata;
- item-local `customXmlProps` relationship summaries;
- `itemProps` store item IDs and schema refs;
- duplicate store item ID, external store, missing properties relationship, and
  missing store ID diagnostics;
- package-level summary counts in `packageProvenance`.

This stays bounded to DOCX/OpenXML package ingestion. It does not bind content
controls, alter visible document output, expose custom XML bytes as media, or
invoke Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests.

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 796 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 65034 assertions, 0 failures

Metric:

- `phpPass`: `3091 -> 3092`
- `phpFail`: `0`
- `mappedDocxOpenXmlCustomXmlDataStoreCases`: `1`
- `docxOpenXmlCustomXmlDataStoreAssertions`: `40`
