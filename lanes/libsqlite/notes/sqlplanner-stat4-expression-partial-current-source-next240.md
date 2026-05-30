# sqlplanner-stat4-expression-partial-current-source-next240

## Behavior

Adds a current-source STAT4 expression partial-index fence for yielded planner
reuse. The new plan reuses the accepted next237 trailing-payload validation,
then proves that the current partial-index predicate terms are implied by the
live WHERE terms and that stale prepared-only partial predicates are not being
used to admit the plan.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext240Test.php`
  - `1 test files, 64 assertions, 0 failures`
  - 64 focused PASS lines
- Application smoke:
  - `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next240.php --self-test`

## Non-Overlap

This slice avoids accepted next237 trailing-payload validation, expression
ORDER BY, range-cost ranking, JSON table constraints, WAL/VFS durability,
B-tree delete/reuse, trigger/FK, UTF/collation, and suite-runner clusters.

## Dependency Closure

No new support component is needed. The patch reuses the lane-local
current-source STAT4 expression partial planner chain.
