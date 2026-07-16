# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T024629Z`
Accepted base: `93ff2a1225d594c3864b3222b381965462c18bba`

## Behavior

- Added bounded native DOCX linked-media handling in `DocxReader`.
- Drawing image metadata is now resolved from the nearest `wp:inline` or
  `wp:anchor` container's `wp:docPr`, so multiple images inside one
  `w:drawing` keep distinct alt/title metadata instead of inheriting the first
  drawing property.
- Safe external DrawingML image relationships referenced by `a:blip r:link`
  now become normal image AST nodes and WordPress image markup without fetching
  remote bytes.
- Unsafe external image targets, such as `javascript:` relationships, remain
  visible in the OPC relationship/media report but are not rendered into
  Markdown or WordPress output.
- The DOCX media import report now ties AST-used linked-media nodes back to
  their external relationship targets for `usedCount`, alt text, and titles.

## Source Truth

- This is bounded OpenXML package behavior: DrawingML images can be embedded
  with `r:embed` or linked with `r:link`, and `wp:docPr` is scoped to the
  concrete `wp:inline` / `wp:anchor` drawing object.
- The local upstream Pandoc checkout is not hydrated in this isolated worktree,
  so this remains native PHP DOCX/OpenXML support and focused handoff coverage,
  not Haskell runner parity.
- No Pandoc, Word, LibreOffice, zip/unzip, `ZipArchive`, Haskell runner,
  browser renderer, online service, or external converter was invoked.

## Evidence

- Rework notes:
  - `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' -print | sort | tail -20`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before this implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 515 assertions, 0 failures`.
- Red-first focused test after adding linked-media coverage:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 517 assertions, 1 failures`; the reader produced
    only two image nodes because safe external `r:link` media was dropped.
- Focused test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 566 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- Full focused Pandoc lane directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5969 assertions, 0 failures`; verified `551`
    PASS lines with `rg -c '^PASS ' /tmp/pandoc-focused-tests.log`.

## Delta

- Adds `+1` focused DOCX/OpenXML PHP PASS case.
- Adds `+51` focused DOCX assertions over the accepted baseline
  (`515 -> 566` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1029 -> 1030`.
- Updates lane `phpPass` from `550 -> 551`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`,
  OPC relationships/content types, `DocxReader`, `MarkdownWriter`, and
  `WordPressBlockWriter`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` build dependency closure, not by
  this DOCX/OpenXML behavior.

## Non-Overlap

- Does not repeat accepted ZIP/OPC package parsing, relationship preflight,
  DOCX body/core properties, flat or nested DOCX numbering, table spans,
  comments/endnotes, OMML math, tracked changes, bookmarks, field-code
  hyperlinks, section header/footer metadata, structured document tags,
  alternative-format `altChunk` imports, or ZIP encrypted metadata preflight.
- Leaves DOCX charts/diagrams, external media download/import policy, unsafe
  linked-media quarantine UI, richer media extraction/export policy,
  cross-paragraph comment range stitching, style-linked numbering restarts,
  and broader malformed OpenXML drawing fixtures as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
