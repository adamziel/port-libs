# sqlplanner-stat4-expression-partial-current-source-next243

## Behavior

Adds a current-source `sqlite_stat4` expression sample-tape fence for partial expression-index planning. The new planner composes the accepted `next240` current partial-predicate proof, then verifies that the current STAT4 sample rowids resolve against the current source, expand duplicate `lower(option_name)` keys to the matched current rowids, and are not just the stale prepared sample tape.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext243Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next243.php --self-test`
- PHP lint: changed PHP files under `lanes/libsqlite`
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This slice avoids accepted next240 partial predicate implication, next237 trailing payload validation, expression `ORDER BY`, range-cost ranking, JSON table, WAL/VFS, B-tree, trigger, UTF/collation, and suite-runner clusters. It only adds current STAT4 sample-tape validation for partial expression indexes.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP planner source arrays, current-source STAT4 expression partial planner chain, and focused TestRunner harness.
