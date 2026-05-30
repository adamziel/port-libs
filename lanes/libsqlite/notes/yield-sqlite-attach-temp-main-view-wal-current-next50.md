# yield-sqlite-attach-temp-main-view-wal-current-next50

Adds ordered current/next route metadata to `SQLiteAttachTempWalViewTriggerPlan`
for ATTACH/TEMP view-trigger flows that mix temp rollback writes, main/attached
WAL writes, and rollback-journal fallback writes in one trigger body.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteAttachTempWalViewTriggerRouteCurrentNext50Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 54 assertions, 0 failures
```

Application smoke:

```text
$ php lanes/libsqlite/examples/application-attach-temp-wal-view-trigger-route-current-next50.php
{
    "status": "planned",
    "operationRoutes": [
        {"operation_index": 0, "kind": "insert", "schema": "temp", "journal": "temp-rollback", "reader_boundary": "connection-local-next", "commit_visible": true, "wal_frame_indexes": []},
        {"operation_index": 1, "kind": "insert", "schema": "main", "journal": "wal", "reader_boundary": "next", "commit_visible": true, "wal_frame_indexes": [1, 2]},
        {"operation_index": 2, "kind": "select", "schema": "temp", "journal": "read", "reader_boundary": "current", "commit_visible": false, "wal_frame_indexes": []}
    ],
    "currentNextBoundaries": {
        "main": {"journal": "wal", "current_reader": "database-or-existing-wal", "next_reader": "appended-wal", "commit_visible": true, "watched_pages": 2, "frame_indexes": [1, 2]},
        "temp": {"journal": "temp-rollback", "current_reader": "temp-btree-before-trigger", "next_reader": "connection-local-temp-btree", "commit_visible": true, "operation_count": 1, "frame_indexes": []}
    },
    "dependencies": [
        "sqlite-attach-temp-wal-view-trigger-current-next",
        "sqlite-wal-checkpoint",
        "durable-sidecar-write",
        "sqlite-wal-append-transaction",
        "sqlite-wal-frame-checksum-chain",
        "vfs-file-write-coordination",
        "sqlite-wal-checkpoint-append-current-next",
        "sqlite-temp-trigger-rollback-journal-routing"
    ]
}
```

Non-overlap:

This extends accepted batch48 ATTACH temp WAL view-trigger planning with
ordered operation route and current/next boundary materialization. It does not
repeat JSON table cursor/source work, SELECT SQL text/JOIN/GROUP/ORDER,
rollback-journal commit/apply, VFS writer/lock/sync plans, B-tree page moves,
or WAL savepoint byte truncation.

Dependency closure:

No new support component is needed. The slice reuses lane-local attached schema
catalogs, trigger-yield planning, WAL append current/next planning, and rollback
routing metadata.
