# real-upstream-corpus-select-core-dynamic-20260601T153303Z-0

Lane: libsqlite

Base accepted HEAD: 58f1b15e81ee03d64915f36a0a94fc3dd31fae09

## Source truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select1.test`
- `select1-18.3`: nested correlated scalar subqueries with an innermost `SELECT x FROM (VALUES(0))` branch.
- `select1-18.4`: the same nested scalar predicate over a `t1, t2` source list, preserving outer row multiplicity.
- `select1-20.10`: `JOIN t1 USING(a,b)` with a grouped scalar subquery predicate and an `OR a = ...` equality escape.

## Patch

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamSelect1NestedScalarDynamic20260601T153303ZTest.php`.
- The test keeps the upstream SQL shapes visible and exercises 1000 deterministic dynamic cases over `SQLiteSelectSql`.
- The batch verifies scalar truthiness, outer-column correlation, nested `VALUES(0)` source handling, source-list row multiplicity, self-join `USING` projection, and grouped scalar subquery predicate routing.

## Countability

- Focused PASS cases added: 1003.
- Focused assertions: 15019.
- `lane-status.json` `phpPass` moves `5978211 -> 5979214`.
- `benchmarkDenominator.mapped` is unchanged because this adds focused PHP corpus coverage, not a newly mapped upstream manifest unit.

## Non-overlap

This slice avoids accepted grouped SELECT SQL text, expression `ORDER BY`, JSON table source/cursor/constraint work, WAL/VFS/B-tree storage clusters, source-neutral cleanup, and metadata-only runner admission. The selected upstream subtests are the `select1.test` nested scalar and `JOIN USING` scenarios listed above, not the already accepted `selectA` reversed UNION ORDER BY or broad SELECT parser clusters.

## Dependency closure

No new support component is needed. The slice reuses current `SQLiteSelectSql` support for correlated scalar subqueries, `VALUES` sources, `JOIN ... USING`, grouped scalar subqueries, and predicate truthiness.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect1NestedScalarDynamic20260601T153303ZTest.php`
- Result: `1 test files, 15019 assertions, 0 failures`.
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect1NestedScalarDynamic20260601T153303ZTest.php`
- Result: `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamSelect1NestedScalarDynamic20260601T153303ZTest.php`.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- Result: `lane-status json ok`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Result: `1 test files, 7 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite`
- Result: clean.
- Root harness: not run - isolated micro-slice.
