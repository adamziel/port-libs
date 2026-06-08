# Pandoc table geometry current-base width-layout slice

Session: `port-dev-pandoc-table-geometry-20260608T210010Z`
Micro-slice: `pandoc-table-geometry-core-current-base-20260608T210010Z`
Base accepted HEAD: `5d4304c18bb1f0b3ffb02f52a119f3462fac3ca7`

## Behavior

This slice adds bounded HTML table width layout handoff support:

- `TableGeometry` now normalizes valid source `<table width="...">` attributes into a `tableLayout` review-packet record.
- Accepted width values are positive pixel integers `1..9999` and percentages `> 0` through `100`, normalized before metadata or WordPress output.
- Unsafe/non-bounded width expressions such as `calc(100% - 1px)` do not produce `tableLayout` metadata and are not rendered as WordPress table attributes.
- Markdown, AsciiDoc, and LaTeX writer review packets now include explicit `table-layout-width` diagnostics when a source table width needs writer review or raw HTML preservation.
- `WordPressBlockWriter` now emits safe normalized table `width` attributes through the existing table attribute sanitizer.

## Source Truth And Non-Overlap

The local upstream Pandoc checkout was absent from `/home/claude/port-libs/.upstream-cache/pandoc` in this isolated worker, so the bounded source truth for this slice is the existing lane contract for HTML-reader table attribute capture, table geometry review packets, and WordPress table handoff behavior.

This slice does not overlap the accepted table-geometry footer-section, block-cell, RST grid-table, header-abbreviation, row-group, global-row, frame/rules/border, cellpadding/cellspacing, directionality, colgroup, decimal-alignment, header-axis, summary, flat-grid, covered-slot, missing-slot, or rowspan-zero provenance slices.

## Evidence

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 626 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1679 assertions, 0 failures`

Red-first after adding the focused width-layout test and before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 631 assertions, 1 failures`
  - Failure was the missing `tableLayout` metadata packet.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 655 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 2334 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

Required final checks run for handoff:

- `php -l lanes/pandoc/src/TableGeometry.php`
  - `No syntax errors detected in lanes/pandoc/src/TableGeometry.php`
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - `No syntax errors detected in lanes/pandoc/src/WordPressBlockWriter.php`
- `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - passed with no output

## Status Delta

- `lane-status.json` `phpPass`: `1845 -> 1846`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2269 -> 2270`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 184`
- Focused table geometry family assertions: `2305 -> 2334` (`+29`)

## Dependency Closure

No new support component is needed. The slice reuses native PHP `MarkdownReader` table attribute capture, `TableGeometry` review-packet/writer diagnostics, and `WordPressBlockWriter` safe table attribute rendering. No Pandoc, Cabal solver/build/test command, Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Next

A follow-up table-geometry slice should choose a non-overlapping layout or writer-handoff gap, such as table height/background provenance or importer-side raw-HTML fallback diagnostics, without repeating accepted width/frame/spacing/direction/colgroup/decimal/header/summary/grid slices.
