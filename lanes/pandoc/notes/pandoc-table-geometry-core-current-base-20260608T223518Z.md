# Pandoc Table Geometry Core Current Base: Table Background

Micro-slice: `pandoc-table-geometry-core-current-base-20260608T223518Z`

Accepted base: `c5c14bd99fa330d27c77e6af2133453dccf48a5a`

## Behavior

- Added bounded HTML table background provenance to `TableGeometry::reviewPacket()` for safe table-level `bgcolor` and CSS `background-color`.
- Packet summaries now expose `hasTableBackground`, effective background color, source (`style` over `bgcolor`), and attribute count.
- Markdown, AsciiDoc, and LaTeX downgrade diagnostics now report table-background requirements for lossy writer handoff.
- WordPress table output preserves normalized safe table background colors and drops unsafe table-level background CSS/expression values.

## Source and Non-Overlap

- Source truth is the existing lane-local HTML table reader/writer handoff shape for table layout, alignment, frame, spacing, and directionality.
- This avoids accepted table geometry clusters for visual spans, section grids, row/global coordinates, source summaries, header associations, width/height, placement alignment, frame/rules/border, cellpadding/cellspacing, directionality, captions, footer sections, block cells, nested tables, and empty tables.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, Word, LibreOffice, zip/unzip, browser renderer, online service, live provider test, or live-service provider test was run.

## Focused Verification

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` existed for this slice.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` failed with `1 test files, 708 assertions, 1 failures` because `tableBackground` metadata was absent.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 735 assertions, 0 failures`.
- Focused family test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `2 test files, 2414 assertions, 0 failures`.
- Regression guard for table-level WordPress style order: `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php` passed with `1 test files, 3247 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` passed.
- PHP lint: `php -l` passed for `TableGeometry.php`, `WordPressBlockWriter.php`, `TableGeometryReaderHandoffTest.php`, and `wordpress-table-geometry-handoff.php`.

## Status Delta

- `lane-status.json` `phpPass`: `1928 -> 1929`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2350 -> 2351`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 182`.

## Dependency Closure

No new support component is needed. This reuses native `MarkdownReader` HTML table attribute capture, `TableGeometry` review-packet/writer diagnostics, and `WordPressBlockWriter` table serialization. Root harness not run - isolated micro-slice.
