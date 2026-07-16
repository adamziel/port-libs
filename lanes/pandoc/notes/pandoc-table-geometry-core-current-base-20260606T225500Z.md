# Pandoc Table Geometry Empty-Table Handoff

Micro-slice: `pandoc-table-geometry-core-current-base-20260606T225500Z`
Base accepted HEAD: `72b74d8bf978910fedcbf4b3ed6fbaee9456d76b`

## Behavior

- Added a bounded table geometry diagnostic for AST tables that have no cell
  coverage: `table-has-no-cells`.
- The diagnostic preserves caption state, declared/visual column counts,
  row-group counts, and empty section summaries for reader/writer review
  packets.
- Added Markdown, AsciiDoc, and LaTeX writer-review diagnostics for empty
  tables:
  - `markdown-empty-table-omitted`
  - `asciidoc-empty-table-review-required`
  - `latex-empty-table-review-required`
- WordPress output remains a preservation path for the source table element and
  does not receive an empty-table downgrade diagnostic.

## Source Truth

- The lane manifest already maps Pandoc's static upstream HTML-reader inventory
  including "Tables without Headers" shapes and empty table omission. This slice
  ports the format contract into native PHP review-packet behavior rather than
  shelling out to Pandoc or any external writer.
- A hydrated Pandoc source checkout is not present in
  `/home/claude/port-libs/.upstream-cache/pandoc` for this worker, so this
  slice reuses accepted static inventory evidence plus local AST/writer
  contracts.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  passed with `1 test files, 1167 assertions, 0 failures`.
- Red-first after adding the empty-table test:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  failed with `1 test files, 1170 assertions, 1 failures` because
  `table-has-no-cells` was not emitted.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  passed with `1 test files, 1202 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  passed with `table geometry handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/TableGeometry.php`,
  `php -l lanes/pandoc/tests/TableGeometryTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php` passed.
- Patch check:
  `git diff --check -- lanes/pandoc` passed with no output.
- Root harness:
  not run - isolated micro-slice.

## Dependency Closure

- No new support component is needed. The slice reuses native PHP
  `TableGeometry`, `MarkdownWriter`, and `WordPressBlockWriter` behavior.
- No Pandoc, Cabal, Haskell runner, Word, LibreOffice, external writer,
  template engine, TeX/PDF engine, browser renderer, online service, or
  live-service provider test was executed.

## Next

- Continue table-geometry closure with non-overlapping table writer or reader
  cases such as additional Markdown pipe-table loss diagnostics, alignment
  inheritance edge cases, or bounded WordPress review metadata for table
  captions/sections.
