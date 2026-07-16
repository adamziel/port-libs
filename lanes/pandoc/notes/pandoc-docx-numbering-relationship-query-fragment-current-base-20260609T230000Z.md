# pandoc-docx-numbering-relationship-query-fragment-current-base-20260609T230000Z

Slice: `pandoc-docx-numbering-relationship-query-fragment-current-base-20260609T230000Z`

This slice extends the native PHP DOCX reader's document-level numbering
relationship summary for relationship targets that include URI query or
fragment suffixes. `DocxReader` now records `targetQuery` and `targetFragment`
in `metadata.docxNumbering.relationship` and `importReport.numbering.relationship`
while continuing to load numbering definitions from the path-only package part.

The regression fixture points the numbering relationship at
`lists/review-numbering.xml?review=ready#numbering-defs` and stores the actual
package part at `word/lists/review-numbering.xml`. The reader preserves the raw
target and resolved target with suffix metadata, resolves `targetPart` to the
path-only part, and still imports the selected list definitions into native AST
and WordPress list output.

This stays bounded to DOCX numbering relationship provenance. It does not invoke
Pandoc, Word, LibreOffice, zip/unzip, external validators, online services,
live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 file, 4464 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 files, 57945 assertions, 0 failures

Status delta:

- `lane-status.json` `phpPass`: `2877 -> 2878`
- `lane-status.json` suite progress: `780 -> 781`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3081 -> 3082`
- `mappedDocxNumberingRelationshipCases`: `2 -> 3`
- `docxNumberingRelationshipAssertions`: `42 -> 60`
