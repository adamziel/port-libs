# Compound SELECT Window Recursive LIMIT Current Source Next182

This patch adds a focused current-source wrapper for compound SELECT behavior
where a recursive CTE arm remains syntactically present but is exhausted by
`LIMIT 0`. The table arms still evaluate window functions before `UNION ALL`
combination, and the final `ORDER BY ... LIMIT ... OFFSET` moves the
WordPress current/next boundary when plugin/theme options are added.

Behavior covered:

- `WITH RECURSIVE` `LIMIT 0` suppresses the anchor before any compound output.
- `row_number()`, `rank()`, and `dense_rank()` window values are materialized in
  compound arms even when the recursive arm is empty.
- `UNION ALL` preserves duplicate windowed table rows from later arms.
- Tail `ORDER BY metric, id LIMIT 4 OFFSET 1` is applied after the empty
  recursive arm and both windowed table arms are combined.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext182Test.php`
- `php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next182.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext182Test.php`
- `php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next182.php --self-test`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass +63` from the new focused test file.
`benchmarkDenominator.mapped` remains `614 / 1589`; this is current-source PHP
behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT
inventory.

Non-overlap:

This avoids accepted next139/157/170/178 recursive/window LIMIT surfaces,
next181 UNION distinct yield-tape work, EXCEPT/INTERSECT variants, multi-anchor
recursion, recursive affinity/collation variants, SQL expression ORDER BY, JSON
table source/cursor/constraint work, VFS/WAL/B-tree clusters, and suite
evidence handoffs. The new surface is specifically an empty recursive arm from
`LIMIT 0` feeding `UNION ALL` table-window arms before final compound
LIMIT/OFFSET.

Dependency closure:

No new support component is needed. The slice reuses lane-local recursive CTE
tracing, window execution, UNION ALL compound execution, ORDER BY, and final
LIMIT/OFFSET machinery.
