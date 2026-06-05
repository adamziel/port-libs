# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T072613Z`
Base accepted HEAD: `17dbfeadf12027c4877b7ae89d1c4dadc1683066`

## Behavior

- Added bounded DOCX run review-markup preservation for `w:highlight` and
  `w:shd` inside `w:rPr`.
- `DocxReader` now wraps highlighted and shaded run content in normal
  Pandoc-like `span` AST nodes with `docx-highlight`, color-specific
  `docx-highlight-*`, and `docx-shading` classes plus `data-docx-*`
  attributes.
- `w:highlight w:val="none"` remains unmarked so Word's explicit no-highlight
  runs do not create noisy reviewer spans.
- The WordPress DOCX body handoff smoke now proves highlighted and shaded
  reviewer text survives into WordPress block HTML.

## Source Truth

- WordprocessingML stores visible run review marks in run properties such as
  `w:highlight` and `w:shd`.
- Pandoc-style DOCX import should preserve visible reviewer annotations instead
  of flattening them into unmarked plain text, while keeping normal bold/italic
  run styling intact.
- This is native PHP DOCX/OpenXML parsing only. It does not invoke Pandoc, Word,
  LibreOffice, zip/unzip, external office tooling, browser renderers, online
  sanitizers, or online conversion services.

## Evidence

- Rework notes:
  - `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort`
  - Result: no current Pandoc lane rework notes.
- Baseline focused DOCX test before adding the new case:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 783 assertions, 0 failures`.
- Red-first focused test after adding highlight/shading expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 785 assertions, 1 failures`.
  - Failure: the marked paragraph collapsed to three plain nodes instead of
    seven nodes with highlight/shading spans.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 813 assertions, 0 failures`.
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
- Raises focused DOCX reader coverage from `783 -> 813` assertions.
- Updates Pandoc mapped checks from `1203 -> 1204`.
- Updates DOCX/OpenXML mapped cases from `31 -> 32`.
- Updates DOCX/OpenXML mapped assertion counter from `313 -> 343`.
- Updates lane PHP pass count from `744 -> 745`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC package
  primitives, `DocxReader`, `MarkdownWriter`, and `WordPressBlockWriter`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` build dependency closure, not by
  this DOCX/OpenXML behavior.

## Non-Overlap

- Does not repeat accepted ZIP/OPC parsing, relationship target preflight,
  DOCX body/core properties, styles/numbering, nested lists, table spans,
  comments/endnotes, comment ranges, DrawingML image extraction, VML image or
  textbox extraction, OMML math, tracked changes, bookmarks, field-code
  hyperlinks, content controls, smart tags, custom XML wrappers, symbol-font
  runs, section properties, header/footer import, `altChunk` imports, Markup
  Compatibility `AlternateContent` selection, or chart/diagram drawing
  placeholders.
- Leaves style color inheritance, paragraph shading, revision property changes,
  commentsExtended threading, chart workbook traversal, SmartArt text
  extraction, and malformed OpenXML recovery as separate bounded DOCX/OpenXML
  slices.

## Root Harness

- Not run - isolated micro-slice.
