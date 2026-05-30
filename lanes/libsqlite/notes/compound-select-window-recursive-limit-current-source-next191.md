# compound-select-window-recursive-limit-current-source-next191

Status: focused PHP behavior growth for parser-level compound SELECT output where an ordered recursive queue feeds `nth_value()`, `ntile()`, and `lead()` window arms before a final `UNION` distinct and post-compound LIMIT/OFFSET current/next boundary.

Behavior covered:

- `WITH RECURSIVE` queue `ORDER BY 3 DESC LIMIT 5 OFFSET 1` skips the anchor row before the recursive arm is visible to the compound SELECT.
- `nth_value(label, 2)`, `ntile(3)`, and `lead(..., 2, ...)` are evaluated inside their compound arms before `UNION` duplicate elimination.
- Tail `ORDER BY peer, id LIMIT 6 OFFSET 2` is applied only after recursive and Application `wp_options` rows are combined.
- A next-source `plugin_alpha`/`theme_mods` autoload pair moves the Application import boundary and changes the final value-offset peers without changing recursive queue diagnostics.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext191Test.php
php -l lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next191.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteCompoundSelectWindowRecursiveLimitCurrentSourceNext191Test.php
php lanes/libsqlite/examples/application-compound-select-window-recursive-limit-current-source-next191.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +64` from the new focused test file. `benchmarkDenominator.mapped` remains `617 / 1589`; this is current-source PHP behavior over already mapped recursive CTE, compound SELECT, window, and LIMIT inventory.

Non-overlap: avoids accepted next139/158/187/188 recursive final-limit and endpoint-window variants, next186 comma LIMIT, accepted expression ORDER BY, grouped SELECT text, JSON table source/cursor/constraint work, VFS/WAL/B-tree clusters, VDBE sorter/window collation work, and encoding-only LIKE/GLOB/collation clusters. The narrower surface is ordered recursive queue LIMIT feeding value-offset window functions before a post-compound LIMIT/OFFSET current/next boundary.

Dependency closure: no new support component is needed; this reuses lane-local parser-level SELECT SQL, recursive CTE queue ordering, compound combiner, window row-array execution, and result LIMIT/OFFSET machinery.
