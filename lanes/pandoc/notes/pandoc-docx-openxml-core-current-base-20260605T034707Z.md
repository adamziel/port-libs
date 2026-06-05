# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T034707Z`
Accepted base: `45c71a1afa8b5325fb861f358457c511540bfeeb`

## Behavior

- Added bounded native DOCX `w:smartTag` metadata preservation.
- `DocxReader` now wraps visible smart-tag inline content in an AST `span`
  with `.docx-smart-tag`.
- The span carries `w:uri`, `w:element`, and bounded `w:smartTagPr/w:attr`
  values as sanitized `data-docx-smart-tag-*` attributes.
- Existing child run styling is preserved, so bold/emphasis/link content inside
  the smart tag still renders normally.
- Markdown and WordPress block output use the existing generic span attribute
  path; no writer-specific smart-tag special case was needed.
- The WordPress DOCX body handoff example now asserts the rendered
  `docx-smart-tag` span.

## Source Truth

- WordprocessingML smart tags are inline semantic wrappers around ordinary
  visible content and may carry `w:uri`, `w:element`, and property attributes.
- Import review queues should not drop the visible content or the source
  provenance when Word emits these wrappers.
- This is bounded native PHP DOCX/OpenXML support, not full Haskell runner
  parity.

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null | tail -20`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 593 assertions, 0 failures`.
- Red-first focused test after adding smart-tag expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 596 assertions, 1 failures`; the smart-tag child
    began as `strong` because `w:smartTag` was unwrapped.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 607 assertions, 0 failures`.
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
- Adds `+14` focused DOCX assertions over the accepted baseline
  (`593 -> 607` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1066 -> 1067`.
- Updates lane `phpPass` from `586 -> 587`.

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
  endnotes, comments, comment ranges, cross-paragraph comment ranges, media
  import reports, OMML math, tracked changes, bookmarks, field-code
  hyperlinks, section header/footer metadata, structured document tags, or
  alternative-format `altChunk` imports.
- Leaves nested/overlapping smart tags, smart tags across block boundaries,
  customXml markup, glossary/document-part smart-tag dictionaries, charts and
  diagrams, style-linked numbering restarts, and broader malformed OpenXML
  fixtures as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
