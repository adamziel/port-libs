# Pandoc Table Geometry Cell Decimal Alignment Core Slice

- Base accepted HEAD: `e65d6824c4b52805d383debe5763a0de4e4f464d`
- Micro-slice: `pandoc-table-geometry-core-current-base-20260608T130152Z`
- Scope: preserve HTML source `align="char"`/`char`/`charoff` metadata on `th` and `td` cells, expose it in table geometry review packets, report non-HTML writer downgrade requirements, and retain safe WordPress table-cell attributes.

## Source Truth

- Bounded support-library behavior follows the existing lane table-geometry contract and the already mapped upstream HTML/table fixture family. This slice is deliberately cell-scoped and does not duplicate the accepted colgroup/column decimal alignment handoff.
- No Pandoc, Cabal, Haskell runner, external writer, browser renderer, online service, live provider test, or live-service provider test was executed.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` failed after adding the new case with `1 test files, 499 assertions, 1 failures` because source cell `align="char"` metadata was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php` passed with `1 test files, 531 assertions, 0 failures`.
- WordPress smoke: `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test` passed with `table geometry handoff self-test ok`.
- Assertion delta: +32 focused assertions.
- Lane status delta: `phpPass` 1646 -> 1647.
- Manifest delta: mapped denominator 2066 -> 2067; `mappedTableGeometryCoreCases` 9 -> 10; `tableGeometryCoreAssertions` 155 -> 187.

## Dependency Closure

No new native PHP support component is needed. The slice reuses `MarkdownReader`, `TableGeometry`, `WordPressBlockWriter`, `TableGeometryReaderHandoffTest.php`, and the existing WordPress table geometry example smoke.

## Non-Overlap

This slice avoids the accepted table column decimal alignment cluster. Column-level `<colgroup>/<col align="char">` records remain under `columnDecimalAlignments`; this patch adds separate `cellDecimalAlignments` and `decimalAlignment` coverage records for source `th`/`td` cells only.

## Next

A follow-up table geometry slice should choose a distinct remaining source table feature or writer fallback diagnostic, not another colgroup or cell decimal-alignment variant.
