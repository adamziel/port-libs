# yield-sqlite-application-import-rollback-wal-json-current-next38

2026-05-27 isolated libsqlite slice.

## Behavior

Adds `SQLiteJsonImportRollbackWalPlan`, a bounded Application import
rollback planner that wraps the existing JSON import savepoint plan and applies
current-batch rollback semantics to both database bytes and WAL bytes. When a
JSON mutation in a copied `wp_options` import batch fails, the planner restores
the pre-batch database image and truncates the WAL byte stream back to the
savepoint frame boundary.

This intentionally avoids accepted clusters for WAL savepoint byte truncation,
VFS savepoint rollback application, rollback-journal commit/apply,
parser-level JSON table sources/cursors/constraints, and accepted B-tree
page-move/freeblock work.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteImportRollbackWalJsonCurrentNext38Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 50 assertions, 0 failures
```

Example smoke:

```text
php lanes/libsqlite/examples/application-import-rollback-wal-json-current-next38.php
{
    "status": "rolled_back_current_json_batch",
    "failed_statements": [
        "broken_payload"
    ],
    "database_restored_to_before": true,
    "wal_frame_count_before": 4,
    "wal_frame_count_after": 0,
    "wal_truncated": true
}
```

Expected dashboard movement: `phpPass +50`, from `13431` to `13481`, with no
new failures.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP
JSON mutation, savepoint statement-journal, and WAL frame-boundary primitives.
