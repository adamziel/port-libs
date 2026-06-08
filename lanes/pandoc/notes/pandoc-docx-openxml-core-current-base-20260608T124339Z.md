# DOCX/OpenXML Table Reviewer Ranges

Slice: `pandoc-docx-openxml-core-current-base-20260608T124339Z`

Accepted base: `a88c4509d5cb7b64defde196ff1edfb693356b72`

## Behavior

- Added native DOCX/OpenXML handling for reviewer ranges that start in a body paragraph and close inside a table cell paragraph.
- `DocxReader` now keeps active proof-error, permission, and move-range state while descending through `w:tbl` / `w:tc` block parsing.
- The AST, Markdown writer, and WordPress block writer preserve the table-cell closing segments as reviewer spans instead of leaking or resetting range state.

## Source Truth

- WordprocessingML range markers are document-order markup and can span block boundaries; this slice keeps the existing cross-paragraph behavior active through table-cell block containers.
- No Pandoc, Word, LibreOffice, zip/unzip, Cabal, Haskell runner, external office tool, online service, live provider test, or live-service provider test was executed.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result before reader fix after adding the focused fixture: `1 test files, 2554 assertions, 1 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 2614 assertions, 0 failures`.
  - Focused assertion growth over the accepted DOCX file count: `+61`.
- Example smoke: `php lanes/pandoc/examples/wordpress-docx-reviewer-table-ranges.php --self-test`
  - Result: `wordpress-docx-reviewer-table-ranges self-test passed`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `ZipPackage` OPC fixture path, `DocxReader` WordprocessingML body/table traversal, `MarkdownWriter`, `WordPressBlockWriter`, and the lane `TestRunner`.

## Non-Overlap

This avoids the already accepted DOCX slices for document background, table cell margins, omitted table row grid columns, cross-paragraph proof/permission ranges, cross-paragraph move ranges, header/footer section imports, and upstream-runner dependency audits. It only threads reviewer range state through table cells.

## Follow-Up

Potential next DOCX/OpenXML gaps are cross-container comment ranges through tables, section-linked header/footer edge cases, or additional style/numbering metadata. Keep follow-up work native PHP and external-tool free.
