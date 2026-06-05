# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T021610Z`
Accepted base: `0df7f83fa6571259635166e594b06a5096c92f71`

## Behavior

- Added bounded native DOCX multilevel numbering preservation in `DocxReader`.
- Consecutive WordprocessingML paragraphs with the same `w:numId` and deeper
  `w:ilvl` values now become child `bullet_list` / `ordered_list` AST nodes
  under the preceding `list_item` instead of separate top-level lists.
- The focused fixture covers decimal top-level numbering, lower-alpha second
  level numbering, a third-level bullet, Markdown output, and WordPress nested
  list markup.
- Updated the WordPress DOCX body handoff example so the user-visible import
  path asserts nested checklist output.

## Source Truth

- This is a bounded native OpenXML behavior matching Pandoc's AST expectation
  that nested lists are represented as list blocks inside list items, and DOCX
  WordprocessingML's `w:numPr` / `w:ilvl` outline levels.
- No Pandoc, Word, LibreOffice, zip/unzip, `ZipArchive`, Haskell runner, online
  converter, or browser renderer was invoked.

## Evidence

- Rework notes:
  - `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 490 assertions, 0 failures`.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 515 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- Full focused Pandoc lane directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5656 assertions, 0 failures`.
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- Final metadata / whitespace checks:
  - `php -r 'json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `git diff --check -- lanes/pandoc`
  - Result: `pandoc json ok`; diff whitespace check exited with no output.

## Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Adds `+25` focused DOCX assertions over the accepted baseline
  (`490 -> 515` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1006 -> 1007`.
- Updates lane `phpPass` from `528 -> 529`.

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
  DOCX body/core properties, flat DOCX style/numbering grouping, table spans,
  comments/endnotes, media import reports, OMML math, tracked changes,
  bookmarks, field-code hyperlinks, section header/footer metadata, structured
  document tags, or alternative-format `altChunk` imports.
- Leaves DOCX charts/diagrams, richer media extraction/export policy,
  cross-paragraph comment range stitching, style-linked numbering restarts, and
  broader malformed OpenXML numbering fixtures as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
