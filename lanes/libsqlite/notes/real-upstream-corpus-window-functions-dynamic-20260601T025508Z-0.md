# real-upstream-corpus-window-functions-dynamic-20260601T025508Z-0

Base accepted HEAD: `515fa94ece8af5512b4751f4654c8d7fe66ba5ec`

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window4.test`
- Ported sections: `11.1`, `11.5`, `11.7`, `11.8`, `12.1`, and `12.3`.

Implemented behavior:

- `SQLiteSelectSql` now builds implicit aggregate plans for constant SELECT arms, which lets correlated scalar subqueries such as `SELECT avg(a)` participate in compound subqueries.
- `SQLiteSelectQuery` no longer lets sampled source columns overwrite already-computed implicit aggregate summary columns, preserving the inner `avg(a)` summary when the outer grouped row also has aggregate metadata.
- Added `SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T025508ZTest.php` with 1007 focused TestRunner PASS cases and 9021 behavior assertions over NTILE overflow buckets, nested aggregate window functions, scalar window subqueries, and compound scalar subqueries.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteSelectSql.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectSql.php`
- `php -l lanes/libsqlite/src/SQLiteSelectQuery.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteSelectQuery.php`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T025508ZTest.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T025508ZTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T025508ZTest.php`
  - `1 test files, 9021 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamCorpusWindowFunctionsDynamic20260601T025508ZTest.php lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php lanes/libsqlite/tests/SQLiteRealUpstreamFilterAggregateSelectSqlDynamic20260531Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `4 test files, 21105 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`
  - no whitespace errors

Non-overlap:

- This slice avoids accepted `window4.test` 4.5 tail frame batches, earlier NTILE/ranking coverage, JSON table/window rows, WAL/VFS/B-tree storage slices, and prior SELECT SQL subquery coverage.
- No dashboard/root publication files were edited.

Bounded exclusion:

- `window4.test` 12.2 remains a follow-up. SQLite lifts `SELECT (SELECT avg(a)) FROM t2 ORDER BY 1` into a single aggregate result over the outer rows (`2.0000`), while the current bounded executor still evaluates the scalar subquery per outer row. This patch does not weaken or fake that behavior.

Dependency closure:

- No new support component is needed. The patch reuses `SQLiteSelectSql`, `SQLiteSelectQuery`, existing aggregate summary helpers, and existing window-function execution.
