# DOCX numbering relationship provenance

Slice: `pandoc-docx-numbering-relationship-provenance`

Implemented a bounded native PHP DOCX reader slice for document-level numbering
relationship provenance. `DocxReader` now records the numbering relationship
id, source part, relationships part, raw target, resolved target part, existence,
content type, issues, and compact definition/level counts in both
`metadata.docxNumbering` and `importReport.numbering`.

The regression fixture omits the conventional `word/numbering.xml` and points
the document numbering relationship at `word/lists/review-numbering.xml`, so the
case verifies that relationship-selected list definitions still drive AST list
construction while reviewer reports retain the source/target relationship
metadata.

Verification:
- `php -l lanes/pandoc/src/DocxReader.php`
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - 1 test file, 4434 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 42 test files, 57359 assertions, 0 failures.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
zip/unzip, external validator, online service, live provider test, or
live-service provider test was executed.
