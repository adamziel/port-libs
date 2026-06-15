# DOCX OpenXML Content-Type Default Declaration Handoff

This slice maps one native PHP DOCX/OpenXML package ingestion case after rebase
onto current main `7d8fbf7009`.

`DocxOpenXmlReader` now exposes content-type default declaration usage in
package provenance:

- declared default count, used/unused defaults, and unused extensions;
- default-resolved versus override-resolved package part counts;
- per-default package parts, byte totals, relationship-part counts, and MIME
  parameter metadata;
- missing default extensions and extensionless missing-content-type diagnostics.

The focused fixture covers a DOCX package with a used `.bin` default, an unused
declared default, a missing `.payload` default, and an extensionless package
part. The implementation stays under `lanes/pandoc` and does not invoke
Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers, external
validators, online services, live provider tests, or live-service provider
tests.

Accounting:

- `phpPass`: 3706 -> 3707
- `phpFail`: 0
- mapped upstream manifest cases: 3730 -> 3731
- `mappedDocxOpenXmlContentTypeDefaultDeclarationCases`: 1
- `docxOpenXmlContentTypeDefaultDeclarationAssertions`: 38

Verification:

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - 1 file, 2928 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 files, 87813 assertions, 0 failures
