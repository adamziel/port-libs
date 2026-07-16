## Summary

Consolidated the explicit WAL hot-journal savepoint checkpoint current-source
`next724` through `next787` production wrapper methods into the canonical
`afterCurrentCheckpointStage()` dispatcher on
`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`.

The stable `sealReadyCheckpointCurrentSourceHandoff()` entry point remains in
place. Observable status keys, dependency strings, operation names, receipt
names, and reason text are preserved by the shared dispatcher.

## Verification

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext724739Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext740755Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext756771Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext772787Test.php`
  - `4 test files, 320 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The consolidation reuses the existing
after-current checkpoint receipt validation path and keeps the same WAL
current-source evidence contract.

## Non-Overlap

This is suffix cleanup only for the WAL checkpoint current-source wrapper range
724 through 787. It does not change pager behavior, WAL checkpoint semantics,
VFS writes, JSON, B-tree, planner, upstream-suite admission, dashboard, or root
gate evidence.
