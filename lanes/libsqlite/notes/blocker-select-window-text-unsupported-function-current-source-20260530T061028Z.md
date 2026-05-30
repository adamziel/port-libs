# Select Window Text Unsupported Function Blocker

- Slice: `blocker-select-window-text-unsupported-function-current-source-20260530T061028Z`.
- Before fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php` failed `select sql window text rejects unsupported function` because `sum(weight) OVER (ORDER BY option_id)` is now supported current-source behavior and no longer throws.
- Change: preserved the unsupported-function guard with `definitely_missing_window(...) OVER (...)` and added focused aggregate window assertions for `sum`, `count(*)`, `avg`, and `max` over WordPress-style `wp_options` rows.
- After fix: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSelectSqlWindowTextTest.php` passes `1 test files, 56 assertions, 0 failures`.
- Family/root-like check: `php tools/run-tests.php $(rg --files lanes/libsqlite/tests | rg 'SQLiteSelect.*Window.*Test\\.php')` passes `11 test files, 736 assertions, 0 failures`.
- Dependency closure: no new support component is needed; this reuses existing `SQLiteSelectSql` and window aggregate execution.
- Root harness: not run from this isolated micro-slice.
