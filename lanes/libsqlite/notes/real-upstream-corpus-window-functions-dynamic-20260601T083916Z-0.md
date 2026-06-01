# real-upstream-corpus-window-functions-dynamic-20260601T083916Z-0

Base accepted HEAD: `56d05df2fec029b5e619e6a16107a698092a4221`

Source truth: `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`

Ported upstream behavior:

- `window1.test` 57.1, ticket `0899cf62f597d7e7`: NULL aggregate rows composed with `min(b) OVER ()` and `count(c) OVER (ORDER BY b)`.
- `window1.test` 57.2: `SELECT DISTINCT v1, lead(v1) OVER() FROM v0 GROUP BY v1 ORDER BY 2` keeps the single grouped row and NULL lead.
- `window1.test` 58.1, ticket `1f6f353b684fc708`: nested `sum(avg(678)) OVER (ORDER BY c)` and `sum(345+b) OVER (ORDER BY b)` collapse to the aggregate row.

Patch:

- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggregateNullDynamic20260601Test.php`.
- The test hydrates the upstream source file, asserts the exact upstream rows, and adds 1000 generic `app_metrics`, `app_values`, and `app_numbers` dynamic cases around the same aggregate/window regressions.
- Focused pass growth: 1005 TestRunner PASS cases and 9017 assertions.

Non-overlap:

- Avoids accepted window1 sections 14-17, 25-26, 28-29, 36, 42-43, 52, 66, and 78-79.
- Avoids the accepted `window4.test` 12.2 scalar aggregate subquery slice and all current non-window PRAGMA/WAL/B-tree/source-neutral surfaces.

Verification:

- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggregateNullDynamic20260601Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggregateNullDynamic20260601Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1AggregateNullDynamic20260601Test.php`
  - `1 test files, 9017 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

Dependency closure:

- No new support component is needed. The batch reuses existing `SQLiteSelectSql` aggregate/window execution, grouped single-row `lead()` handling, and nested aggregate-window expression dispatch.

Root harness:

- Not run; isolated micro-slice.
