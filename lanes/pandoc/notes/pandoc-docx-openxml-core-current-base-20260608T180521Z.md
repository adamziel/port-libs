# pandoc-docx-openxml-core-current-base-20260608T180521Z

Accepted base: `1d10c26783e331f072073a9dc0eef297e722aedb`

Scope:
- Implemented a bounded native DOCX/OpenXML data/document field metadata handoff.
- `MERGEFIELD`, `DOCVARIABLE`, and `DOCPROPERTY` displayed results now remain visible while carrying inert span metadata for the normalized field instruction, field type, field name, format switch, and `MERGEFIELD` before/after text switches.
- Both `w:fldSimple` and complex `w:fldChar`/`w:instrText` result ranges use the existing field result wrapper path.

Red-first evidence:
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
- Result before production change: `1 test files, 2763 assertions, 1 failures`
- Failure was expected: the new data-field fixture collapsed to plain text instead of emitting metadata spans.

Final focused evidence:
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
- Result after production change: `1 test files, 2807 assertions, 0 failures`
- New focused assertion delta versus the previous passing shape: `+46`.
- `php lanes/pandoc/examples/wordpress-docx-data-field-handoff.php --self-test`
- Result: `wordpress-docx-data-field-handoff self-test passed`

Dependency closure:
- No new native PHP support component is needed.
- This reuses the existing `DocxReader` field instruction tokenizer, simple/complex field result wrapper path, `ZipPackage` fixtures, `MarkdownWriter`, and `WordPressBlockWriter`.

Exclusions:
- No Pandoc, Word, LibreOffice, zip/unzip, Cabal solver/build/test command, Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.
- Field evaluation, mail merge execution, and document property recalculation remain out of scope.

Suggested follow-up:
- Pick a non-overlapping DOCX/OpenXML field or package gap such as additional safe field-code metadata, DrawingML text extraction beyond existing text boxes, or media relationship provenance.
