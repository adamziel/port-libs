# Compound SELECT Window Recursive LIMIT Exhausted Yield Boundary

## Behavior

This consolidation keeps the historical proof keys but moves the direct
production/test/example surface to the stable
`SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan::compareRecursiveLimitExhaustedYieldBoundary()`
entry point for a bounded current-source SELECT executor edge:

- `WITH RECURSIVE` queue execution with `LIMIT ... OFFSET` exhaustion.
- Window function arms before compound reduction (`lag()` and `lead()`).
- Mixed `UNION ALL` followed by `UNION` distinct compound selection.
- Final compound `ORDER BY ... LIMIT ... OFFSET` that changes which recursive
  rows survive when the next WordPress option source adds rows.

The new plan records recursive limit pressure, skipped anchor rows, admitted
recursive labels, recursive rows dropped by the final compound limit, and the
current/next source boundary shift in the compound yield tape.

## WordPress Scenario

`examples/wordpress-compound-select-window-recursive-limit-exhausted-yield-boundary.php`
models a `wp_options` migration/import query that ranks current recursive seed
rows beside autoloaded option rows. Adding plugin/theme option rows changes the
final LIMIT boundary, so the native SELECT executor must keep recursive CTE
limit exhaustion, window metrics, compound distinct handling, and final
LIMIT/OFFSET ordering aligned.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitExhaustedYieldBoundaryTest.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 354 assertions, 0 failures
```

Consolidation delta: no public pass counter movement; this removes the
numbered production entry/helper surface while preserving the existing direct
assertion coverage.

## Non-Overlap

This does not repeat accepted next181 compound SELECT yield-tape coverage. The
new assertions specifically require exhausted recursive LIMIT/OFFSET queues and
verify how the current/next source expansion changes which recursive labels are
admitted or dropped by the final compound LIMIT after window and UNION distinct
processing.

## Dependency Closure

No new support component is needed. The slice reuses existing lane-local SELECT
SQL recursive CTE tracing, window evaluation, compound yield tape, UNION
distinct reduction, and final LIMIT/OFFSET execution.
