# pandoc-docx-openxml-missing-content-type-package-parts-current-base-20260611T182925Z

Slice: `plib-c8l3c`, DOCX OpenXML package ingestion core blocker.

Base: `origin/main` `9b98274d9`.

## Scope

DOCX package provenance already tracked each part's content-type source and each
relationship target's resolved part. This slice adds review-ready summaries for
package parts and relationship targets that exist in the ZIP package but have no
matching `[Content_Types].xml` default or override declaration.

## Change

`DocxOpenXmlReader` now records:

- `missingContentTypePartCount`
- `relationshipTargetMissingContentTypeCount`
- `relationshipPartsWithMissingContentTypes`
- `partsWithoutContentType`
- `relationshipTargetsWithoutContentType`

The summary rows preserve part names, byte counts, default extensions, package
roles, relationship source and target suffix provenance, and missing
content-type source markers without exposing external tools or Office behavior.

## Verification

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php`
  - `1 test files, 786 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 65390 assertions, 0 failures`

No Pandoc, Word, LibreOffice, office suite, zip/unzip, browser renderer,
external validator, online service, live provider test, or live-service
provider test was executed.
