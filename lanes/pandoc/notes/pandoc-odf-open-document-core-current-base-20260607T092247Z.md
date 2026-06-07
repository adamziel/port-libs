# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260607T092247Z`
Accepted base: `a2dfaf1bb6d587edaf1feffea7751db293c974ea`

Implemented bounded native ODT database-field handoff:

- `OdfReader` now treats `text:database-display`, `text:database-name`, `text:database-next`, `text:database-row-number`, and `text:database-row-select` as existing `odf-field` review spans.
- Preserves `text:database-name`, `text:table-name`, `text:table-type`, `text:column-name`, `text:row-number`, and `text:condition` as field metadata and `data-odf-field-*` WordPress attributes.
- Empty database row/source fields use metadata fallback text so Markdown and WordPress review output does not drop visible row/source provenance.
- Added focused ODF reader coverage and a WordPress database-field smoke example.

Source truth is the bounded OpenDocument Text field preservation contract already used by this lane's native ODT field handling. This patch preserves source database field metadata only; it does not attempt database connections, query execution, mail merge, or office-suite evaluation.

Red-first evidence:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result before implementation: expected failure in `maps ODT database fields into review spans`; database field contents were dropped and the command ended with `1 test files, 1440 assertions, 1 failures`.

Focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` -> `1 test files, 1464 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php lanes/pandoc/tests/OdtReaderTest.php` -> `2 test files, 1559 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-odf-database-field-handoff.php --self-test` -> `odf database field handoff self-test ok`.
- `php -l lanes/pandoc/src/OdfReader.php` -> no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderTest.php` -> no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-odf-database-field-handoff.php` -> no syntax errors.
- `git diff --check -- lanes/pandoc` -> passed with no output.

Expected lane movement:

- `phpPass`: `1484 -> 1485`.
- Mapped upstream denominator: `1902 -> 1903`.
- Focused ODF reader coverage: `1439 -> 1464` assertions, one new PASS case.
- ODF plus ODT compatibility coverage: `1534 -> 1559` assertions.

Dependency closure:

No new support component is needed. The slice reuses native `ZipPackage` ODT fixture assembly, `DOMDocument`/`DOMElement` traversal in `OdfReader`, existing AST field-span metadata, Markdown writer output, and WordPress block writer HTML handoff. Full upstream Pandoc/Haskell runner parity, database execution, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, and live-service provider tests remain out of scope.

Non-overlap:

This does not repeat accepted ODF text-tab normalization, paragraph blockquote mapping, heading auto/source identifiers, conditional/hidden text fields, chart object handoff, notes configuration, MathML/OLE object placeholders, table/list/manifest/media/link/section/index coverage, or DOCX/EPUB/XML/HTML5 DOM support work. The new surface is only bounded database-backed ODT field preservation and fallback text.

Follow-up:

Keep ODF work bounded to non-overlapping hidden paragraph/conditional section handoff, richer database-range policy metadata, index-entry layout, tab-stop position metadata, or export-side ODT writing with focused PHP tests.
