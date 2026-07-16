# Pandoc Table Geometry Row Group Range Handoff

Slice: `pandoc-table-geometry-core-current-base-20260607T100712Z`
Base: `9249a8421a3ff1980e89d00422073eb64b55016c`
Date: 2026-06-07 UTC

## Behavior

Implemented bounded row-group geometry metadata for native Pandoc table review
packets. `TableGeometry::rowGroups()` now assigns each emitted head/body/foot
group:

- ordered `ordinal` values;
- global visual row offsets through `globalRowStart`, `globalRowEnd`, and
  `rowRange`;
- `headerLikeRowCount` and `dataLikeRowCount` handoff counts;
- per-group `rowRoleCounts`;
- body-group `bodyOrdinal` metadata alongside the existing `bodyIndex`.

`TableGeometry::reviewPacket()` summary output now rolls those records into
`rowGroupRanges`, `rowGroupSections`, aggregate `rowRoleCounts`,
header/data-like row totals, and non-empty/empty/max row-group counters. This
keeps multi-body table handoff reviewable without forcing WordPress importers
to reconstruct section-relative row ranges from body/head/footer counts.

The slice is non-overlapping with the accepted table-span, row-header,
caption, footer/body-head writer diagnostic, nested-table, source-attribute,
source-header, and reader vertical-alignment handoffs. It reuses the existing
table AST and review-packet plumbing rather than introducing a new parser or
writer.

## Verification

Red-first focused check after adding assertions:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1224 assertions, 1 failures
```

Failure was the expected missing `globalRowStart` metadata in row-group
records.

Final focused checks:

```text
php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php
1 test files, 1267 assertions, 0 failures
```

The previous accepted table-geometry note recorded `TableGeometryTest.php` at
`1 test files, 1248 assertions, 0 failures`; this slice adds 19 focused
assertions and one mapped native table-geometry case.

Additional required checks are recorded in the final handoff output:

```text
php -l lanes/pandoc/src/TableGeometry.php
No syntax errors detected in lanes/pandoc/src/TableGeometry.php

php -l lanes/pandoc/tests/TableGeometryTest.php
No syntax errors detected in lanes/pandoc/tests/TableGeometryTest.php

php -l lanes/pandoc/examples/wordpress-table-geometry-handoff.php
No syntax errors detected in lanes/pandoc/examples/wordpress-table-geometry-handoff.php

php tools/run-tests.php lanes/pandoc/tests/TableGeometryTest.php lanes/pandoc/tests/TableGeometryReaderHandoffTest.php
2 test files, 1608 assertions, 0 failures

php lanes/pandoc/examples/wordpress-table-geometry-handoff.php --self-test
table geometry handoff self-test ok

jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json
passed with no output

git diff --check -- lanes/pandoc
passed with no output
```

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses native PHP
`TableGeometry` row-group construction, review-packet summary rollups, the
static table fixture inventory, focused table tests, and the WordPress table
geometry example. No Pandoc, Cabal solver/build/test command, Haskell runner,
external writer, browser renderer, online service, live provider test, or
live-service provider test was executed.

## Follow-Up

Future table-geometry work should stay on non-overlapping AST/WordPress
handoff gaps, such as caption placement edge cases, remaining writer-specific
span diagnostics, or reader-source vertical alignment edge cases not already
covered by the current HTML/DocBook handoff tests.
