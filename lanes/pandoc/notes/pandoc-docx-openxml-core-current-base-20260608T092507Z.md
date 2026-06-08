# DOCX OpenXML Core Current Base: Omitted Table Row Grid Columns

Micro-slice: `pandoc-docx-openxml-core-current-base-20260608T092507Z`

Accepted base: `76dc0ae478cf17b9d4471313469197e6c70ed1d9`

## Behavior

This slice maps one bounded DOCX/OpenXML table-row behavior into the native PHP DOCX reader: `w:trPr/w:gridBefore`, `w:gridAfter`, `w:wBefore`, and `w:wAfter`.

Rows that omit leading or trailing table grid columns now materialize empty `table_cell` AST nodes carrying safe reviewer metadata. This keeps the table geometry, Markdown pipe-table handoff, and WordPress block output aligned with the source WordprocessingML grid without shelling out to Pandoc or office tools.

## Non-overlap

This does not repeat accepted DOCX/OpenXML coverage for run language metadata, embedded OLE/package placeholders, tracked formatting changes, deleted OMML math revisions, paragraph borders, SDT form controls, table grid column widths, table cell preferred width, margins, borders, shading, vertical alignment, or table row repeat-header/cant-split/height metadata.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2429 assertions, 0 failures`.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` failed as expected with `1 test files, 2432 assertions, 1 failures`; the new omitted-grid row had `2` cells instead of the expected `4`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 2482 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test` passed with `docx body handoff self-test ok`.
- Syntax checks passed for:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- Whitespace check `git diff --check -- lanes/pandoc` passed.

Focused assertion delta: `+53`.

Lane counter delta: `phpPass` `1593 -> 1594`; manifest mapped denominator `2013 -> 2014`; `mappedDocxOpenXmlCoreCases` `33 -> 34`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses:

- `DocxReader` WordprocessingML DOM parsing.
- `ZipPackage` in-memory OPC fixtures.
- `TableGeometry` review-packet metadata.
- Existing Markdown and WordPress writers for table handoff output.

Full upstream DOCX parity remains gated on broader OpenXML behavior and a hydrated Pandoc runner, but this slice intentionally did not run Pandoc, Word, LibreOffice, `zip`/`unzip`, Cabal, Haskell runners, external office tools, online services, live provider tests, or live-service provider tests.

## Next Task

Choose a non-overlapping DOCX/OpenXML body/properties/styles/numbering/media package parsing gap with focused native PHP assertions. Avoid repeating the omitted-grid row path added here.
