# WAL Hot-Journal Recovery Current Next22

## Behavior

- Added `SQLiteVfsFileWriter::applyHotJournalThenWalRecovery()` for the ordered recovery edge where a copied WordPress SQLite database has both a hot rollback journal and a WAL sidecar.
- The native VFS path restores the hot rollback-journal database image first, then runs WAL transaction-boundary recovery against that restored image, checkpoints the committed WAL prefix, truncates uncommitted WAL tail bytes, deletes the rollback journal, syncs database/WAL/directory state, and keeps the operation atomic through the existing file-writer rollback guard.
- This is intentionally disjoint from the accepted standalone hot rollback-journal apply, rollback-journal commit/super-journal commit, WAL checksum-boundary apply, WAL savepoint byte truncation, checkpoint transaction, sync-plan/apply, and locked writer clusters.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalRecoveryCurrentNext22Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 51 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/wordpress-wal-hot-journal-recovery.php
{
    "status": "applied",
    "rollback_reason": "hot_journal_recovered",
    "wal_reason": "uncommitted_valid_tail_after_last_commit",
    "journal_exists": false,
    "database_contains_wal_commit": true,
    "database_contains_draft_tail": false,
    "wal_bytes": 1640
}
```

## Status Delta

- `phpPass`: `7625` -> `7676` for the 51 newly verified PASS lines in `SQLiteWalHotJournalRecoveryCurrentNext22Test.php`.
- `benchmarkDenominator.mapped`: unchanged; no new upstream inventory unit was mapped.
- Dependency closure: no new support component is required. The slice reuses the existing bounded rollback-journal parser, WAL transaction-boundary recovery, and native VFS file-writer atomic operation support.

## Next

- Replay on the latest accepted libsqlite head if integration has advanced beyond `c58b6f5c4bf8ea6c9311b67899473c82c6895d2b`.
- Continue with non-overlapping pager/WAL durability, SQL/JSON planner execution, or B-tree delete/freelist gaps.
