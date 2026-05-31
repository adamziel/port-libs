# real-upstream-corpus-window-functions-dynamic-20260531T041330Z-0

- Slice: `real-upstream-corpus-window-functions-dynamic-20260531T041330Z-0`
- Base accepted HEAD: `6e668fbae83ee0543bff0a4aa8940cbc4e4fb4ca`
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`

## Ported Upstream Sections

- `window1.test` `14.0-14.1`: window `row_number()` inside an `IN` subquery survives subquery flattening and duplicated projection expressions.
- `window1.test` `15.0-15.2`: recursive-query window functions are rejected; correlated scalar subqueries preserve window aggregate partition state.
- `window1.test` `16.1-16.2`: `PARTITION BY b IN (SELECT rowid FROM ...)` partitions on subquery membership.
- `window1.test` `17.1-17.3`: unary-plus window aggregate expressions remain valid in result expressions and `ORDER BY`.

## Focused Evidence

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryPartitionDynamicTest.php`.
- Focused command:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SubqueryPartitionDynamicTest.php`
- Result:
  `1 test files, 10008 assertions, 0 failures`
- PASS-line growth:
  `1008` focused TestRunner PASS cases from one real upstream behavior cluster.

## Non-Overlap

This owns `window1.test` sections `14.0-17.3`. It avoids accepted window1 basic aggregate/view/trigger/sales/chained/range-mixed sections, `window2` frame-boundary rows, `window3/window4` ranking and value batches, `window5` custom aggregate behavior, `window6` value-function argument validation, `window7/window8` GROUPS/RANGE matrices, `window9`, `windowA` through `windowE`, `windowerr`, `windowfault`, `windowpushd`, JSON, WAL, VFS, B-tree, PRAGMA, trigger, and suite metadata rows. It adds no generated fake upstream script ids and no domain-specific API surface.

## Dependency Closure

No new support component is needed. The slice reuses lane-local `SQLiteWindowFunction` row-number, aggregate-frame, and row-array partition behavior over real upstream `window1.test` subquery and partition semantics.
