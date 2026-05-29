# WAL hot-journal reader checkpoint current-source next148

Status: focused PHP behavior growth for `wal-hot-journal-reader-checkpoint-current-source-next148`.

This slice adds `SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan`, a bounded WAL/pager planner for the checkpoint image produced after rollback-journal hot recovery. Earlier current-source slices validate reader WAL/database source tokens and restarted reader generations. This one validates that the checkpoint database bytes themselves match the hot-recovered database plus current WAL frames before a reset is allowed for a current reader.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalReaderCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalReaderCheckpointCurrentSourceNextTest.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-reader-checkpoint-current-source-next.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalReaderCheckpointCurrentSourceNextTest.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-reader-checkpoint-current-source-next.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 79 assertions, 0 failures` with 79 PASS lines.

Expected dashboard movement: `phpPass` increases by the focused PASS-line count from `SQLiteWalHotJournalReaderCheckpointCurrentSourceNextTest.php`. Mapped upstream coverage remains conservative because this is current-source WAL/pager behavior over existing hot-journal/checkpoint inventory.

Non-overlap: avoids accepted next144 reader database-source admission, next143 reader restart generation, next132 reader WAL source validation, WAL checkpoint transactions, VFS savepoint rollback, rollback-journal commit/apply/super-journal paths, JSON/B-tree/SQL/encoding batches, and WAL byte truncation. The new surface is specifically checkpoint database source-token validation after hot rollback-journal recovery.

Dependency closure: no new support component is needed. The slice reuses native PHP rollback-journal hot recovery, WAL parsing, reader snapshots, and checkpoint database source comparison.
