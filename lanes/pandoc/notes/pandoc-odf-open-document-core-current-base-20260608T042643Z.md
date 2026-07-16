# Pandoc ODF OpenDocument Core Drop-Down Field Slice

Slice: `pandoc-odf-open-document-core-current-base-20260608T042643Z`
Base: `e8c43317726abb932805c171a399c58fb2c01c99`

## Behavior Added

`OdfReader` now maps bounded ODT `text:drop-down` fields into the existing inert `odf-field` span handoff. Each dropdown keeps:

- the field name;
- all `text:label` option values in structured `fieldMetadata`;
- `labelCount` and `selectedValue` as Markdown/WordPress review attributes;
- selected-label fallback text from `text:current-selected="true"`;
- first-label fallback text when no option is explicitly selected.

This preserves reviewer-visible import state for ODT form-like source packets without implementing an office form engine or evaluating document UI state.

## Source Truth

The local upstream Pandoc checkout is not present in this isolated worktree or the shared upstream cache. This slice uses the lane's accepted static ODF/OpenDocument support-library contract and ODF XML fixture shape already exercised by `OdfReader`: text-field elements become inert AST spans with metadata and WordPress-safe `data-odf-field-*` attributes. It does not run Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests.

## Verification

Red-first:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1703 assertions, 1 failures`
- Failure: `text:drop-down` was skipped, leaving `Disposition  with fallback  remains auditable.`

Final:

- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
- Result: `1 test files, 1727 assertions, 0 failures`

Example smoke:

- `php lanes/pandoc/examples/wordpress-odf-open-document-handoff.php --self-test`
- Result: `odf open document handoff self-test ok`

## Status Delta

- Manifest mapped checks: `1959 -> 1960`
- Lane PHP pass cases: `1539 -> 1540`
- ODF/OpenDocument core cases: `11 -> 12`
- ODF/OpenDocument core assertions: `251 -> 276`
- Added focused dropdown assertions: `25`

## Dependency Closure

No new support component is needed. This reuses the existing native `OdfReader` field-span pipeline, `MarkdownWriter` span attributes, `WordPressBlockWriter` span attributes, focused `OdfReaderTest.php`, and the lane-local WordPress ODF handoff example.

## Non-Overlap

This does not repeat accepted ODF work for text tabs, blockquote styles, preformatted/source text styles, ruby, continued lists, notes, bookmarks, reference marks, sequences, soft page breaks, form controls, variable/user/text input fields, database fields, source metadata fields, page/chapter/statistic fields, conditional/hidden text, placeholders, bibliography marks, table of contents, generated indexes, section metadata, tracked changes, MathML, embedded objects, charts, image frames, table captions, or package/media/encryption preflight.

## Follow-Up

Keep separate ODF slices for hidden paragraphs, DDE and execute-macro audit fields, richer form/list controls, additional section/index metadata, export-side ODT writing, and full upstream Pandoc ODT runner parity once the pinned checkout and Haskell test executables are available.
