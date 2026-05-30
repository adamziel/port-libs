# Compound SELECT Window Recursive LIMIT Current Source Next210

## Scope

- Adds a focused current-source compound SELECT cluster for `WITH RECURSIVE` queue LIMIT/OFFSET feeding `row_number()` and framed `last_value()` window arms.
- Exercises `UNION ALL` followed by `INTERSECT` and `EXCEPT` before final compound `ORDER BY metric, id LIMIT/OFFSET` admission.
- Keeps a current-source cursor token so replay accepts only the current source and rejects stale next-source cursors.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext210Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next210.php`
- Expected dashboard movement: +70 focused PASS lines from the new test file; mapped upstream coverage unchanged.

## Non-Overlap

Avoids accepted next209 sum/count aggregate windows, next208 rank/dense-rank EXCEPT fencing, next206 lead/nth_value INTERSECT fencing, next203 lag/last_value EXCEPT-only fencing, and unrelated JSON/WAL/B-tree/VFS clusters.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `SQLiteSelectSql` compound execution, recursive queue tracing, window dispatch, compound membership, final LIMIT helpers, and current-source cursor validation.
