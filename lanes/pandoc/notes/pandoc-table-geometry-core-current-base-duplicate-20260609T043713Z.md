# Pandoc Table Geometry Row Border Presentation Slice

Slice: `pandoc-table-geometry-core-current-base-duplicate-20260609T043713Z`
Base: `07a72489fb26b6c1406952193d9f53ff0495c0b3`

## Behavior

Implemented bounded HTML table row border presentation handoff for `tr`
attributes and styles. `TableGeometry::reviewPacket()` now records row-level
border color, style, width, edge-specific border attributes, row and global-row
coordinates, section membership, and writer downgrade diagnostics for Markdown,
AsciiDoc, and LaTeX. WordPress output continues to preserve the safe source row
style attributes.

This is intentionally distinct from the already accepted row background,
table-level border presentation, cell border presentation, and cell side-border
presentation slices.

## Evidence

Red-first focused probe before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
FAIL normalizes html table row border presentation metadata for geometry and wordpress handoff
Expected: 3
Actual: 0
1 test files, 1038 assertions, 1 failures
```

Final focused checks:

```text
php -l lanes/pandoc/src/TableGeometry.php
No syntax errors detected in lanes/pandoc/src/TableGeometry.php

php -l lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
No syntax errors detected in lanes/pandoc/tests/TableGeometryReaderHandoffTest.php

php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'
pandoc json ok

php tools/run-tests.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
1 test files, 1087 assertions, 0 failures

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 2975 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok

git diff --check -- lanes/pandoc
passed with no output
```

Status delta:

- `phpPass`: `2310 -> 2311`
- `benchmarkDenominator.mapped`: `2710 -> 2711`
- `mappedTableGeometryCoreCases`: `9 -> 10`
- `tableGeometryCoreAssertions`: `155 -> 204`

## Dependency Closure

No new support component is required. The slice reuses native PHP
`MarkdownReader` HTML table parsing, `TableGeometry` section-grid and coverage
helpers, `WordPressBlockWriter` safe row attribute preservation, and the existing
focused PHP test runner. Full upstream Pandoc runner parity remains a separate
upstream-runner dependency task that would require hydrated pinned upstream
sources and Haskell test executables.

## Follow-Up

Potential non-overlapping table-geometry follow-up: rowgroup style inheritance,
column-group border presentation, caption-side placement, or additional writer
downgrade coverage not already covered by row backgrounds, row borders, cell
backgrounds, cell borders, table borders, duplicate headers, or body row-head
columns.

Root harness not run: isolated micro-slice.
