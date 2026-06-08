# Pandoc Table Geometry Core Current Base: Cell Nowrap

Micro-slice: `pandoc-table-geometry-core-current-base-20260608T230000Z`

Accepted base: `d8ca989a03aa98e6028adc24e3edc39bb34ec9a6`

## Behavior

- Added bounded HTML table cell `nowrap` provenance to `TableGeometry::reviewPacket()` as `cellNoWraps`.
- Packet summaries now expose `hasCellNoWraps`, `cellNoWrapCount`, `cellNoWrapColumns`, and `cellNoWrapSections`.
- Markdown, AsciiDoc, and LaTeX downgrade diagnostics now report cell nowrap requirements for lossy writer handoff.
- WordPress table output preserves safe truthy source cell `nowrap` attributes as `nowrap="nowrap"` and drops explicit false-like values.

## Source and Non-Overlap

- Source truth is the existing lane-local HTML table reader/writer handoff shape for table layout, alignment, frame, spacing, directionality, background, and cell decimal alignment.
- This avoids accepted table geometry clusters for visual spans, section grids, row/global coordinates, source summaries, header associations, width/height, placement alignment, frame/rules/border, cellpadding/cellspacing, directionality, captions, footer sections, block cells, nested tables, empty tables, decimal alignment, and table background.
- No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, Word, LibreOffice, zip/unzip, browser renderer, online service, live provider test, or live-service provider test was run.

## Focused Verification

- Rework notes: no `port-pandoc-*.needs-lane-rework.md` existed for this slice.
- Baseline focused test before the patch: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 735 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 758 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- JSON validation: `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` passed.
- PHP lint: `php -l` passed for `TableGeometry.php`, `WordPressBlockWriter.php`, `TableGeometryReaderHandoffTest.php`, and `wordpress-table-geometry-handoff.php`.
- Whitespace check: `git diff --check -- lanes/pandoc` passed.

## Status Delta

- `lane-status.json` `phpPass`: `1950 -> 1951`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2370 -> 2371`.
- `mappedTableGeometryCoreCases`: `9 -> 10`.
- `tableGeometryCoreAssertions`: `155 -> 178`.

## Dependency Closure

No new support component is needed. This reuses native `MarkdownReader` HTML table attribute capture, `TableGeometry` coverage/review-packet diagnostics, and `WordPressBlockWriter` table serialization. Root harness not run - isolated micro-slice.
