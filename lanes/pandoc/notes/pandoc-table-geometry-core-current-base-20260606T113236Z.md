# Pandoc Table Geometry Multiple Body Writer Handoff

Slice: `pandoc-table-geometry-core-current-base-20260606T113236Z`
Lane: `pandoc`
Base accepted HEAD: `454eb2e80ab750c1392b21e50662320bbde7c428`

## Behavior

`TableGeometry` now records non-HTML writer handoff diagnostics when a Pandoc
table has multiple non-empty `table_body` groups. WordPress/HTML handoff still
preserves separate `<tbody>` sections, while Markdown, AsciiDoc, and LaTeX
review packets now carry explicit downgrade/review records instead of hiding
the row-group boundary loss.

Writer diagnostic codes:

- `markdown-table-bodies-flattened` with required feature `body-row-group-boundaries`.
- `asciidoc-table-bodies-review-required` with required feature `table-body-groups`.
- `latex-table-bodies-review-required` with required feature `longtable-body-group-review`.

Each diagnostic includes the caption, column count, section count, total row
count, body count, head/body/foot row counts, body section ids, body section row
counts, and the full section summary already used by the table geometry review
packet.

## Source Truth

The mapped upstream behavior comes from the pinned static Pandoc inventory for
`test/html-reader.html`, `test/html-reader.native`, and `test/tables/nordics.html5`
where HTML tables can carry multiple table body sections and Pandoc AST table
handoff keeps body section identity. This slice ports the bounded format
contract in native PHP and does not attempt full writer parity.

## Status Delta

- `phpPass`: `1317 -> 1318`.
- `benchmarkDenominator.mapped`: `1731 -> 1732`.
- `mappedTableGeometryCoreCases`: `7 -> 8`.
- `tableGeometryCoreAssertions`: `105 -> 143`.
- Focused `TableGeometryTest.php`: `1033 -> 1071` assertions.

## Verification

Baseline before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1033 assertions, 0 failures`

Red-first evidence after adding the behavior:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1070 assertions, 1 failures`
  - Failure was the new Markdown output assertion expecting unpadded `| Posts | 42 |`; the native Markdown writer right-aligns numeric cells as `| Posts |    42 |`.

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php`
  - `1 test files, 1071 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `1 test files, 341 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `2 test files, 1412 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test`
  - `table geometry handoff self-test ok`
- `php -l lanes/pandoc/src/TableGeometry.php`
  - `No syntax errors detected in lanes/pandoc/src/TableGeometry.php`
- `php -l lanes/pandoc/tests/TableGeometryTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php`
- `php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/TableGeometryReaderHandoffTest.php`
- `php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php`
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- `git diff --check -- lanes/pandoc`
  - clean

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP `AstNode`,
`TableGeometry`, `MarkdownWriter`, `WordPressBlockWriter`, `MarkdownReader`,
the existing HTML/table reader fixtures, and the focused PHP harness.

No Pandoc, Cabal build, Haskell runner, external writer, Word, LibreOffice,
zip/unzip, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Non-overlap

This does not repeat accepted table span layout, row-head column handling,
body-local head rows, section-scoped rowspans, declared-column overflow,
section grids, header associations, footer writer diagnostics, caption/source
attributes, nested-table rollups, or block-cell writer diagnostics. The new
surface is specifically multiple body group writer handoff metadata.

## Follow-up

Keep actual Markdown/AsciiDoc/LaTeX multiple body rendering mitigation, richer
writer syntax policy, DOCX/ODF row-group provenance, and full upstream
Pandoc/Haskell runner comparison as separate bounded slices.
