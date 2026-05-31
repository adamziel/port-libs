# real-upstream-corpus-select-core-dynamic-20260531T013805Z-0

Base accepted HEAD: `472430c1daaad1016852e97d68cabd3ea687d289`.

## Upstream Source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select6.test`
- `select6-11.1` through `select6-11.5`: aggregate columns from a derived
  FROM subquery are visible to scalar correlated subqueries in SELECT
  projection, WHERE, ORDER BY, and CASE contexts.
- `select6-11.100`: an empty aggregate derived subquery returns SQL NULL from
  the scalar correlated subquery.

## Implementation

- Updated `SQLiteSelectSql::groupBy()` so numeric `GROUP BY` ordinals resolve
  to the matching result expression.
- When the ordinal points to a result source column, the group uses that column
  directly. This preserves the grouped column for projection from derived
  aggregate subqueries such as `SELECT count(*) AS cnt, w AS xyz ... GROUP BY
  2`.

## Focused Coverage

- Added `SQLiteRealUpstreamSelect6CorrelatedAggregateDynamicTest.php`.
- New selected PASS lines: `1007`.
- Behavior assertions: `4035`.
- Dynamic coverage: `200` generic application seeds over all five upstream
  correlated aggregate contexts, plus canonical upstream rows and the empty
  aggregate NULL case.

## Non-Overlap

This owns only the `select6.test` correlated aggregate alias regression around
sections `11.1` through `11.5` and `11.100`. It does not repeat accepted
`select6` derived alias aggregate coverage for sections `1.x` through `4.x`,
negative LIMIT, SELECT1 nullable count, selectC/selectD/selectE/selectF/selectH
batches, expression ORDER BY, grouped SELECT text, JSON table source/cursor/
constraint work, storage clusters, or metadata-only runner rows.

Mapped denominator coverage remains `1589 / 1589`; this is PASS-line growth
against already mapped upstream inventory.

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect6CorrelatedAggregateDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6CorrelatedAggregateDynamicTest.php`
  - `1 test files, 4035 assertions, 0 failures`
  - `1007` PASS lines
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6DerivedAliasAggregateDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6DerivedDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect6CorrelatedAggregateDynamicTest.php`
  - `3 test files, 39401 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - not run: focused path does not exist in this worktree.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This reuses the lane-local SELECT SQL
planner/executor, grouped aggregate summarizer, scalar correlated subquery
execution, predicate filtering, ORDER BY evaluation, and CASE expression
helpers.
