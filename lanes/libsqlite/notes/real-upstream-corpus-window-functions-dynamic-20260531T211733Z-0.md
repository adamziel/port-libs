# real-upstream-corpus-window-functions-dynamic-20260531T211733Z-0

Base accepted HEAD: `3a3374ad59c06e8a3561833481036dd945373160`.

Ported upstream source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` section `6.1`: `SELECT x, count(*) OVER (ORDER BY x) FROM t1` emits result rows in the window sorter order even without an outer `ORDER BY`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test` section `6.2`: cross join against that window subquery keeps the sorted subquery rows before the outer `ORDER BY 1, 2`.

Implementation delta:

- `SQLiteSelectQuery` now applies a narrow implicit result ordering after window materialization and before projection when a SELECT has one shared non-partitioned window `ORDER BY` and no explicit SELECT `ORDER BY`, `GROUP BY`, `DISTINCT`, `LIMIT`, or `OFFSET`.
- The guard intentionally leaves partitioned windows, mixed window orderings, explicit outer ordering, grouped/distinct/limited SELECTs, and existing projection/order paths untouched.

Focused evidence:

- Red-first before source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SorterOutputDynamic20260531Test.php`
  failed with `1 test files, 1005 assertions, 1001 failures`; the first failure showed `window1.test 6.1` returning `7..1` input order instead of `1..7` window sorter order.
- After source fix:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SorterOutputDynamic20260531Test.php`
  passed with `1 test files, 5005 assertions, 0 failures`.
- Adjacent window/filter family:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1SorterOutputDynamic20260531Test.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowFunctionsDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicRowsTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AliasOrderDynamic20260531T122347ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow12FrameDynamicCorpusTest.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow9AggregateSubqueryDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterWindowDynamicTest.php`
  passed with `7 test files, 38356 assertions, 0 failures`.

Non-overlap:

- Avoids existing accepted `window1` explicit outer `ORDER BY` alias coverage (`43.2.2-43.2.4`), explicit frame/range dynamic rows, filter/window corpus, `window9` aggregate/subquery sections, and the zero-argument `count() FILTER` fix.
- This slice owns only the no-outer-ORDER window sorter-output behavior from `window1.test 6.1-6.2`.

Dependency closure:

- No new support component required; the patch reuses `SQLiteSelectSql`, `SQLiteSelectQuery`, `SQLiteSelectExpression`, and existing window materialization.
