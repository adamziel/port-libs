# compound-select-window-recursive-limit-current-source-next213

Status: focused PHP behavior growth for parser-level compound SELECT output where recursive CTE queue LIMIT/OFFSET rows and copied `wp_options` rows compute framed `min()` / `max()` window extrema before an `INTERSECT` membership boundary and final compound LIMIT/OFFSET.

Behavior covered:
- `SQLiteWindowFunction::aggregateFrameValues()` and parser-level SELECT execution now support framed `min()` and `max()` window aggregates.
- `WITH RECURSIVE` queue `ORDER BY ... LIMIT ... OFFSET` is traced before the compound arms are evaluated.
- Window extrema are evaluated in both recursive and WordPress option arms before the compound `INTERSECT`.
- A next-source copied `wp_options` row shifts the final LIMIT boundary while stale current-source cursor tokens are rejected.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext213Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 370 assertions, 0 failures
```

Additional required checks:

```text
php lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next213.php
php -l lanes/libsqlite/src/SQLiteWindowFunction.php
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/src/SQLiteSelectQuery.php
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext213Test.php
php -l lanes/libsqlite/examples/wordpress-compound-select-window-recursive-limit-current-source-next213.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +67` from the new focused test file. `benchmarkDenominator.mapped` remains `623 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory, not a newly hydrated upstream row.

Non-overlap: avoids accepted next212 `group_concat`/`row_number` EXCEPT fencing, next209 `sum`/`count` EXCEPT+UNION fencing, next208 rank/dense_rank EXCEPT fencing, earlier recursive/window compound slices, JSON table source/cursor/constraint work, WAL/VFS/B-tree clusters, and grouped/JOIN/subquery/ORDER SQL text work. The narrower surface is framed `min()` / `max()` window execution before `INTERSECT` and final compound LIMIT over current and next WordPress option sources.

Dependency closure: no new support component is needed; this reuses lane-local SELECT SQL compound execution, recursive CTE tracing, window frame evaluation, INTERSECT membership, and result LIMIT/OFFSET machinery.
