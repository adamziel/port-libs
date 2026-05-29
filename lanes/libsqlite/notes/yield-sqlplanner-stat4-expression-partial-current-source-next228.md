# SQLite planner STAT4 expression partial current-source next228

Status: focused PHP behavior growth for a STAT4 expression partial-index planner
slice on current-source sample-row admission.

Behavior: `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` composes
the accepted next224 sample-order fence and adds a current-source validation
step for the selected index's `sqlite_stat4` sample rows. The fence proves that
each current STAT4 sample row still resolves to a current row image, that the
sample expression key still matches `lower(option_name)`, and that the row
still satisfies the current partial-index predicate before a resumable
WordPress-style `wp_options` cursor can keep using the partial expression
index.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Test.php`
- `php -l lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next228.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext228Test.php`
  - Result: `1 test files, 79 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next228.php --self-test`
  - Result: `stat4-expression-partial-current-source-next228-ready`

WordPress path: the example models an autoloaded plugin-option scan over copied
`wp_options` rows where a yielded prepared statement may only keep using a
current-source partial expression index when the current STAT4 sample rows are
still inside the partial predicate.

Dependency closure: no new support component is needed; this reuses the native
current-source STAT4 expression partial planner and adds bounded PHP sample-row
predicate validation.

Non-overlap: avoids accepted next224 sample-order validation, grouped LIKE/OR
proofs, rowid alias/payload fences, expression ORDER BY, range-cost ranking,
JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice only validates
current STAT4 sample row images against the partial-index predicate.
