## real-upstream-corpus-select-core-dynamic-20260531T024040Z-0

Base accepted HEAD: `47e43ea345c857243140b52082e7a664319c5aa0`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select5.test`
- Scenario: `select5-9.1`, `GROUP BY a, abs(b)` with `NULL` values and `quote()` projection.

Behavior moved:

- `SQLiteGroupedAggregate::summarize()` now orders GROUP BY summary buckets by SQLite value ordering of the group keys instead of preserving first-seen input row order.
- Added one exact `select5-9.1` assertion and four dynamic variants for NULL, zero, signed `ABS()` keys, repeated numeric keys, and composite expression groups.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicTest.php`
- Result: `1 test files, 8950 assertions, 0 failures`

Dependency closure:

- No new support component required. This reuses the existing `SQLiteSelectSql`, `SQLiteSelectExpression`, and `SQLiteGroupedAggregate` execution path.

Non-overlap:

- Does not touch accepted SELECT GROUP BY/HAVING text dispatch, expression ORDER BY, subquery execution, JSON table cursor/source behavior, WAL/VFS/B-tree clusters, or source-neutral cleanup surfaces.
