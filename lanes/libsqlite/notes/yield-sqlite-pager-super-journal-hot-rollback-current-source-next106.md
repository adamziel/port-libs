# Pager Super-Journal Hot Rollback Current Source Next106

## Behavior

Adds `SQLitePagerSuperJournalHotRollbackCurrentSourceNextPlan::currentSourceNext()` for hot rollback-journal recovery when a WordPress multi-database import crashes under a SQLite super-journal. The planner hydrates current database and journal bytes, ignores stale candidate snapshots, restores only participant journals listed by the current super-journal, and deletes the super-journal only after every named participant journal is cleared.

`SQLiteVfsFileWriter::applySuperJournalHotRollbackCurrentSource106()` wires the plan to current VFS file handles with atomic operation rollback.

## Verification

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerSuperJournalHotRollbackCurrentSourceNext106Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 71 assertions, 0 failures
```

Smoke:

```text
php lanes/libsqlite/examples/wordpress-pager-super-journal-hot-rollback-current-source-next106.php --self-test
wordpress-pager-super-journal-hot-rollback-current-source-next106 self-test passed
```

## Dashboard Delta

Expected focused PASS-line movement is `+71` for the new lane-scoped test file, with `0` failures. Mapped coverage is unchanged because this is additional pager/super-journal current-source behavior under the already mapped pager inventory.

## Dependency Closure

No new support component is needed. The slice reuses native rollback-journal parsing and existing VFS file write/sync/delete operation application.

## Non-Overlap

Avoids accepted batch89 master-journal current-source hot rollback, accepted batch102/103 pager statement-journal savepoint handling, accepted hot rollback-journal application, accepted super-journal commit behavior, and older supplied-byte super-journal recovery. This slice is specifically current-source super-journal hot rollback with stale snapshot filtering and all-participants-cleared super-journal deletion.
