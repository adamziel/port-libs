# real-upstream-corpus-select-core-dynamic-20260601T011723Z-0

Base accepted HEAD: `6025aa0c35dc17d20b1c6c068ec52bbef5bf715c`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select4.test`
- Ported scenarios: `select4-17.1`, `select4-17.2`, and `select4-17.3`

## Behavior

This slice ports the `select4.test` aggregate/non-aggregate compound-subquery
pushdown regression. The dynamic corpus varies generic `t1(a,b)` table images,
aggregate sums, filter thresholds, and compound arm order while preserving the
upstream shape:

- `SELECT x, y FROM (constant arm UNION grouped aggregate arm) AS w WHERE y>=... ORDER BY +x`
- `SELECT x, y FROM (grouped aggregate arm UNION constant arm) AS w WHERE y>=... ORDER BY +x`
- invalid first-arm `LIMIT` before `UNION`, matching the upstream diagnostic

The focused assertions verify that the outer `WHERE` is applied to the derived
compound result and is not pushed into the grouped aggregate arm.

## Focused Coverage

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundAggregatePushdownDynamic20260601T011723ZTest.php`.
- Adds `1002` TestRunner PASS cases and `12010` behavior assertions.
- No production source change was needed; the current `SQLiteSelectSql`
  compound subquery, grouped aggregate, outer filter, `ORDER BY +x`, and
  misplaced `LIMIT` diagnostic paths already support this upstream section.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundAggregatePushdownDynamic20260601T011723ZTest.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundAggregatePushdownDynamic20260601T011723ZTest.php`
  - `1 test files, 12010 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundAggregatePushdownDynamic20260601T011723ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4AggregateJoinDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4CompoundDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect4MaterializedCompoundDynamic20260531Test.php`
  - `4 test files, 53335 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed

## Non-Overlap

This avoids existing select4 coroutine/yield coverage (`select4-15.1`),
select4 aggregate subquery join coverage (`select4-16.1` through `16.3`),
select4 compound CTAS/materialization coverage, grouped SELECT text, expression
`ORDER BY`, JSON table SELECT sources, and storage/VFS clusters.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local
`SQLiteSelectSql` support for compound SELECT subqueries, grouped aggregate
execution, outer `WHERE` predicates, unary-plus `ORDER BY`, and compound
placement diagnostics.

Root harness: not run - isolated micro-slice.
