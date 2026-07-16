# real-upstream-corpus-window-functions-dynamic-20260530T165043Z-0

- Base accepted HEAD: `a84cc0bc40ea83098a1549736f91208ad366e490`.
- Upstream source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` and `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`.
- Ported scenarios: `window2` bounded `ROWS BETWEEN n PRECEDING AND m FOLLOWING` aggregate windows over whole and partitioned rowsets; `window1` lead offsets/defaults and frame-tolerant `row_number()`/`lead()` named-window behavior.
- Focused behavior: `SQLiteSelectSql` executes dynamic generated application rowsets with `sum`, `count(*)`, and `group_concat` window frames across 10 upstream-shaped row variants, 3 partition shapes, 2 directions, and 3 frame bounds, checked against an independent PHP oracle.
- Behavior fix: frame-agnostic built-in window functions (`row_number`, ranking/distribution functions, `ntile`, `lag`, `lead`) now tolerate named or inline frame clauses instead of rejecting them before dispatch. Value functions and aggregate windows keep their existing frame semantics.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicRowsTest.php` passed `1 test files, 1625 assertions, 0 failures` with `182` PASS lines.
- Expected status movement: `phpPass` +182, from `198691` to `198873`; mapped coverage unchanged at `958 / 1589` because this slice does not claim new denominator rows.
- Dependency closure: no new support component needed; this reuses lane-local `SQLiteSelectSql`, `SQLiteSelectQuery`, and `SQLiteWindowFunction`.
- Non-overlap: avoids accepted static `window2/window3` ROWS/RANGE/GROUPS frame handoffs by adding dynamic generated ROWS-frame SELECT SQL coverage and the upstream `window1.test` built-in frame-tolerance fix.
