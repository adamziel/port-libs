# VDBE Window Aggregate Current/Next 25

## Status

- Added `SQLiteVdbeWindowAggregateCursor` for a bounded VDBE-style window aggregate loop over sorted row arrays.
- The cursor sorts by partition and order keys, keeps `ROWS BETWEEN n PRECEDING AND n FOLLOWING` frames inside the current partition, supports SQL FILTER truthiness, and exposes current/next aggregate values without parser-level SELECT changes.
- Added focused tests and a Application smoke for copied `wp_options` window aggregate previews.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowAggregateCurrentNext25Test.php`
- `php lanes/libsqlite/examples/application-vdbe-window-aggregate-current-next25.php`
- `php -l lanes/libsqlite/src/SQLiteVdbeWindowAggregateCursor.php`
- `php -l lanes/libsqlite/tests/SQLiteVdbeWindowAggregateCurrentNext25Test.php`
- `php -l lanes/libsqlite/examples/application-vdbe-window-aggregate-current-next25.php`
- `git diff --check -- lanes/libsqlite`

## Non-overlap

This slice avoids accepted parser-level SELECT SQL text, GROUP BY/HAVING, expression ORDER BY, JSON table source/cursor/constraint work, VFS writer/lock/sync work, WAL checkpoint/savepoint byte truncation, B-tree page move/root collapse/overflow freelist release, Unicode GLOB, VDBE aggregate DISTINCT cursors, and sorter DISTINCT group cursors. It is limited to VDBE-style window aggregate current/next frame iteration.

## Dependency Closure

No new support component is needed. The implementation reuses existing lane-local sort comparison, numeric aggregate, and text aggregate primitives.
