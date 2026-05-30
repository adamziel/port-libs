# sql-aggregate-window-order-current-next72

Status: focused PHP behavior growth for VDBE-style window aggregate `ORDER BY` input sorting.

This slice adds `SQLiteVdbeWindowAggregateCursor::currentNextOrderedAggregateSummary()`. It keeps the existing window scan order for frame membership, applies EXCLUDE/FILTER through `currentFrameRows(true)`, then sorts the aggregate input rows by a separate aggregate `ORDER BY` key list before computing `group_concat`, numeric aggregate summaries, and current/next rowid diagnostics. The current cursor position is restored after peeking the next row.

Application smoke:

- `php lanes/libsqlite/examples/application-sql-aggregate-window-order-current-next72.php --self-test`
- Scenario: copied `wp_options` import rows use a window frame ordered by import sequence while `group_concat(option_group ORDER BY priority, option_name) FILTER (WHERE ok)` uses a separate aggregate sorter for current and next frames, without `ext/sqlite`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSqlAggregateWindowOrderCurrentNext72Test.php`
- Result: `1 test files, 52 assertions, 0 failures`.

Non-overlap:

- Avoids accepted parser-level GROUP BY/HAVING SQL text, SQL expression ORDER BY result sorting, `count(DISTINCT)` aggregate parsing, JSON aggregate/window object coverage, JSON table source/cursor/constraint work, VFS/WAL transaction application, B-tree page/freelist clusters, and Unicode GLOB behavior.
- The new surface is the VDBE current/next aggregate-input sorter used inside a window frame, where aggregate `ORDER BY` is distinct from the window `ORDER BY`.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP VDBE sort comparator, SQL FILTER truthiness, numeric aggregate helpers, text aggregate helpers, and window frame cursor.
