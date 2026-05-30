# sqlplanner-stat4-expression-partial-current-source-next213

## Scope

Adds a focused current-source STAT4 expression partial-index planner fence for grouped `LIKE` partial-index arms whose case-sensitive LIKE mode or collation can change after statement preparation.

The slice composes the accepted next212 grouped-LIKE partial-arm proof and then refuses reuse unless the current partial-index LIKE arm's case/collation contract is still implied by the query and all selected row payloads satisfy that contract.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerStat4ExpressionPartialCurrentSourceNext213Test.php`
  - `Focused test run: 1 selected test files (root lock skipped)`
  - `1 test files, 66 assertions, 0 failures`
  - 66 focused PASS lines.
- `php lanes/libsqlite/examples/application-sqlplanner-stat4-expression-partial-current-source-next213.php`
  - Application smoke validates copied `wp_options` plugin-option planning with a NOCASE `LIKE` partial arm.

## Non-Overlap

This avoids accepted next212 grouped LIKE arm proof, next209 grouped OR, next206 OR proof, expression ORDER BY, range-cost, JSON, WAL, VFS, B-tree, trigger, and Unicode GLOB clusters. The new behavior is only the case/collation compatibility fence layered after the grouped-LIKE partial-arm proof.

## Dependency Closure

No new support component is needed. The slice reuses current-source STAT4 expression partial planner data, grouped-LIKE proof rows, and row payloads already present in the lane.

## Next

Continue planner work on distinct current-source gaps such as stale STAT4 sample invalidation, residual predicate proof boundaries, or broader SQL executor/planner correctness outside accepted range-cost and expression ORDER BY surfaces.
