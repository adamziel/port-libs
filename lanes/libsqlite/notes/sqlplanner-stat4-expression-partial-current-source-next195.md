# SQL planner STAT4 expression partial current-source next195

Status: focused behavior growth for `sqlplanner-stat4-expression-partial-current-source-next195`.

Behavior:

- Adds `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan`, composing accepted next191 payload expression-key fencing.
- Rejects stale prepared partial expression-index reuse unless the current source's partial-index WHERE predicate is implied by the query terms.
- Rechecks selected current-source rows against the current partial predicate before admitting the covering STAT4 row stream.

WordPress smoke:

- `php lanes/libsqlite/examples/wordpress-sqlplanner-stat4-expression-partial-current-source-next195.php`
- Expected: JSON summary with `stat4-expression-partial-current-source-next195-ready`.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext195Test.php`
- `1 test files, 68 assertions, 0 failures`
- Expected PASS delta: +68 focused PASS lines for one new focused test file.

Dependency closure: no new support component is needed; this reuses current-source STAT4 expression partial planning and adds a lane-local partial predicate proof fence.

Non-overlap: avoids accepted next191 payload expression-key rechecks, next188 peer rowid fencing, next185 sample provenance, expression ORDER BY, range-cost ranking, JSON, WAL, VFS, B-tree, trigger, and UTF clusters. This slice only proves changed partial-index WHERE predicates before admitting current-source STAT4 expression partial rows.
