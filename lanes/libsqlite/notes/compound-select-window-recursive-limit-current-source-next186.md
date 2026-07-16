# compound-select-window-recursive-limit-current-source-next186

## Scope

- Adds a current-source compound SELECT slice for recursive CTE rows unioned with Application `wp_options` rows where `rank()` and `dense_rank()` are materialized before a distinct compound arm and a comma-form tail `LIMIT offset,count`.
- Non-overlap: avoids accepted next183 `LIMIT/OFFSET` tail diagnostics, accepted grouped SELECT SQL text, accepted expression `ORDER BY`, accepted SELECT SQL subqueries, JSON table sources/constraints/cursors, and all WAL/B-tree/VFS accepted clusters.
- Dependency closure: no new support component is needed; the slice reuses native recursive CTE LIMIT/OFFSET, compound UNION ALL/UNION, window rank/dense_rank, ORDER BY, and comma-form LIMIT execution already present in lane-local SELECT helpers.

## Evidence

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext186Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next186.php --self-test`
- PHP lint: `php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php && php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext186Test.php && php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next186.php`
- Diff check: `git diff --check -- lanes/libsqlite`

## Next

- Broader SQL executor follow-up: parser/planner admission for compound SELECT arms whose window terms depend on hidden ORDER expressions across current/next source invalidation.
