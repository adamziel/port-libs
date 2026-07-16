# sqlplanner-stat4-expression-partial-current-source-next237

## Behavior

Adds a bounded current-source STAT4 fence for partial expression indexes that carry trailing payload columns. The new `SQLitePlannerStat4ExpressionPartialCurrentSourceNextPlan` reuses the accepted next228 sample partial-predicate fence, then verifies each current `sqlite_stat4` sample row still has matching trailing payload values such as `autoload` and `blog_id` before a yielded covering scan is reused.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext237Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next237.php --self-test`
- PHP lint: changed PHP files only
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-overlap

This slice avoids accepted STAT4 sample-window/order, rowid peer fences, sample partial-predicate validation, expression `ORDER BY`, range-cost ranking, JSON table planner/cursor/source work, WAL/VFS durability, B-tree page/freelist work, trigger/FK, and UTF/collation clusters. It is limited to current-source STAT4 trailing payload validation for partial expression-index covering scans.

## Dependency closure

No new support component is needed. The slice reuses the existing lane-local planner fixtures and PHP test runner.
