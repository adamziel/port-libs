# SQL planner STAT4 expression partial current-source next182

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next182`.

Behavior:
- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, a bounded
  current-source wrapper over the accepted next177/next180 STAT4 partial
  expression-index path.
- Applies LIMIT/OFFSET windowing after the descending current-source row stream
  is fenced, preserving duplicate-key rowid order and excluding rows outside
  the partial predicate.
- Projects requested covering payload columns from the partial expression index
  without requiring a table b-tree lookup or temp sort.

Application smoke:
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next182.php --self-test`
  - `application-sqlplanner-stat4-expression-partial-current-source-next182 self-test passed`

Verification:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext182Test.php`
  - `1 test files, 63 assertions, 0 failures`

Dependency closure:
- No new support component needed; this composes existing lane-local STAT4
  expression partial-index fences, descending scan materialization, and
  covering row payload diagnostics.

Non-overlap:
- Avoids accepted next180 descending scan direction, next177 BETWEEN admission,
  next165 range proof, next169 full-index cost, next173 duplicate fanout,
  next175 LIKE prefix windows, expression ORDER BY text execution, range-cost
  ranking, JSON, WAL, VFS, B-tree, and trigger clusters. This slice only
  windows and projects the current-source STAT4 partial expression covering row
  stream.
