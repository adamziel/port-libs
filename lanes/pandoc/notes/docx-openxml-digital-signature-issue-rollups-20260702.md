# DOCX/OpenXML Digital Signature Issue Rollups

Slice: `plib-7jqg4`

## Scope

DOCX/OpenXML package ingestion now summarizes digital-signature origin and signature issues by code for importer review. The rollups stay metadata-only and do not expose package bytes or attempt signature validation.

## Handoff

- `packageProvenance.digitalSignatures` now carries `issueCodeCounts`, relationship ids by issue code for origin and signature relationships, affected origin/signature parts by issue code, and external targets by issue code.
- `packageProvenance.summary` mirrors those maps under `digitalSignatureIssue*` keys for reviewer dashboards and package gates.
- The focused fixture covers missing origin parts, missing signature parts, external origin/signature targets, unexpected signature content type, and unexpected signature root diagnostics.

## Manifest

- Added `mappedDocxDigitalSignatureIssueRollupCases: 1`.
- Added `docxDigitalSignatureIssueRollupAssertions: 17`.
- Updated `benchmarkDenominator.mapped` from `2883` to `2884`.

## Validation

- `php -l lanes/pandoc/src/DocxOpenXmlReader.php`
- `php -l lanes/pandoc/tests/DocxOpenXmlDigitalSignatureIssueRollupTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlDigitalSignatureIssueRollupTest.php`
  - 1 test file, 17 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXmlDigitalSignatureIssueRollupTest.php lanes/pandoc/tests/DocxOpenXmlReaderTest.php lanes/pandoc/tests/DocxOpenXmlPackageInventoryRolesTest.php`
  - 3 test files, 12580 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/DocxOpenXml*.php`
  - 78 test files, 16985 assertions, 0 failures
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`

No external Pandoc, Office, TeX/browser, Node, zip/unzip, Jupyter, or external validator tooling was used.
