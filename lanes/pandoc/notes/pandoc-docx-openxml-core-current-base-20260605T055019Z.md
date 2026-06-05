# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260605T055019Z`
Accepted base: `59f74ed0eba0c82ff3e4a59978f6d445940ec730`

## Behavior

- Added bounded native DOCX VML picture image extraction.
- `DocxReader` now maps `w:pict` / `v:imagedata` image relationships through
  the same AST and media-report path used by DrawingML images.
- Embedded VML images render only when the package target exists and preserve
  source part, byte count, alt text, and title metadata.
- Safe external VML image targets render as linked images without fetching
  remote bytes.
- Unsafe external VML image targets remain visible in the OPC/media import
  report but are not rendered to Markdown or WordPress blocks.
- The WordPress DOCX body handoff smoke now includes a VML badge image so
  reviewer queues can see legacy Word picture markup without office tooling.

## Source Truth

- WordprocessingML can carry legacy VML picture markup inside `w:pict`.
- VML picture payloads reference package image relationships with
  `v:imagedata r:id`, while visible review metadata can come from the owning
  VML shape's `alt` and the Office VML `o:title` attribute.
- This is bounded native PHP DOCX/OpenXML support, not full Haskell runner
  parity.

## Evidence

- Rework notes:
  - `ls -1 /home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md 2>/dev/null || true`
  - Result: no current Pandoc lane rework notes.
- Baseline focused test before implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 674 assertions, 0 failures`.
- Focused DOCX test after implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 712 assertions, 0 failures`.
- WordPress example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- Full focused Pandoc lane directory:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 7752 assertions, 0 failures`; counted `667`
    `PASS` lines with `rg -c '^PASS ' /tmp/pandoc-vml-focused-tests.log`.
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
- Adds `+38` focused DOCX assertions over the accepted baseline
  (`674 -> 712` for `DocxReaderTest.php`).
- Updates Pandoc mapped checks from `1,144 -> 1,145`.
- Updates DOCX/OpenXML mapped cases from `31 -> 32`.
- Records the verified lane PHP PASS count as `667`.

## Dependency Closure

- No new support component is needed.
- This slice reuses existing native PHP support: `ZipPackage`, OPC
  relationships/content types, `DocxReader`, `MarkdownWriter`, and
  `WordPressBlockWriter`.
- Full upstream Pandoc runner parity remains blocked by the existing Haskell
  `test-pandoc` / `test-pandoc-lua-engine` build dependency closure, not by
  this DOCX/OpenXML behavior.

## Non-Overlap

- Does not repeat accepted ZIP/OPC parsing, relationships/content types, DOCX
  body/core properties, styles/numbering, nested lists, table spans, endnotes,
  comments, comment ranges, media reports for DrawingML images, OMML math,
  tracked changes, bookmarks, field-code hyperlinks, content controls, smart
  tags, symbol-font runs, VML textboxes, customXml wrappers, section
  properties, header/footer import, or `altChunk` imports.
- Leaves charts/diagrams, custom XML datastore item relationships,
  style-linked numbering restarts, full VML shape geometry, and malformed
  OpenXML drawing fixtures as separate bounded slices.

## Root Harness

- Not run - isolated micro-slice.
