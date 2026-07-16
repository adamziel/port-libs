# SQLite compound SELECT window recursive LIMIT current-source next171

## Scope

- Added `SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan` for a current-source compound `UNION` distinct cluster.
- The covered behavior is recursive CTE `LIMIT/OFFSET` feeding windowed compound arms, then distinct `UNION`, final `ORDER BY`, and final `LIMIT/OFFSET`.
- This intentionally avoids accepted EXCEPT/INTERSECT compound slices, exhausted recursive queues, expression `ORDER BY`, grouped SELECT text, JSON table source/cursor behavior, and WAL/B-tree/VFS accepted clusters.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext171Test.php`
- Result: `1 test files, 201 assertions, 0 failures`
- PASS-line delta: `+62` focused PASS cases in the new lane test file.

## Application Smoke

- Added `lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next171.php`.
- The smoke models copied `wp_options` rows where recursive option labels and autoload table rows overlap, requiring window values to be computed before distinct `UNION` and final pagination.

## Dependency Closure

- No new support component is needed.
- The slice reuses lane-local recursive CTE LIMIT/OFFSET tracing, window SELECT arm execution, compound UNION distinct combination, ORDER BY, and final LIMIT/OFFSET execution.

## Next

- Continue with non-overlapping compound SELECT/current-source behavior only if it adds new executor semantics beyond UNION distinct recursion/window pagination; otherwise pivot to SQL planner/executor, WAL/pager durability, JSON planner, encoding/collation, or B-tree materialization gaps.
