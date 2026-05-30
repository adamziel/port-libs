# VDBE Window FILTER/EXCLUDE Frame Current/Next55

Status delta: added a bounded VDBE-style current/next aggregate snapshot for
window frames. `SQLiteVdbeWindowAggregateCursor::currentNextAggregateSummary()`
reports current and next row frame rowids, filtered frame rowids, aggregate
outputs, value-window outputs, and row payloads without advancing the cursor.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteVdbeWindowFilterExcludeFrameCurrentNext55Test.php`
  -> `1 test files, 64 assertions, 0 failures` with 64 PASS lines.

Application smoke:

- `php lanes/libsqlite/examples/application-vdbe-window-filter-exclude-frame-current-next55.php --self-test`
  -> `application-vdbe-window-filter-exclude-frame-current-next55 self-test passed`.

Non-overlap: this avoids accepted parser-level SELECT SQL window frame work,
GROUP BY/HAVING text, expression ORDER BY, JSON table source/cursor/constraint
work, VFS writer/lock/sync/rollback clusters, WAL byte/checkpoint/savepoint
clusters, B-tree page/root/overflow clusters, Unicode GLOB, and earlier VDBE
window current/next basics. The narrower surface is aggregate current/next
state after FILTER and EXCLUDE frame application.

Dependency closure: no new support component is needed. The slice reuses the
existing lane-local VDBE sorter comparison, SQL FILTER truthiness, numeric
aggregate, and text aggregate helpers.
