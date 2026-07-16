# Current Root Window Ranking Value Functions

Slice: `libsqlite-current-root-failures-window-ranking-value-functions-20260602T0204Z`

Source truth:
- Local SQLite oracle `sqlite3 :memory: "WITH t(x) AS (VALUES (1),(2)) SELECT x, lag(x,0,'d') OVER (ORDER BY x), lead(x,0,'d') OVER (ORDER BY x) FROM t;"` returns current-row values for zero offsets.
- Existing focused real-upstream coverage in `SQLiteRealUpstreamWindowDynamicCorpusTest.php` already asserts `lead([1, 2, 3], 0)` and `lag([1, 2, 3], 0)` return the current row.

Change:
- Updated the broad `SQLiteHeaderTest.php` window ranking/value-functions assertion to preserve zero-bucket `ntile()` and zero-index `nth_value()` rejection while asserting zero-offset `lag()` / `lead()` current-row behavior.
- No production implementation change was needed; `SQLiteWindowFunction` already matches SQLite and the focused upstream tests.

Evidence:
- Before: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` reported `1 test files, 9419 assertions, 9 failures`, including `dispatches sqlite builtin window ranking and value functions`.
- After: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php` reports `1 test files, 9423 assertions, 8 failures`; the window ranking/value-functions case passes.
- `php -l lanes/libsqlite/tests/SQLiteHeaderTest.php` reports no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindowDynamicCorpusTest.php` passes `1 test files, 635 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passes `1 test files, 10 assertions, 0 failures`.
- `git diff --check -- lanes/libsqlite` passes.

Dependency closure: no new support component needed; this reuses the existing native `SQLiteWindowFunction` value-offset helpers and focused real-upstream window corpus coverage.

Non-overlap: this slice only addresses the current root stale zero-offset `lag()`/`lead()` assertion. It does not touch WAL, B-tree, JSON, grouped aggregate ordering, scalar subqueries, residual predicates, joins, query-plan SELECT values, compound SELECT ordering/coercion, or source-neutral API cleanup.
