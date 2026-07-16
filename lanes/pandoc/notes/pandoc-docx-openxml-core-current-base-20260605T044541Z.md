# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T044541Z`
Accepted base: `94b3a2779b755c19b92832ec3f5c8c93bdbddb56`

## Behavior

- Added bounded native DOCX VML textbox extraction.
- `DocxReader` now splits a paragraph around `w:r` runs that contain
  `w:pict` / `v:textbox` / `w:txbxContent`, then imports textbox paragraphs
  and tables as sibling body blocks in source order.
- Handles `mc:AlternateContent` fallback branches for compatibility-authored
  textbox runs, matching the upstream DOCX reader's fallback preference.
- The normal paragraph/list path is preserved when a paragraph has no textbox
  runs.
- The WordPress DOCX body handoff example now proves VML textbox paragraphs
  render as WordPress blocks without calling office tooling.

## Source Truth

- Upstream Pandoc's DOCX parser walks the document through `walkDocument` and
  unwraps VML textbox content from paragraph runs before converting body
  elements.
- Its `splitP` path preserves non-textbox paragraph content before and after a
  textbox run, and inserts the textbox body content between those segments.
- Its run fallback path looks under `mc:AlternateContent` / `mc:Fallback` to
  avoid duplicate extraction when compatibility markup is present.
- Source reference:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Docx/Parse.hs`

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 627 assertions, 0 failures`.
- Red-first focused test after adding textbox expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 628 assertions, 1 failures`; the new case expected
    six body blocks but the current reader returned one because `w:pict`
    textbox content was ignored.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 646 assertions, 0 failures`.
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
- Adds `+19` focused DOCX assertions over the accepted baseline
  (`627 -> 646` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1104 -> 1105`.
- Updates lane `phpPass` from `629 -> 630`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC package
  parsing, `DocxReader`, `MarkdownWriter`, `WordPressBlockWriter`, and
  `TableGeometry`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` dependency closure.

## Non-Overlap

- Does not repeat accepted ZIP/OPC package parsing, relationships/content
  types, DOCX body/core properties, styles/numbering, nested lists, table
  spans, endnotes, comments, comment ranges, media import reports, OMML math,
  tracked changes, bookmarks, field-code hyperlinks, non-hyperlink field
  provenance, structured document tags, smart tags, symbol-font runs, section
  properties, header/footer import, or `altChunk` imports.
- Leaves full VML shape image extraction, chart/diagram placeholders,
  customXml wrappers, style-linked numbering restarts, Webdings/full
  symbol-table parity, and broader malformed OpenXML fixtures as separate
  bounded slices.

## Root Harness

- Not run - isolated micro-slice.
