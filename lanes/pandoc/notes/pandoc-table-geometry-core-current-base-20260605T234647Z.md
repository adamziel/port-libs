# Pandoc Table Geometry Row-Group Summary

Slice: `pandoc-table-geometry-core-current-base-20260605T234647Z`
Base accepted HEAD: `efecd2a4fb0c5195ad7f93389be8d6723c15a8cd`

## Behavior

`TableGeometry::reviewPacket()` now exposes a JSON-safe `rowGroups` list for
Pandoc table grouping metadata:

- `table-head`, each `table-body`, and `table-foot` group boundaries.
- Body `bodyIndex`, body-local head row counts, body row counts, row roles,
  `rowHeadColumns`, and source attributes.
- Packet summary counters for row-group count, body-group count, multiple body
  groups, body-local head rows, row-head groups, max row-head columns, and
  table-foot rows.

This is additive metadata for importer/reviewer handoff. It does not change
slot layout, span normalization, WordPress rendering, Markdown writer fallback,
RST requirement diagnostics, or accessibility header generation.

## Evidence

Red-first:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
- Result before implementation: `1 test files, 684 assertions, 1 failures`
- Failure: row-group packet sections expected `head/body/body1/foot`, actual
  empty list because `rowGroups` was not present.

After implementation:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 711 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 314 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`

Manifest/status movement:

- `phpPass`: `1113 -> 1114`
- `benchmarkDenominator.mapped`: `1565 -> 1566`
- Added one mapped native table-geometry row-group summary case.
- Focused assertion growth across changed table tests: `989 -> 1025`.

## Dependency Closure

No new support component was needed. The slice reuses native PHP `AstNode`,
`TableGeometry`, `MarkdownReader` table handoff, and `WordPressBlockWriter`
smoke paths.

Full upstream Pandoc runner parity remains blocked on a hydrated Pandoc checkout
and Cabal/Haskell runner build; no Pandoc, Cabal solver/build/test command,
Haskell runner, external writer, office tool, online sanitizer, online service,
or live provider test was executed.

## Non-Overlap

This slice avoids the already accepted table-geometry surfaces for visual spans,
colspecs, section-scoped rowspans, declared-column overflow diagnostics,
nested-table rollups, block-cell writer diagnostics, RST writer requirements,
rowHeadColumns rendering, body-local head-row slot metadata, and accessibility
header attributes. It adds only first-class review-packet row-group summaries.

## Next

Keep writer-specific row-group rendering policies, DOCX/ODF row-group provenance
import, and full upstream Pandoc runner parity as separate bounded slices.
