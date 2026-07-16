# sqlplanner-stat4-expression-partial-current-source-next175

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next175`.

## Behavior

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded current-source planner for partial expression indexes where `lower(option_name) LIKE 'plugin\_%' ESCAPE '\'` can be admitted as a STAT4 prefix window.
- The plan reparses stale prepared sources when schema or STAT4 generation changes, derives the prefix upper bound (`plugin_` to `plugin``), fences the current STAT4 sample window, applies the partial predicate proof, and blocks stale prepared rowids outside the current prefix row stream.
- Application smoke: `application-sqlplanner-stat4-expression-partial-current-source-next175.php` models a copied `wp_options` plugin-option scan after ANALYZE refresh.

## Evidence

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext175Test.php`
- `php -l lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next175.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext175Test.php`
  - `1 test files, 67 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next175.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next175 self-test passed`

## Dependency Closure

No new support component needed. This composes existing lane-local expression
evaluation, LIKE prefix extraction, partial predicate implication, STAT4 sample
fences, and current-source row diagnostics.

## Non-Overlap

Avoids accepted next154 equality/IN/BETWEEN row streams, next164 range proof,
next171 unsampled equality brackets, next173 duplicate sample fanout, expression
ORDER BY, range-cost, JSON, WAL, VFS, and B-tree clusters. The new surface is
LIKE-prefix STAT4 window admission for a partial expression index.
