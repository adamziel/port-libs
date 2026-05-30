# Hot Journal WAL Visibility Current Next 36

## Status

Implemented `SQLitePagerHotJournalWalRecoveryPlan::currentNextVisibility()` for copied Application database images that have both a hot rollback journal and a WAL sidecar. The planner compares the current dirty reader snapshot with the next recovered reader snapshot after hot-journal page restoration, committed WAL prefix checkpointing, and uncommitted WAL tail discard.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHotJournalWalVisibilityCurrentNext36Test.php
Focused test run: 1 selected test files (root lock skipped)
61 PASS lines
1 test files, 61 assertions, 0 failures
```

The new focused test covers hot-journal recovery, reserved-lock and super-journal skip gates, uncommitted and corrupt WAL tail visibility, missing page diagnostics after the commit page count, and current/next source and frame-index summaries.

## Application Smoke

```text
php lanes/libsqlite/examples/application-hot-journal-wal-current-next.php
```

The example reports copied `wp_options` reader-visible pages before and after recovery without requiring `ext/sqlite`.

## Non-Overlap

This slice does not repeat accepted VFS rollback-journal application, rollback-journal commit application, WAL byte truncation, VFS savepoint rollback application, VFS file-writer application, WAL checkpoint transaction planning, or hot rollback-journal recovery decisions. It adds the missing current/next reader-visibility evidence for the combined hot-journal plus committed WAL recovery path.

## Dependency Closure

No new support component is needed. The implementation reuses existing rollback-journal parsing/recovery, WAL transaction recovery boundaries, and bounded planner payloads.
