# SQLite planner STAT4 expression partial current-source next244

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next244`.

This slice adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, an additive current-source LIMIT/OFFSET window fence for stale prepared statements that reuse a STAT4-backed partial expression index. After the accepted next241 residual WHERE validation, the new fence recomputes the current partial expression order and verifies that the yielded rowid window exactly matches the current source for the requested LIMIT/OFFSET.

Application relevance: copied `wp_options` plugin-admin pagination can keep a prepared descending partial `lower(option_name)` expression-index plan only when the current source still yields the same page window after ANALYZE/schema/source movement.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext244Test.php`
  - `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next244.php --self-test`
  - `application sqlplanner stat4 expression partial current-source next244: stat4-expression-partial-current-source-next244-ready window=30,50,20,21,22 signature=0a91136be211`

Dependency closure: no new support component needed; this composes existing native PHP STAT4 expression partial planning, current-source residual checks, and bounded row materialization.

Non-overlap: avoids accepted next241 residual WHERE checks, next238 covering payload validation, next232 STAT4 counters, next231 page membership, next228 sample partial proof, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. The new surface is specifically current-source LIMIT/OFFSET window validation for partial expression-index cursor reuse.
