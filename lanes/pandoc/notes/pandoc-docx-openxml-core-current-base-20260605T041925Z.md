# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T041925Z`
Accepted base: `9f1a5acae6e7f10a53b3e432bfded7a636865d9e`

## Behavior

- Added bounded native DOCX `w:sym` run decoding.
- `DocxReader` now maps common Word symbol-font characters into Unicode text
  nodes before Markdown or WordPress block rendering.
- Handles the Word private-use `F000` codepoint convention by normalizing it
  back to the low byte before looking up source symbol mappings.
- Covers bounded `Symbol`, `Wingdings`, `Wingdings 2`, and `Wingdings 3`
  markers used by checklist and source-review documents.
- Unknown symbol fonts are suppressed rather than leaking private-use glyphs;
  known symbol fonts with unmapped values preserve the original codepoint.
- The WordPress DOCX body handoff example now asserts decoded symbol markers:
  `alpha`, bullet, check mark, and left arrow.

## Source Truth

- Upstream Pandoc's DOCX reader treats `w:sym` as inline run content and routes
  symbol-font decoding through its symbol table.
- Upstream Pandoc's `Symbols.hs` carries explicit Microsoft symbol-font tables
  for `Symbol`, `Wingdings`, `Wingdings 2`, `Wingdings 3`, and `Webdings`.
- The bounded PHP map in this slice follows the same contract for the
  fixture-backed entries needed by current WordPress handoff coverage.
- Source references:
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Docx/Parse.hs`
  - `https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/src/Text/Pandoc/Readers/Docx/Symbols.hs`

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 620 assertions, 0 failures`.
- Red-first focused test after adding symbol expectations:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 623 assertions, 1 failures`; the paragraph rendered
    as `Checklist symbols /    remain visible.` because `w:sym` was dropped.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 627 assertions, 0 failures`.
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
- Adds `+7` focused DOCX assertions over the accepted baseline
  (`620 -> 627` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1087 -> 1088`.
- Updates lane `phpPass` from `613 -> 614`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC package
  parsing, `DocxReader`, `MarkdownWriter`, and `WordPressBlockWriter`.
- Full upstream symbol-table parity remains a bounded DOCX follow-up, not a new
  dependency. Full upstream Pandoc runner parity remains blocked by the
  existing Haskell `test-pandoc` / `test-pandoc-lua-engine` dependency closure.

## Non-Overlap

- Does not repeat accepted ZIP/OPC package parsing, relationships/content
  types, DOCX body/core properties, styles/numbering, nested lists, table spans,
  endnotes, comments, comment ranges, cross-paragraph comment ranges, media
  import reports, OMML math, tracked changes, bookmarks, field-code hyperlinks,
  non-hyperlink field provenance, section properties, header/footer import,
  structured document tags, smart tags, or `altChunk` imports.
- Leaves full `Symbols.hs` table parity, Webdings coverage, symbol-font
  decoding for normal text runs selected through `w:rFonts`, VML textboxes,
  charts, diagrams, customXml wrappers, style-linked numbering restarts, and
  broader malformed OpenXML fixtures as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
