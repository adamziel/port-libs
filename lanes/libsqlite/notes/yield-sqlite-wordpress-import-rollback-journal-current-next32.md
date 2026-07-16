# yield-sqlite-application-import-rollback-journal-current-next32

Status: focused PHP behavior growth for rollback-journal Application import current/next reader boundaries.

## Implementation

- Added `SQLiteRollbackJournalCurrentNextPlan::importTransaction()` to compose rollback-journal commit planning with current/next database images.
- The planner preserves the current reader on the pre-commit database image through journal write, journal sync, dirty database page writes, and database sync.
- The next reader becomes visible only when the rollback journal is deleted, truncated, or zeroed for persist mode, matching the commit boundary expected by copied `wp_options` import transactions.
- Added `application-import-rollback-journal-current-next.php` as a Application smoke for copied `wp_options` rows.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRollbackJournalCurrentNext32Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 58 assertions, 0 failures
```

```text
php lanes/libsqlite/examples/application-import-rollback-journal-current-next.php
{
    "status": "planned",
    "databasePath": "/wp-content/database/.ht.sqlite",
    "dirtyPages": [
        2,
        3,
        4
    ],
    "currentReader": [
        "wp_options active_plugins before import",
        "wp_options autoload index before import",
        ""
    ],
    "nextReader": [
        "wp_options active_plugins after copied import",
        "wp_options autoload index after copied import",
        "wp_options new imported plugin option row"
    ],
    "commitVisibleAt": "delete_rollback_journal_after_commit",
    "dependencies": [
        "sqlite-rollback-journal-commit",
        "durable-journal-before-database-write",
        "vfs-file-write-coordination",
        "sqlite-rollback-journal-current-next-reader-boundary",
        "application-import-rollback-journal-current-next"
    ]
}
```

## Non-Overlap

This avoids accepted rollback-journal commit/apply, VFS rollback-journal apply,
super-journal commit, VFS savepoint rollback, WAL byte truncation/checkpoint
transactions, VFS sync/locked-writer/process-lock clusters, JSON table
source/cursor/constraint work, SELECT SQL text/subquery/GROUP/ORDER clusters,
B-tree page move/root-collapse/overflow freelist clusters, and Unicode GLOB
work. The new surface is the rollback-journal current/next reader visibility
boundary during copied Application import commits.

## Dependency Closure

No new support component is needed. The slice reuses lane-local rollback
journal commit planning and durable VFS write-order metadata; broader pager
integration can consume this current/next boundary when applying real file
handles.
