# Pandoc ODF OpenDocument Core Current Base

Slice: `pandoc-odf-open-document-core-current-base-20260606T122708Z`
Base accepted HEAD: `f15cbc9106adbb92bb890518a310c78c306e1f13`

## Behavior

- `OdfReader` now maps `text:conditional-text` and `text:hidden-text` into the existing `odf-field` span handoff.
- The field span preserves visible/fallback text plus `condition`, `stringValue`, `stringValueIfTrue`, and `stringValueIfFalse` metadata when present.
- Markdown and WordPress output reuse the existing field metadata writer path, so review spans now expose the ODF condition and branch fallback values without evaluating office-suite logic.

## Source Truth

The bounded source contract is OpenDocument XML field preservation for ODT reader handoff. The slice maps the ODT field attributes that are present in `content.xml` without shelling out to Pandoc, LibreOffice, Word, `zip`/`unzip`, or any external converter.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1227 assertions, 0 failures`.
- Red-first: the new conditional/hidden field case failed with `1 test files, 1228 assertions, 1 failures`; paragraph text dropped the field values before implementation.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php` passed with `1 test files, 1251 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test` passed with `odf open document handoff self-test ok`.

## Status Delta

- Focused ODF PASS cases: `+1`.
- Focused ODF assertions: `1227 -> 1251` (`+24`).
- Lane PHP pass count: `1328 -> 1329`.
- Manifest mapped checks: `1742 -> 1743`.
- ODF/OpenDocument core cases: `10 -> 11`.
- ODF/OpenDocument core assertions: `217 -> 241`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`, `OdfReader`, `AstNode`, `MarkdownWriter`, `WordPressBlockWriter`, and the focused PHP test harness.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout and Cabal/Haskell runner build. No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice, `zip`/`unzip`, external converter, office tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat ODF manifest/content/styles/meta package loading, outline extraction, list/table/image/comment/index-entry handoff, table of contents mapping, tab-stop diagnostics, tracked changes, DOCX/OPC/EPUB package behavior, or export-side ODT writing. It adds only conditional and hidden text field preservation in the ODF reader.

## Follow-Up

Keep database fields, hidden paragraphs and conditional sections, richer index-entry layout application, tab-stop position metadata, export-side ODT writing, and hydrated upstream Haskell runner comparison as separate bounded slices.
