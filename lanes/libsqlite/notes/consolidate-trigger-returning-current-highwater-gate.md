# Trigger RETURNING Current Highwater Gate Consolidation

Session: `port-dev-sqlite-yield-consol-meth-trigger-ap`
Micro-slice: `consolidate-final-numbered-methods-trigger-returning-thirty-seventh-pass`

## Change

- Renamed `SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext200()` to `executeCurrentHighwaterGate()`.
- Renamed the direct private `*Next200` helper methods to stable current-highwater helper names.
- Updated the direct focused test and WordPress smoke caller.
- Preserved accepted `*_next200` result keys, dependency markers, and status strings so scenario coverage remains unchanged while production method names no longer carry this worker-number suffix.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext200Test.php`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next200.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext200Test.php`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next200.php --self-test`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. This is a production-method consolidation only; it reuses the existing recursive view trigger RETURNING current-source highwater gate behavior.

## Non-Overlap

This patch only consolidates the trigger recursive view RETURNING current-highwater gate method/helper names. It does not change row-value RETURNING, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, B-tree, PRAGMA, attach, or suite-runner behavior.
