# Compound SELECT Window Recursive LIMIT Current Source Next178

## Scope

- Adds a current-source `compound-select-window-recursive-limit-current-source-next178` behavior cluster.
- Covers a recursive CTE with `LIMIT 5 OFFSET 2`, `lag()` over recursive rows, `lead()` over copied `wp_options` rows, `UNION ALL` streaming, a following distinct `UNION`, and final `ORDER BY ... LIMIT/OFFSET`.
- Non-overlap: avoids accepted next162 `UNION ALL` plus `EXCEPT`, next175 distinct `UNION` plus `INTERSECT`, accepted zero/exhausted recursive LIMIT slices, grouped SELECT SQL text, JSON table source/cursor/constraint slices, WAL/VFS/B-tree/encoding clusters, and suite-runner evidence work.

## Evidence

- Focused test command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext178Test.php`
- Expected focused delta: 67 PASS lines in one new lane test file.
- Application smoke: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next178.php --self-test`

## Dependency Closure

No new support component is needed. The slice reuses lane-local native PHP SELECT SQL recursive CTE, compound set-operator, window, ORDER BY, and LIMIT/OFFSET helpers.
