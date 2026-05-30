# VDBE window GROUPS FILTER EXCLUDE current-next49

## Behavior

- Added `SQLiteVdbeWindowAggregateCursor::currentNextSummary()` for non-advancing current/next VDBE window yield diagnostics.
- Added `SQLiteVdbeWindowAggregateCursor::currentNextFrameRows()` so a caller can inspect the current row's frame and the next row's frame after `GROUPS`, `EXCLUDE CURRENT ROW`, and aggregate `FILTER` composition.
- Preserved existing aggregate methods and drain behavior; the new helpers temporarily evaluate the next row's frame and restore the current cursor position.

## Focused evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowGroupsFilterExcludeCurrentNext49Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 43 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-vdbe-window-groups-filter-exclude-current-next49.php --self-test
application-vdbe-window-groups-filter-exclude-current-next49 self-test passed
```

## Counter delta

- `phpPass`: `17920 -> 17963` from 43 new focused PASS assertions in `SQLiteVdbeWindowGroupsFilterExcludeCurrentNext49Test.php`.
- `benchmarkDenominator.mapped`: unchanged; this is focused PHP behavior coverage only.

## Non-overlap

This avoids accepted parser-level SELECT SQL window/GROUP BY/JOIN/subquery/expression ORDER BY work, JSON table source/cursor/hidden/visible constraint clusters, VFS/WAL/B-tree accepted clusters, VDBE aggregate ORDER BY cursors, existing GROUPS EXCLUDE/FILTER aggregate outputs, and filter/sorter NULL current-row summaries. The new surface is the missing VDBE-style current/next yield observation layer for GROUPS frames after `EXCLUDE CURRENT ROW` and `FILTER` composition.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP VDBE sort comparator plus numeric/text aggregate helpers.
