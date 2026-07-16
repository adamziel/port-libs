# real-upstream-corpus-select-core-dynamic-20260601T000852Z-0

Base accepted HEAD: `9938ea0ca5f2430c11f7b91d23d2213507185488`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/select3.test`
- Ported scenarios: `select3-1.2`, `select3-1.3`, `select3-5.1`, `select3-5.2`

## Behavior

This slice ports upstream SELECT behavior where one aggregate SELECT or GROUP BY query uses aggregates over different value expressions, for example `min(n)`, `min(log)`, `sum(n)`, `sum(log)`, `avg(n)`, and `avg(log)` in the same projection or ORDER BY.

`SQLiteSelectSql` now keeps the existing single-value aggregate summary path for ordinary queries, but switches to argument-specific aggregate summary slots when a query needs multiple aggregate value expressions. The implementation reuses the existing grouped aggregate engine and filtered aggregate path; no new support component is required.

## Focused Coverage

- Added `SQLiteRealUpstreamSelect3MultiAggregateDynamic20260601T000852ZTest.php`.
- Adds 1001 TestRunner PASS cases and 4006 assertions from real upstream SELECT scenarios.
- Red-first result before the implementation:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3MultiAggregateDynamic20260601T000852ZTest.php`
  - `1 test files, 6 assertions, 1000 failures`
  - Failure blocker: `SQLite SELECT SQL GROUP BY supports one aggregate value column`

## Verification

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php` - passed
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamSelect3MultiAggregateDynamic20260601T000852ZTest.php` - passed
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3MultiAggregateDynamic20260601T000852ZTest.php` - `1 test files, 4006 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3MultiAggregateDynamic20260601T000852ZTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicFollowupTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreGeneratedAggregateNamesDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusSelectCoreDynamicBatch1Test.php lanes/libsqlite/tests/SQLiteRealUpstreamSelectCoreDynamicThousandTest.php lanes/libsqlite/tests/SQLiteRealUpstreamSelect3AggregateGroupDynamicTest.php` - `6 test files, 18907 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - `1 test files, 3 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite` - passed

## Non-Overlap

This patch does not touch accepted storage, JSON table, VFS, B-tree, expression ORDER BY, subquery, GROUP BY text, or source-neutral cleanup surfaces. It is a bounded upstream `select3.test` aggregate-expression parity slice.

Root harness: not run - isolated micro-slice.
