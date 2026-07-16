# pandoc-docx-numbering-relationship-content-type-current-base-20260609T223500Z

Slice: `pandoc-docx-numbering-relationship-content-type-current-base-20260609T223500Z`

This slice extends the native PHP DOCX reader's document-level numbering
relationship summary. When a numbering relationship points to an existing part
whose OPC content type is not
`application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml`,
`DocxReader` now records:

- `expectedContentType`
- `invalid-numbering-content-type` in the relationship `issues`

The reader still imports relationship-selected numbering definitions from the
target part, so WordPress review queues can keep the visible list structure
while auditing malformed package metadata. Missing target content types are
reported separately as `missing-content-type`; missing target parts remain
`missing-in-package`.

This stays bounded to metadata/reporting for relationship-selected numbering
parts. It does not invoke Pandoc, Word, LibreOffice, zip/unzip, external
validators, online services, live provider tests, or live-service provider
tests.

Verification:

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 file, 4446 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57686 assertions, 0 failures

Status delta:

- `lane-status.json` `phpPass`: `2863 -> 2864`
- `lane-status.json` suite progress: `766 -> 767`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3068 -> 3069`
- `mappedDocxNumberingRelationshipCases`: `1 -> 2`
- `docxNumberingRelationshipAssertions`: `30 -> 42`
