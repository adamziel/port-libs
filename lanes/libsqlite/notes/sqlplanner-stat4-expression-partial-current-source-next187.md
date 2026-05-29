# sqlplanner-stat4-expression-partial-current-source-next187

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next187`.

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded current-source planner that composes the accepted next175 STAT4 LIKE-prefix partial expression scan with retained residual `NOT LIKE` filters.
- WordPress smoke: `wordpress-sqlplanner-stat4-expression-partial-current-source-next187.php` models copied `wp_options` plugin scans where debug/tmp plugin option rows are rejected after current STAT4 prefix admission.
- Non-overlap: extends next175 with residual `NOT LIKE` exclusion only; avoids next184 IN-predicate implication, expression ORDER BY, JSON, WAL, VFS, B-tree, and accepted STAT4 range-cost clusters.
- Dependency closure: no new support component needed; this reuses lane-local STAT4 prefix fences, expression normalization, and current-source row diagnostics.

Verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext187Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next187.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext187Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next187.php`
- `git diff --check -- lanes/libsqlite`
