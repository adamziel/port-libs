# WAL checkpoint hot-journal reader current-source next144

Status: focused PHP behavior growth for `wal-checkpoint-hot-journal-reader-current-source-next144`.

This slice adds `SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan`, a bounded native PHP planner for the WAL/pager edge where a rollback-journal hot recovery changes the database source image before checkpoint reset. Existing hot-journal reader checks already compare WAL source bytes; next144 adds the missing database-source token so a reader using the same WAL header/frames but dirty pre-recovery database bytes must reopen before a checkpoint reset can proceed.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalReaderCurrentSourceNext144Test.php
php -l lanes/libsqlite/examples/application-wal-checkpoint-hot-journal-reader-current-source-next144.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalReaderCurrentSourceNext144Test.php
php lanes/libsqlite/examples/application-wal-checkpoint-hot-journal-reader-current-source-next144.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 72 assertions, 0 failures` with 72 PASS lines.

Application smoke: `application-wal-checkpoint-hot-journal-reader-current-source-next144 self-test passed`.

Expected dashboard movement: `phpPass` +72 from the focused next144 PASS lines. Mapped upstream coverage remains conservative because this is focused PHP WAL/pager behavior over existing WAL hot-journal/checkpoint inventory.

Non-overlap: avoids accepted WAL hot-journal checkpoint reader next122/132/135, WAL hot-journal checkpoint restart next129, WAL checkpoint hot-journal savepoint next114, WAL byte truncation, WAL checkpoint transactions, VFS savepoint rollback, rollback-journal apply/commit/super-journal paths, and B-tree/JSON/SQL/encoding batch140 clusters. The new surface is specifically the database source-token fence for current readers after hot rollback-journal recovery.

Dependency closure: no new support component is needed. The slice reuses native PHP rollback-journal recovery, WAL parsing/reader snapshots, and source-token hashing.

Next task: continue with broader pager/VFS checkpoint or transaction application; avoid another hot-journal checkpoint wrapper unless it adds a distinct persistence or source-ordering rule.
