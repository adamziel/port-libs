# SQL Planner STAT4 Expression Partial Current Source Next247

Status: focused PHP behavior growth for current-source STAT4 expression partial-index planning where a LIMIT/OFFSET cursor window starts or ends inside duplicate expression-key peers.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, layered on accepted next244 current-source LIMIT/OFFSET window validation. The new fence recomputes current partial expression order, derives the first/last window expression keys, expands their full peer rowid sets, and verifies the yielded cursor did not reuse stale prepared-source boundary peers before promoting cursor reuse.

Application path: `application-planner-stat4-expression-partial-current-source-next247.php` models copied `wp_options` plugin-option queries using a partial `lower(option_name)` expression index where duplicate `plugin_forms` rows straddle the current page boundary.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext247Test.php`
- `php -l lanes/libsqlite/examples/application-planner-stat4-expression-partial-current-source-next247.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext247Test.php`
  - Result: `1 test files, 67 assertions, 0 failures`, `67` PASS lines.
- `php lanes/libsqlite/examples/application-planner-stat4-expression-partial-current-source-next247.php`
  - Result: `PASS application planner stat4 expression partial current source next247 smoke`.

Expected dashboard movement: `phpPass +67` from focused lane-local PASS lines. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over already mapped planner/STAT4 expression partial-index inventory.

Dependency closure: no new support component is needed. The slice reuses current-source STAT4 expression partial planning, next244 LIMIT/OFFSET window validation, row-array expression evaluation, and cursor-program proof signatures.

Non-overlap: avoids accepted next244 window validation, next235 vector counters, next232 first-prefix cardinalities, next231 page membership, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, encoding, PRAGMA, and suite-runner clusters. The narrower behavior is duplicate expression-key boundary peer validation after accepted current-source LIMIT/OFFSET window proof.
