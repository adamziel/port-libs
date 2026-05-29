# compound-select-recursive-window-order-current-source-next144

## Behavior

- Adds `SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNextPlan`, a bounded diagnostic plan for parser-level `WITH RECURSIVE` CTE queue `ORDER BY` feeding window-function compound arms and a final compound `ORDER BY`.
- Covers current/next source boundaries where a new WordPress option subtree changes recursive queue admission, per-arm `row_number()` output, and final compound ordering.
- This avoids accepted next134/139/140/141 compound surfaces by combining recursive queue ordering and window-arm ranking before final compound ordering, rather than CTE materialized windows, final LIMIT/OFFSET, affinity-only recursive ordering, or EXCEPT/window LIMIT behavior.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectRecursiveWindowOrderCurrentSourceNext144Test.php`
- Result: `1 test files, 270 assertions, 0 failures`, with `67` PASS lines.
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-compound-recursive-window-order-current-source-next144.php`
- Result: `wordpress-compound-recursive-window-order-current-source-next144 self-test passed`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP `SQLiteSelectSql` recursive CTE, window, and compound SELECT execution path already present in `lanes/libsqlite/src`.

## Next

A clean integrator can count this as `+67` focused libsqlite PASS lines and no mapped upstream denominator movement. Follow-up SQL executor work should move to a distinct planner/executor gap, not another recursive/window compound variant.
