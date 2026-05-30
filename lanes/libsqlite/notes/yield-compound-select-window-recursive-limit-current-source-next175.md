# Compound SELECT Window Recursive LIMIT Current Source Next175

- Scope: parser/executor compound SELECT behavior for a recursive CTE with `LIMIT/OFFSET`, `UNION` distinct, derived `INTERSECT`, mixed `row_number()` / `dense_rank()` windows, final `ORDER BY`, and tail `LIMIT/OFFSET`.
- Application path: copied `wp_options` rows gain autoloaded plugin/theme options between current and next source snapshots; the derived `INTERSECT` changes the final limited option boundary.
- Focused test evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext175Test.php` passes with 66 tests / 262 assertions / 0 failures.
- Smoke evidence: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next175.php` emits the next175 plan JSON.
- Non-overlap: avoids accepted next171-next173 compound `EXCEPT`/recursive-window LIMIT surfaces by covering the `INTERSECT` arm after windowed UNION distinct output, and avoids queued B-tree, JSON, VFS, WAL, PRAGMA, trigger, and encoding clusters.
- Dependency closure: no new support component is needed; the slice reuses lane-local SELECT SQL recursive CTE, compound UNION/INTERSECT, window, ORDER BY, and LIMIT/OFFSET helpers.
