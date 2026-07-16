# VDBE window sorter affinity current-source next

## Scope

Adds a bounded current-source VDBE sorter/window handoff that composes the
accepted affinity/collation sorter source plan with window frame summaries and
records the `OP_SorterData`/`OP_SorterNext` loop shape. The new plan reports
the current row, next row, sorter record, frame rowids, filtered rowids, and
aggregate values before the sorter advances.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowSorterAffinityCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/application-vdbe-window-sorter-affinity-current-source-next.php`
- `php -l lanes/libsqlite/src/SQLiteVdbeWindowSorterAffinityCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteVdbeWindowSorterAffinityCurrentSourceNextTest.php`
- `php -l lanes/libsqlite/examples/application-vdbe-window-sorter-affinity-current-source-next.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap

This avoids accepted next sorter/window frame recalculation, DISTINCT
sorter, expression `ORDER BY`, standalone window frame/filter/exclude,
compound SELECT/window, JSON, WAL, B-tree, and VFS clusters. The new behavior
is the current-source sorter loop diagnostic over sorter-fed window frames.

## Dependency closure

No new support component is needed. The slice reuses lane-local native PHP
VDBE sorter yield, affinity/collation comparison, and window aggregate cursor
primitives.
