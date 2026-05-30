# Pager Hot-Journal Master/Super Current Next73

Status: focused PHP behavior growth for attached-database hot rollback-journal recovery gated by a current master/super-journal.

This slice adds `SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext()`. It models the SQLite pager edge where a surviving master/super-journal names the attached rollback journals that are hot for the next opener. Named journals recover their database image before WAL transaction-boundary recovery; unnamed or reserved-lock journals are skipped and keep their current dirty database image. The master/super-journal is deleted only after all named journals have been cleared.

Focused evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerHotJournalMasterSuperCurrentNext73Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 53 assertions, 0 failures
```

Application smoke:

```text
php lanes/libsqlite/examples/application-pager-hot-journal-master-super-current-next73.php --self-test
{
    "status": "super_journal_hot_recovery_current_next",
    "recoveredDatabases": 2,
    "superJournalAction": "delete_super_journal_after_named_hot_journals",
    "mainNextSources": [
        "database",
        "wal"
    ],
    "siteNextSources": [
        "database",
        "wal"
    ]
}
```

Adjacent regression evidence:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHotJournalWalCheckpointRecoveryCurrentNext27Test.php lanes/libsqlite/tests/SQLiteHotJournalWalVisibilityCurrentNext36Test.php lanes/libsqlite/tests/SQLitePagerHotJournalMasterSuperCurrentNext73Test.php
3 test files, 173 assertions, 0 failures
```

Non-overlap: this avoids accepted super-journal commit, rollback-journal apply/commit, pager savepoint current-next/retry/release surfaces, WAL byte truncation/checkpoint/read-pin surfaces, VFS writer/sync/lock/file-control clusters, B-tree pointer-map/freelist/page-move clusters, JSON table planner/source/cursor/constraint clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is master/super-journal membership deciding current/next hot-journal recovery across attached databases.

Dependency closure: no new support component is needed. The slice reuses lane-local rollback-journal recovery and WAL transaction-boundary/current-next visibility primitives.
