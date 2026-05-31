# real-upstream-corpus-window-functions-dynamic-20260531T005849Z-0

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window2.test`
  - `2.14`: `ROWS BETWEEN 3 PRECEDING AND 1 PRECEDING`
  - `2.16`: partitioned `ROWS BETWEEN 1 PRECEDING AND 1 PRECEDING`
  - `2.17`: partitioned empty `ROWS BETWEEN 1 PRECEDING AND 2 PRECEDING`
  - `2.19`: partitioned `ROWS BETWEEN 1 FOLLOWING AND 3 FOLLOWING`
  - `2.20`: `ROWS BETWEEN 1 FOLLOWING AND 2 FOLLOWING`
  - `2.21`: `ROWS BETWEEN 1 FOLLOWING AND UNBOUNDED FOLLOWING`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window6.test`
  - `5.5`: signed `+2 FOLLOWING` to `+3 FOLLOWING` window frame boundary.

Implementation:

- `SQLiteWindowFunction::parseFrameBoundary()` now accepts SQLite's signed positive frame offsets, matching the accepted SQL parser behavior for `+N PRECEDING/FOLLOWING`.
- `SQLiteSelectQuery` now dispatches `first_value`, `last_value`, and `nth_value` through the full `valueFrameBetweenValues()` evaluator when a parsed `BETWEEN ... AND ...` frame carries explicit start/end boundary strings. This preserves following-to-following and preceding-to-preceding value-window frames instead of collapsing them into old preceding/following offset fields.
- Added `SQLiteRealUpstreamWindowFollowingFramesDynamicTest.php` with 602 focused assertions over dynamic generic `t1` rowsets. It ports the upstream frame-boundary shapes above and verifies aggregate and value-window behavior through parser-level `SQLiteSelectSql` execution.

Non-overlap:

This batch does not repeat the accepted `window7.test` GROUPS/RANGE dynamic corpus, JSON table window ranking, parser-level JSON table sources, SELECT GROUP BY text, expression ORDER BY, or earlier window value tests. It owns signed positive frame boundaries and preceding/following `ROWS BETWEEN` frame semantics from `window2.test` and `window6.test`.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWindowFunction.php`: no syntax errors.
- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`: no syntax errors.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindowFollowingFramesDynamicTest.php`: no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFollowingFramesDynamicTest.php`: `1 test files, 602 assertions, 0 failures`.

Dependency closure:

No new support component is needed. The slice reuses the existing native PHP SELECT SQL parser/executor and window-frame evaluator; no SQLite extension, Tcl runner, or new fixture dependency is required.
