# Trigger RETURNING Current-Source Done Gate Consolidation

Session: `port-dev-sqlite-yield-consol-meth-trigger-n`
Micro-slice: `consolidate-final-numbered-methods-trigger-returning-ninth-pass`

## Change

- Renamed the production entry point `executeNext194()` to `executeCurrentSourceDoneGate()`.
- Renamed its direct private helper methods from `*Next194` names to stable descriptive current-source done-gate helpers.
- Updated the direct focused test and WordPress smoke caller.
- Preserved existing result payload keys, dependency strings, and status strings so behavior and assertions remain unchanged while production method names stop carrying the worker-number suffix.

## Verification

- `php -l lanes/libsqlite/src/SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext194Test.php`
  - `No syntax errors detected`
- `php -l lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next194.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteTriggerRecursiveViewReturningCurrentSourceNext194Test.php`
  - `1 test files, 86 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-trigger-recursive-view-returning-current-source-next194.php --self-test`
  - `wordpress-trigger-recursive-view-returning-current-source-next194 self-test passed`

## Dependency Closure

No new support component is needed. This is a production-method consolidation only; it reuses the existing native recursive view RETURNING current-source done-gate behavior.

## Non-Overlap

This patch only consolidates the trigger recursive view RETURNING current-source done-gate method/helper names. It does not change row-value RETURNING, DML RETURNING conflicts, schema reparse, WAL/VFS, JSON table, planner, encoding, B-tree, or suite-runner behavior.
