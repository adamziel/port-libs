# pandoc-docx-numbering-external-relationship-current-base-20260610T103129Z

Slice: `pandoc-docx-numbering-external-relationship-current-base-20260610T103129Z`

This slice extends the native PHP DOCX reader's document-level numbering
relationship report for `TargetMode="External"` numbering relationships.
`DocxReader` now records the external target kind, scheme, allowed preflight
status, and query/fragment suffixes in `metadata.docxNumbering.relationship`
and `importReport.numbering.relationship`.

External numbering relationships are reported as
`external-numbering-relationship` and are not loaded as package parts, so review
queues can see the relationship provenance without fetching remote list
definitions. Paragraphs with `numPr` still use the reader's bounded default list
fallback when no local numbering definitions are available.

This stays bounded to DOCX numbering relationship provenance. It does not invoke
Pandoc, Word, LibreOffice, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 4837 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 62132 assertions, 0 failures`

Status delta:

- `lane-status.json` `phpPass`: `3043 -> 3044`
- `lane-status.json` suite progress: `941 -> 942`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3179 -> 3180`
- `mappedDocxNumberingRelationshipCases`: `3 -> 4`
- `docxNumberingRelationshipAssertions`: `60 -> 95`
