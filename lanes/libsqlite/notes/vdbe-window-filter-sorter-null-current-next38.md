# VDBE Window Filter Sorter NULL Current/Next 38

## Status

- Extended `SQLiteVdbeWindowAggregateCursor` with current FILTER register
  visibility, FILTER pass/fail reporting, and non-advancing next row/order-key
  peeks for VDBE-style current/next loops.
- Added `SQLiteVdbeWindowFilterSorterNullCurrentNext38Test.php` with 51 focused
  PASS cases over NULL sort keys, NOCASE/RTRIM ordering, false/NULL FILTER
  rows that are still yielded as current rows, filtered aggregate contributors,
  EOF behavior, and invalid FILTER register values.
- Added a Application smoke for copied `wp_options` window diagnostics where
  inactive rows remain visible in the sorter cursor but are omitted from
  aggregate frame contribution.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowFilterSorterNullCurrentNext38Test.php`
  -> `1 test files, 51 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-vdbe-window-filter-sorter-null-current-next38.php --self-test`
  -> `application-vdbe-window-filter-sorter-null-current-next38 self-test passed`.
- `php -l lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php`
  -> no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteVdbeWindowFilterSorterNullCurrentNext38Test.php`
  -> no syntax errors.
- `php -l lanes/libsqlite/examples/application-vdbe-window-filter-sorter-null-current-next38.php`
  -> no syntax errors.

## Non-Overlap

This avoids accepted VDBE sorter NULL/collation yield diagnostics, VDBE window
aggregate current/next basics, RANGE/GROUPS frame work, JSON aggregate window
FILTER regression, parser-level SELECT SQL window/GROUP BY/JOIN/subquery
clusters, JSON table source/cursor/constraint work, VFS/WAL/B-tree accepted
clusters, and Unicode GLOB behavior. The narrower surface is VDBE window
aggregate current/next observability when the current row has false or NULL
FILTER state while NULL sorter keys still determine row order.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
VDBE sort comparator plus numeric/text aggregate helpers.
