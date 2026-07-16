# real-upstream-corpus-window-functions-dynamic-20260530T234950Z-0

Added `SQLiteRealUpstreamWindowPushdownSelectSqlDynamicTest.php` as an
additive real upstream window-function corpus batch.

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/windowpushd.test`
  - `windowpushd.test` `1.0-1.4`: row_number view over indexed `grp_id`, then
    outer equality predicate.
  - `windowpushd.test` `2.0-2.1.3.6`: window views with partitioned `max()`
    and outer range predicates.

Behavior covered:

- 500 dynamic `SQLiteSelectSql` executions of
  `SELECT ... FROM (SELECT row_number() OVER (PARTITION BY grp_id) ...) WHERE
  grp_id = ?`.
- 500 dynamic `SQLiteSelectSql` executions of
  `SELECT ... FROM (SELECT max(score) OVER (PARTITION BY grp_id) ...) WHERE
  score >= ? AND score <= ?`.
- 1 source-citation/non-overlap case.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowPushdownSelectSqlDynamicTest.php`
  - Result: `1 test files, 1001 assertions, 0 failures`
  - PASS-line growth: `+1001`

Non-overlap:

- This does not repeat the already accepted helper-level
  `SQLiteRealUpstreamWindowPushdownDynamicTest.php` or
  `SQLiteRealUpstreamWindowPushdownLargeDynamicTest.php` coverage. Those files
  validate row-array/window helper behavior. This slice exercises parser-level
  `SQLiteSelectSql` derived-table execution with outer predicates and bound
  parameters against the same real upstream `windowpushd.test` behavior.
- It avoids accepted `window4`, `window7`, `window8`, `window9`, `windowA`,
  `windowB`, `windowC`, `windowD`, `windowE`, `windowerr`, `windowfault`, and
  grouped SELECT/window batches.

Exclusion:

- `windowpushd.test` `2.1.4.1-2.1.4.3` grouped aggregate subqueries with
  multiple aggregate value columns remain a separate SQL `GROUP BY` planner
  follow-up. The current planner rejects that broader shape with
  `SQLite SELECT SQL GROUP BY supports one aggregate value column`.

Dependency closure:

- No new support component is needed. The slice reuses native `SQLiteSelectSql`
  parsing/execution, derived-table handling, bound parameters, and existing
  window function execution.
