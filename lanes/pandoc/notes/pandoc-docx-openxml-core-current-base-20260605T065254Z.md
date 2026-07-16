# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T065254Z`
Base accepted HEAD: `6f0599fb16318c7a4804ad4d461c523d11b7efa8`

## Behavior

- Added bounded DOCX chart and SmartArt diagram drawing placeholders.
- `DocxReader` now preserves `c:chart r:id` drawing references as span AST
  nodes with relationship id/type, resolved target part, content type,
  existence flag, and `wp:docPr` metadata.
- `DocxReader` now preserves `dgm:relIds` SmartArt references as one diagram
  span carrying the data/layout/quick-style/colors relationship ids, target
  parts, content types, and existence flags.
- The WordPress DOCX body handoff smoke now proves chart and diagram review
  placeholders survive into WordPress blocks.

## Source Truth

- OpenXML WordprocessingML drawings can carry non-image payload references such
  as chart `c:chart` elements and SmartArt diagram `dgm:relIds` elements.
- Pandoc-style import should not silently drop unsupported Office drawing
  objects; this slice exposes enough native metadata for reviewer handoff
  without parsing chart workbooks, rendering SmartArt, or fetching externals.
- This is native PHP DOCX/OpenXML package parsing only. It does not invoke
  Pandoc, Word, LibreOffice, zip/unzip, online conversion services, or diagram
  renderers.

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc lane rework notes.
- Focused DOCX test:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 783 assertions, 0 failures`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8336 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- Metadata check:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`.
- Whitespace check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Raises focused DOCX reader coverage to `783` assertions.
- Updates Pandoc mapped checks from `1183 -> 1184`.
- Updates DOCX/OpenXML mapped cases from `31 -> 32`.
- Updates lane PHP pass count from `724 -> 725`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC content
  types/relationships, `DocxReader`, `MarkdownWriter`, and
  `WordPressBlockWriter`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` build dependency closure, not by
  this DOCX/OpenXML behavior.

## Non-Overlap

- Does not repeat accepted ZIP/OPC parsing, relationship target preflight,
  DOCX body/core properties, styles/numbering, nested lists, table spans,
  comments/endnotes, comment ranges, DrawingML image extraction, VML image or
  textbox extraction, OMML math, tracked changes, bookmarks, field-code
  hyperlinks, content controls, smart tags, custom XML wrappers, symbol-font
  runs, section properties, header/footer import, `altChunk` imports, or
  Markup Compatibility `AlternateContent` selection.
- Leaves chart workbook parsing, SmartArt text extraction, diagram rendering,
  drawing layout fidelity, cross-part chart relationship traversal, and
  malformed drawing recovery as separate bounded DOCX/OpenXML slices.

## Root Harness

- Not run - isolated micro-slice.
