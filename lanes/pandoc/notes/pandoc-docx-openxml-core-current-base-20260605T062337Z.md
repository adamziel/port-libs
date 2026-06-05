# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T062337Z`
Base accepted HEAD: `cbee5993ac487ee180c9498bae22759f9cbd4213`

## Behavior

- Added bounded DOCX Markup Compatibility `mc:AlternateContent` selection.
- `DocxReader` now imports the first `mc:Choice` whose `Requires` prefixes all
  resolve to namespaces already handled by this reader.
- Unsupported choices fall back to `mc:Fallback`.
- The same selection path is used for body block traversal, paragraph inline
  traversal, and child content inside `w:r` runs.
- The WordPress DOCX body handoff smoke now proves selected compatibility
  branches render while unsupported or unselected branches stay suppressed.

## Source Truth

- OpenXML Markup Compatibility uses `mc:AlternateContent` containers with
  `mc:Choice Requires="..."` and optional `mc:Fallback` branches so consumers
  can select content they understand and avoid unsupported extension markup.
- This bounded PHP port treats WordprocessingML, DrawingML image namespaces,
  Office relationships, OMML, VML, and Office VML as understood for DOCX body
  import; broader Office extension namespaces remain future work.
- This is native DOCX/OpenXML parsing only. It does not implement full
  `ProcessContent`, `PreserveElements`, `PreserveAttributes`, `MustUnderstand`,
  chart/diagram placeholders, or malformed OpenXML recovery.

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc lane rework notes.
- Baseline focused DOCX test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 712 assertions, 0 failures`.
- Red-first focused test after adding AlternateContent expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 713 assertions, 1 failures`.
  - Failure: selected `mc:AlternateContent` branches were missing; the fixture
    produced one body block instead of four selected body blocks.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 733 assertions, 0 failures`.
- Full lane-local focused test directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8049 assertions, 0 failures`.
  - PASS lines: `686` via `rg -c '^PASS ' /tmp/pandoc-docx-alternatecontent-tests.log`.
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

## Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Adds `+21` focused DOCX assertions over the accepted baseline
  (`712 -> 733` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1164 -> 1165`.
- Updates DOCX/OpenXML mapped cases from `31 -> 32`.
- Updates lane PHP pass count from `685 -> 686`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC
  relationships/content types, `DocxReader`, `MarkdownWriter`, and
  `WordPressBlockWriter`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` build dependency closure, not by
  this DOCX/OpenXML behavior.

## Non-Overlap

- Does not repeat accepted ZIP/OPC parsing, relationship target preflight,
  DOCX body/core properties, styles/numbering, nested lists, table spans,
  comments/endnotes, comment ranges, DrawingML or VML image extraction, VML
  textbox fallback extraction, OMML math, tracked changes, bookmarks,
  field-code hyperlinks, content controls, smart tags, custom XML wrappers,
  symbol-font runs, section properties, header/footer import, or `altChunk`
  imports.
- Leaves full Markup Compatibility processing policy, broader extension
  namespace support, chart/diagram placeholders, custom XML datastore item
  relationships, style-linked numbering restarts, and malformed OpenXML
  recovery as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
