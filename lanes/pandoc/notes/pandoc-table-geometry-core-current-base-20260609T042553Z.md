# Pandoc Table Geometry Core Current Base - scope=auto

Slice: `pandoc-table-geometry-core-current-base-20260609T042553Z`
Base accepted HEAD: `11fc57ec36d6cc974a7a65f55020cfb9f1af6d59`

## Behavior

Implemented bounded HTML table header `scope="auto"` handling for native PHP table geometry handoff:

- `TableGeometry` now treats `auto` as a valid HTML source scope value for diagnostics.
- `scope="auto"` does not become an explicit source scope override; header associations still compute the effective `col` or `row` scope from table geometry.
- `WordPressBlockWriter` now detects tables containing `scope="auto"` and opts them into computed table accessibility attributes so the rendered WordPress block emits `scope="col"` / `scope="row"` and `headers="..."` instead of dropping the semantics or leaking literal `scope="auto"`.

This maps the Pandoc/HTML table contract for auto-scoped header cells without shelling out to Pandoc, Word, LibreOffice, zip/unzip, browser engines, Haskell runners, or online services.

## Non-Overlap

This slice stays inside `pandoc-table-geometry-core-*` ownership. It does not touch DOCX/ODT/EPUB containers, BibTeX/CSL, math/TeX, ZIP/OPC, XML/HTML5 DOM parsing, archive streams, upstream runner evidence, dashboard files, or root progress files.

Recent accepted table geometry areas already covered explicit `col`, `row`, `colgroup`, `rowgroup` source scopes, source `headers`, axis metadata, row-head columns, rowspan section scoping, colgroups, decimal alignment, captions, source attributes, and visual-slot diagnostics. This slice adds only the valid-but-computed `scope=auto` path.

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: `1 test files, 1057 assertions, 0 failures`
  - Added 1 PHP PASS case and 22 focused assertions.
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - Result: `1 test files, 1888 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - Result: `table geometry handoff self-test ok`

- `php -l lanes/pandoc/src/TableGeometry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `lanes/pandoc/lane-status.json`: `phpPass` 2298 -> 2299.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: mapped denominator 2698 -> 2699; `mappedTableGeometryCoreCases` 9 -> 10; `tableGeometryCoreAssertions` 155 -> 177.

## Dependency Closure

No new support component is needed. The patch reuses native PHP `MarkdownReader`, `TableGeometry`, `WordPressBlockWriter`, the lane-local PHP test runner, and the existing WordPress table geometry example smoke.

Full upstream Pandoc runner parity remains a separate upstream-runner dependency task requiring hydrated pinned upstream sources and Haskell test executables.
