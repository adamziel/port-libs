# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T051659Z`
Accepted base: `89fe927c0e557441b63b35cc5e2a446a60b5ddf2`

## Behavior

- Added bounded native DOCX `w:customXml` wrapper preservation in
  `DocxReader`.
- Inline `w:customXml` content now becomes a `span` with
  `.docx-custom-xml` and bounded `data-docx-custom-xml-*` metadata.
- Block-level `w:customXml` content now becomes a `div` preserving child
  paragraphs/tables and the same metadata shape.
- Preserved `w:uri`, `w:element`, and sanitized `w:customXmlPr/w:attr`
  name/value/URI properties through Markdown and WordPress block output.
- Updated the WordPress DOCX body handoff example to assert custom XML review
  fields without invoking office tooling.

## Source Truth

- WordprocessingML `customXml` wrappers carry custom schema metadata around
  ordinary visible block or inline Word content.
- Pandoc-style import should preserve visible content and reviewable metadata
  rather than dropping these wrappers or shelling out to Word/Pandoc.
- This slice is bounded native PHP DOCX/OpenXML support, not Haskell runner
  parity.

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 646 assertions, 0 failures`.
- Red-first focused test after adding custom XML expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 647 assertions, 1 failures`; the reader returned
    only one body block because block-level `w:customXml` content was dropped.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 674 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.

## Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Adds `+28` focused DOCX assertions over the accepted baseline
  (`646 -> 674` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1,122 -> 1,123`.
- Updates DOCX/OpenXML mapped cases from `31 -> 32`.
- Updates lane `phpPass` from `646 -> 647`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC package
  parsing, `DocxReader`, `MarkdownWriter`, `WordPressBlockWriter`, and
  `TableGeometry`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` dependency closure, not by this
  DOCX/OpenXML behavior.

## Non-Overlap

- Does not repeat accepted ZIP/OPC parsing, relationships/content types, DOCX
  body/core properties, styles/numbering, nested lists, table spans, endnotes,
  comments, comment ranges, media reports, OMML math, tracked changes,
  bookmarks, field-code hyperlinks, content controls, smart tags, symbol-font
  runs, VML textboxes, section properties, header/footer import, or `altChunk`
  imports.
- Leaves custom XML datastore item relationships, nested/overlapping custom
  XML ranges, charts/diagrams, style-linked numbering restarts, full VML shape
  image extraction, richer media export policy, and malformed OpenXML fixtures
  as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
