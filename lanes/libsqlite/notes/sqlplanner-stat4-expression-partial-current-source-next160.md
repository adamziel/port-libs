# sqlplanner-stat4-expression-partial-current-source-next160

## Behavior

Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded
planner materialization for stale prepared statements whose current schema/stat4
source still admits an OR-rowid-union over partial expression indexes.

The slice records:

- current-source reprepare fences for schema cookie, STAT4 generation, index
  signature, and row signature changes;
- OR-rowid-union admission from `SQLiteSelectExpressionIndexPlan`;
- current-source row materialization, rowid dedupe, payload preservation, and
  current/next row pairs for WordPress-style `wp_options` plugin options;
- cursor-tape evidence for ephemeral rowid union, per-arm STAT4 seeks, covering
  payload reads, and final result rows.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext160Test.php`
- `php -l lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next160.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext160Test.php`
  - `1 test files, 63 assertions, 0 failures`
  - 63 PASS lines
- `php lanes/libsqlite/examples/wordpress-planner-stat4-expression-partial-current-source-next160.php --self-test`
  - `wordpress-planner-stat4-expression-partial-current-source-next160 self-test passed`

## Non-Overlap

Avoids accepted next154 non-covering range row streams, next156 bounded range
deferred seeks, next157 IN covering materialization, next145 skip-scan,
expression ORDER BY, expression-index range-cost, JSON table, VFS/WAL, and
B-tree clusters. The new surface is OR-rowid-union current-source row dedupe
for STAT4 partial expression indexes.

## Dependency Closure

No new support component is needed. The slice reuses native OR partial-expression
planning, STAT4 estimates, partial predicate proof, and current-source row
materialization already present in the libsqlite lane.
