# Compound SELECT Window Recursive LIMIT Current Source Next170

This isolated patch adds `SQLiteCompoundExceptWindowRecursiveLimitCurrentSourceNextPlan` and focused coverage for a recursive CTE queue whose `OFFSET` exhausts anchor rows before a windowed compound SELECT tail is reduced by `EXCEPT` and then bounded by final `LIMIT/OFFSET`.

Focused behavior:

- Recursive CTE `LIMIT ... OFFSET ...` trace records skipped anchor rows and emitted recursive rows.
- Window functions are evaluated before compound tail reduction.
- `UNION ALL` rows are reduced by an `EXCEPT` arm before final ordering and limiting.
- Current/next copied `wp_options` rowsets show plugin/theme option changes moving the admitted final boundary.

Verification evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext170Test.php`
- Result: `1 test files, 261 assertions, 0 failures` with 64 PASS lines.

Non-overlap:

- Avoids accepted compound row composition, accepted INTERSECT recursive/window LIMIT behavior, accepted named-window next165 behavior, and accepted LIMIT 0 exhaustion next166 behavior.

Dependency closure:

- No new support component is needed. The patch reuses lane-local recursive CTE tracing, window row-array evaluation, EXCEPT compound reduction, and final LIMIT/OFFSET execution.
