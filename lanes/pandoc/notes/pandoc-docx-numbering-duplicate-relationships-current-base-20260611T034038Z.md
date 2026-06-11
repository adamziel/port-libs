# pandoc-docx-numbering-duplicate-relationships-current-base-20260611T034038Z

Slice: `pandoc-docx-numbering-duplicate-relationships-current-base-20260611T034038Z`

This slice extends the native PHP DOCX reader's document-level numbering
relationship report so `metadata.docxNumbering` and `importReport.numbering`
enumerate every numbering relationship from `word/_rels/document.xml.rels`.
The first relationship remains the primary relationship used to load numbering
definitions, while duplicate or alternate relationships are reported as inert
review metadata.

Duplicate document-level numbering relationships now preserve target,
TargetMode, resolved target, query/fragment suffixes, package-part existence,
content type, external-target preflight fields, and relationship issue lists.
Missing alternate numbering targets surface as `missing-in-package` without
abandoning valid primary list definitions.

This stays bounded to DOCX numbering relationship provenance. It does not invoke
Pandoc, Word, LibreOffice, zip/unzip, browser renderers, external validators,
online services, live provider tests, or live-service provider tests.

Verification:

- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - `1 test files, 4867 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 62162 assertions, 0 failures`

Status delta:

- `lane-status.json` `phpPass`: `3044 -> 3045`
- `lane-status.json` suite progress: `942 -> 943`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `3180 -> 3181`
- `mappedDocxNumberingRelationshipCases`: `4 -> 5`
- `docxNumberingRelationshipAssertions`: `95 -> 125`
