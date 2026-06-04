# Pandoc Table Geometry Core Current Base

Slice: `pandoc-table-geometry-core-current-base-20260604T081300Z`

Accepted base: `d4437ad64ff9f4760da71503530dfa31124f0f7c`

## Behavior

- Added `TableGeometry` as a bounded native PHP support helper for Pandoc-like
  table AST handoff.
- Computes visual cell columns across `colspan` and `rowspan` before writer
  rendering, matching the Pandoc table contract that column specs apply to the
  occupied visual column rather than the physical child index in a row.
- Rewired Markdown table fallback through the shared layout so rectangular pipe
  rows preserve span placeholders.
- Rewired WordPress table output so cells after merged header/body cells inherit
  the correct alignment column. This fixes reviewer grids where a colspan header
  or rowspan body cell previously shifted later cells onto the wrong
  `alignments` entry.
- Added `wordpress-table-geometry-handoff.php`, a local WordPress block smoke
  that renders a merged migration review grid without Pandoc, Word,
  LibreOffice, zip/unzip, or online services.

## Source Truth

- Uses the existing Pandoc static-inventory table rows as source truth:
  `test/command/table-with-cell-align.md`,
  `test/command/table-with-column-span.md`,
  `test/command/rst-writer-gridtable-if-rowspans.md`, `test/pipe-tables.txt`,
  and `test/tables.markdown` are already mapped in
  `UPSTREAM_TEST_MANIFEST.json`.
- This slice ports the format contract only: visual columns advance through
  spans before alignment/writer handoff. It does not attempt the upstream
  Haskell runner.

## Evidence

- `php -l lanes/pandoc/src/TableGeometry.php`: no syntax errors.
- `php -l lanes/pandoc/src/MarkdownWriter.php`: no syntax errors.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`:
  1 selected test file, 18 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`:
  1 selected test file, 2375 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`:
  1 selected test file, 108 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php`:
  emitted WordPress table HTML with `Status` center-aligned after a two-column
  header span and second-row body cells right/center aligned after a rowspan.
- `php tools/run-tests.php lanes/pandoc/tests`:
  8 selected test files, 2875 assertions, 0 failures.

## Non-Overlap

This does not repeat the accepted DOCX `w:gridSpan`/`w:vMerge` parser slice,
DocBook span parsing, HTML table reader coverage, Markdown pipe-table parsing,
or existing WordPress colspan/rowspan attribute output. The new behavior is the
shared visual-column table geometry used by writers after the AST already
contains span attributes.

## Dependency Closure

No new support component is needed. This reuses the existing Pandoc table AST
nodes and native Markdown/WordPress writers. Out of scope remain malformed
overlap diagnostics, richer ODT/DOCX table normalization reports, full upstream
Pandoc Haskell runner execution, office suites, external template engines, and
TeX/PDF engines.

Root harness: not run - isolated micro-slice.
