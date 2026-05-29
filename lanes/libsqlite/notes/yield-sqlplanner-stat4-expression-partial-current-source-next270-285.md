# SQLite planner STAT4 expression partial current-source next270-285

Status: focused PHP behavior growth for `sqlplanner-stat4-expression-partial-current-source-next270-285`.

Behavior: extends `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` with `materializeNext270285()`, a direct follow-on to the merged next254-269 preparation fence. The new fence threads the prior handoff signature, rechecks each carried current-source row projection, and prepares slices 270-285 only when the prior projected rows still match the current source.

WordPress path: `wordpress-sqlplanner-stat4-expression-partial-current-source-next270-285.php` models copied `wp_options` plugin-admin pagination over a descending partial `lower(option_name)` covering index. A stale payload mutation or missing current row blocks the continuation before the next prepared handoff can be reused.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext270285Test.php`
- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next270-285.php --self-test`

Dependency closure: no new support component needed; this composes the existing STAT4 expression partial planner and the accepted next254-269 current-source handoff diagnostics.

Non-overlap: avoids changing next254-269 handoff-window construction, next253 payload row-image validation, next252 scan-direction fences, next249 duplicate peer-count validation, JSON, WAL, VFS, B-tree, trigger/FK, UTF/collation, and suite-runner clusters. The new surface is current-source projection continuity for slices 270-285.
