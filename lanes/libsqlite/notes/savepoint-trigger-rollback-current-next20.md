# Savepoint Trigger Rollback Current Next20

Status: focused PHP corpus growth for current-savepoint rollback after trigger side effects.

## Behavior

- Added `SQLiteSavepointTriggerRollbackPlan`, a bounded native PHP executor for copied row-array INSERT work inside a named savepoint.
- Covers an AFTER INSERT trigger that requests rollback of the current savepoint, restoring the savepoint row snapshot while keeping the outer transaction and savepoint active.
- Reports restored page numbers, rollback-to WAL frame, discarded WAL frames, cleared changes/inserted diagnostics, and dependency tags for savepoint-current rollback plus trigger rollback behavior.
- Added `application-savepoint-trigger-rollback-current-next20.php` as a copied `wp_options` plugin-import smoke.

## Verification

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSavepointTriggerRollbackCurrentNext20Test.php
```

Result: `1 test files, 56 assertions, 0 failures`.

## Status Delta

- `phpPass`: `6957 -> 7013` from 56 newly verified focused PASS lines.
- `phpFail`: remains `0`.
- `benchmarkDenominator.mapped`: unchanged; this adds focused PHP corpus coverage and does not claim a newly hydrated upstream inventory unit.

## Non-Overlap

This slice avoids accepted savepoint page-image rollback, WAL byte truncation, VFS savepoint rollback application, rollback-journal commit/apply, super-journal commit, WAL checkpoint transactions, trigger recursion conflict rollback, SELECT SQL, JSON table, B-tree, Unicode GLOB, VFS writer/sync/lock, and status-only clusters. It targets current-savepoint rollback semantics for trigger side effects and keeps the behavior bounded to row snapshots plus page/WAL rollback evidence.

## Dependency Closure

No new support component is needed. The slice reuses lane-local `SQLiteSavepointStack` page/WAL rollback metadata and adds a bounded trigger/savepoint executor.
