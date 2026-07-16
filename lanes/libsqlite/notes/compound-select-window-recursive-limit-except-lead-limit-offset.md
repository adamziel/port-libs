# Compound SELECT Window Recursive LIMIT Except Lead Limit Offset

## Scope

This slice adds a bounded descriptive current-source comparison for a recursive CTE feeding a three-arm compound SELECT with `UNION ALL` plus `EXCEPT`, window `lead()` values evaluated before the compound boundary, and final `ORDER BY ... LIMIT ... OFFSET` admission.

It intentionally avoids accepted next158/next159/next160 coverage for two-arm recursive/window compounds, comma recursive LIMIT, `row_number()`, `ntile()`, `percent_rank()`, and plain final LIMIT/OFFSET boundaries.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitExceptLeadLimitOffsetTest.php`
  - Result: `1 test files, 272 assertions, 0 failures`, with 68 PASS lines.
- Example smoke: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-except-lead-limit-offset.php`
- Dependency closure: no new support component needed; the slice reuses lane-local recursive CTE queue, SELECT SQL compound combiner, EXCEPT, window execution, ORDER BY, and tail LIMIT/OFFSET helpers.

## Next

Keep compound SELECT follow-up work outside this exact recursive/window/EXCEPT/tail-limit shape, or move to broader SELECT planner/executor behavior with fresh current-source tests.
