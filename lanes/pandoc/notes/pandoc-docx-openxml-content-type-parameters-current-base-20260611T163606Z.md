# DOCX OpenXML content-type parameter provenance

Slice: `plib-ubbxn`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `446b499acc9f346b3816ee329ba01075bab773fd`.

## Scope

`DocxOpenXmlReader` already preserved raw OPC `[Content_Types].xml`
content-type declarations. This slice adds reviewer-visible MIME parameter
provenance without changing those raw values or invoking external tools.

## Change

- Default and override content-type declarations now expose base content type,
  parameter count, ordered parameters, and a parameter map.
- Relationship summaries, package relationship inventories, and package part
  inventory entries carry the same raw/base/parameter fields from their resolved
  content-type declarations.
- Parameterized image defaults and document overrides remain loadable through the
  existing native DOCX OpenXML path.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php` -> `1 test files, 545 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 63959 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suites, zip/unzip, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were run.

## Accounting

- `phpPass` moves `3071 -> 3072` after rebasing over XML/HTML5 DOM hyperlink metadata.
- Mapped denominator moves `3193 -> 3194`.
- Added `mappedDocxOpenXmlContentTypeParameterCases: 1`.
- Added `docxOpenXmlContentTypeParameterAssertions: 30`.
