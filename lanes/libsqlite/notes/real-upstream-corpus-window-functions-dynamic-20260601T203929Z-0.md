Status: focused upstream window-function misuse coverage added for the current isolated libsqlite worktree.

Source truth:
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/window1.test`
- Covered upstream scenarios: `window1.test 7.1.1-7.1.8`.

Behavior ported:
- Bare window-only calls such as `nth_value(x, 1)` now surface `misuse of window function ...` instead of falling through to unsupported scalar dispatch.
- Window expressions in `WHERE`, `GROUP BY`, `HAVING`, and `LIMIT` are rejected before evaluator dispatch with window-specific misuse diagnostics.
- Scalar functions used with `OVER`, for example `trim(x) OVER (...)`, now report that the scalar may not be used as a window function.
- Unexpanded `OVER abc` identifiers now report a missing named window instead of being split as an implicit projection alias.
- Ranking functions with arguments now report SQLite-style wrong-arity diagnostics.

Focused coverage:
- Added `lanes/libsqlite/tests/SQLiteRealUpstreamWindow1MisuseDynamic20260601T203929ZTest.php`.
- The file contains 8 static upstream checks plus 1,000 generated upstream-shaped context variants.
- Focused command passed: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamWindow1MisuseDynamic20260601T203929ZTest.php`
- Result: `1 test files, 1008 assertions, 0 failures`.
- Guard command passed: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- Guard result: `1 test files, 8 assertions, 0 failures`.
- Inventory command passed: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWindowCorpusInventoryTest.php`
- Inventory result: `1 test files, 27 assertions, 0 failures`.
- Expected `phpPass` movement: `6253874 -> 6254882` (`+1008` focused TestRunner PASS cases).
- Mapped denominator coverage remains `1589 / 1589`.

Non-overlap:
- This slice targets the previously uncovered `window1.test 7.1.*` misuse cluster.
- It avoids already accepted window1 result-value/ranking coverage, window5 custom-function coverage, window8/window9/windowA range/collation coverage, windowB JSON inverse coverage, and windowE/windowerr diagnostic coverage.

Dependency closure:
- No new support component is required.
- The implementation reuses the existing `SQLiteSelectSql` parser/executor pipeline and adds lane-local context validation for window expressions.

Additional observation:
- An adjacent-family exploratory run of `SQLiteSelectSqlNamedWindowSubqueryCurrentSourceNext107Test.php` and `SQLiteSelectSqlWindowTextTest.php` still reports four failures in default `nth_value()` / `last_value()` frame expectations. Those failures are outside this misuse-diagnostic slice and were not rewritten here.
