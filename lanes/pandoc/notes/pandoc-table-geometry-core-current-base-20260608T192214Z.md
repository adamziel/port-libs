# Pandoc Table Geometry Legacy Frame/Rules Handoff

Slice: `pandoc-table-geometry-core-current-base-20260608T192214Z`
Base accepted HEAD: `e97bdf9331ef05dac3f6237d837a28df8dd53eb5`

## Behavior

This slice preserves legacy HTML table layout attributes that affect table
border geometry:

- `TableGeometry::reviewPacket()` now exposes a normalized `tableFrame` record
  for safe `frame`, `rules`, and `border` attributes.
- Markdown, AsciiDoc, and LaTeX writer diagnostics now report that legacy
  frame/rules/border geometry needs raw HTML or manual review.
- `WordPressBlockWriter` preserves the safe table-level legacy attributes on
  rendered `<table>` elements.

## Status Delta

- `phpPass`: `1750 -> 1751`
- `benchmarkDenominator.mapped`: `2166 -> 2167`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 172`
- New focused PASS case:
  `normalizes legacy html table frame rules and border geometry`
- New focused assertions in `TableGeometryReaderHandoffTest.php`: `+17`

## Evidence

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 548 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1671 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- `php -l lanes/pandoc/src/TableGeometry.php`
  - no syntax errors
- `php -l lanes/pandoc/src/WordPressBlockWriter.php`
  - no syntax errors
- `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - no syntax errors
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - no syntax errors

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The patch reuses
`MarkdownReader` HTML attribute capture, `TableGeometry` review packets and
writer diagnostics, and `WordPressBlockWriter` table rendering. Pandoc,
Cabal/Haskell runners, external writers, browser renderers, online services,
live provider tests, and live-service provider tests remain out of scope.

## Non-Overlap

This does not repeat accepted span normalization, section-boundary rowspans,
declared-column diagnostics, colgroup provenance/alignment, decimal alignment,
source-summary, caption-source, row/header associations, vertical alignment,
covered-slot replay, or writer section range slices. It is limited to legacy
HTML `frame`/`rules`/`border` table geometry metadata and writer handoff.

## Next

Choose a non-overlapping table-geometry follow-up such as writer-specific raw
HTML fallback rendering policy, additional legacy table layout metadata, or
importer-side grid replay validation.
