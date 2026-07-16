# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T031548Z`
Accepted base: `80e991a9a9260837e8690f88d3d4a67c380b7cf5`

## Behavior

- Added bounded native DOCX cross-paragraph comment range preservation.
- `DocxReader` now carries an active `w:commentRangeStart` id across
  consecutive paragraphs in a block container.
- Each rendered paragraph segment inside the range is wrapped with the existing
  `.docx-comment-range` metadata from `comments.xml`.
- Text after `w:commentRangeEnd` remains outside the range, and the later
  `w:commentReference` still renders as the normal reviewer note in Markdown
  and WordPress output.
- The WordPress DOCX body handoff example now asserts the same
  multi-paragraph reviewer comment path.

## Source Truth

- WordprocessingML comments may mark a range with `w:commentRangeStart`,
  `w:commentRangeEnd`, and a later `w:commentReference`.
- The range can span more than one paragraph; the reader should preserve the
  reviewer metadata for all visible covered text while keeping the comment note
  reference available for review queues.
- This is bounded native PHP DOCX/OpenXML support, not full Haskell runner
  parity.

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null | tail -20`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 566 assertions, 0 failures`.
- Red-first focused test after adding cross-paragraph comment expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 578 assertions, 1 failures`; the second paragraph
    began as plain text instead of a `docx-comment-range` span.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 593 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- Metadata and whitespace checks:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
  - `git diff --check -- lanes/pandoc`
  - Result: no output.

## Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Adds `+27` focused DOCX assertions over the accepted baseline
  (`566 -> 593` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1047 -> 1048`.
- Updates lane `phpPass` from `571 -> 572`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC
  relationships/content types, `DocxReader`, `MarkdownWriter`, and
  `WordPressBlockWriter`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` build dependency closure, not by
  this DOCX/OpenXML behavior.

## Non-Overlap

- Does not repeat accepted ZIP/OPC package parsing, relationship preflight,
  DOCX body/core properties, styles/numbering, nested lists, table spans,
  same-paragraph comment ranges, endnotes, media import reports, OMML math,
  tracked changes, bookmarks, field-code hyperlinks, section header/footer
  metadata, structured document tags, or alternative-format `altChunk` imports.
- Leaves cross-table/cross-section comment ranges, overlapping comment ranges,
  range-level block wrappers, comments with rich nested media relationships,
  charts/diagrams, style-linked numbering restarts, and broader malformed
  OpenXML fixtures as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
