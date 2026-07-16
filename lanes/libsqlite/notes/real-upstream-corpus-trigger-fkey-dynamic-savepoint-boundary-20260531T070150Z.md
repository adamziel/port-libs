# real-upstream-corpus-trigger-fkey-dynamic-savepoint-boundary-20260531T070150Z

## Scope

- Micro-slice: `real-upstream-corpus-trigger-fkey-dynamic-20260531T070150Z-0`
- Upstream source: `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_fkey.test`
- Ported upstream scenarios:
  - `e_fkey-36.1..36.4`: nested savepoint release can leave deferred FK violations pending until the outer transaction boundary.
  - `e_fkey-37.1..37.6`: transaction savepoint release checks deferred FK violations and leaves the savepoint open when blocked.
  - `e_fkey-38.1..38.8`: failed commit preserves nested savepoints, and `ROLLBACK TO` can repair the deferred violation state before commit.

## Behavior Added

- Added `SQLiteDynamicTriggerForeignKeyPlan::deferredForeignKeySavepointBoundaryPlan()` for generic self-referencing deferred FK rows.
- The plan models `BEGIN`, transaction savepoints, nested savepoints, `ROLLBACK TO`, `RELEASE`, `COMMIT`, deferred violation queues, blocked boundaries, and open savepoint preservation.
- Added focused dynamic corpus coverage in `SQLiteRealUpstreamTriggerFkeyDynamicSavepointBoundary20260531Test.php`.

## Evidence

- `php -l lanes/libsqlite/src/SQLiteDynamicTriggerForeignKeyPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSavepointBoundary20260531Test.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamTriggerFkeyDynamicSavepointBoundary20260531Test.php`
  - `1 test files, 9907 assertions, 0 failures`

## Non-Overlap

- This does not repeat accepted trigger/FK defer-pragma, fkey2 deferred graph, action matrix, RESTRICT, recursive trigger, triggerC, triggerG, or count_changes coverage.
- The owned upstream section is the `e_fkey.test` deferred savepoint boundary block, specifically `e_fkey-36` through `e_fkey-38`.
- No WordPress-specific APIs, classes, methods, examples, or fixture names were added.

## Dependency Closure

- No new support component is needed. The slice reuses the existing generic dynamic trigger/FK plan class and the existing PHP test harness.
