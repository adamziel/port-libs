# SQLite VDBE window FILTER RANGE EXCLUDE CURRENT ROW current-next53

2026-05-27 isolated slice `yield-sqlite-vdbe-window-filter-range-exclude-current-next53`.

## Behavior

- Adds `SQLiteVdbeWindowAggregateCursor::countFilteredAll()` so VDBE-style
  aggregate FILTER gates can be observed for `count(*)`-style window steps
  without changing the existing unfiltered `countAll()` diagnostic.
- Adds focused coverage for `RANGE BETWEEN CURRENT ROW AND n FOLLOWING
  EXCLUDE CURRENT ROW` over duplicate peer groups, numeric following
  boundaries, partition breaks, SQL truthiness filters, NULL payloads, and
  EXCLUDE NO OTHERS/GROUP/TIES comparisons.
- Adds a copied Application `wp_options` smoke for autoloaded option previews
  where the current row is excluded before FILTER-gated aggregate stepping.

## Verification

- `php -l lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php`
- `php -l lanes/libsqlite/tests/SQLiteVdbeWindowFilterRangeExcludeCurrentNext53Test.php`
- `php -l lanes/libsqlite/examples/application-vdbe-window-filter-range-exclude-current-next53.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowFilterRangeExcludeCurrentNext53Test.php`
- `php lanes/libsqlite/examples/application-vdbe-window-filter-range-exclude-current-next53.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Non-Overlap

This does not repeat accepted parser-level SELECT window text, VDBE GROUPS
FILTER/EXCLUDE current-next37, VDBE RANGE peer current-next34, JSON table
source/cursor/constraint work, VFS writer/lock/sync/rollback clusters, B-tree
page/root/overflow clusters, SQL expression ORDER BY, subqueries, grouped
SELECT text, or Unicode GLOB ranges. The new surface is the VDBE cursor
observable FILTER step count for RANGE current-to-following frames after
`EXCLUDE CURRENT ROW`.

Dependency closure: no new support component is needed; this reuses existing
native PHP VDBE sort comparison and aggregate helper components.
