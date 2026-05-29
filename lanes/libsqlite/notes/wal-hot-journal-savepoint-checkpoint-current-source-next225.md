# WAL hot-journal savepoint checkpoint current-source next225

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next225`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It models the publish boundary after next219 savepoint-scope finalization: database-header current-source publication is admitted only when database-header, WAL-index-header, and change-counter write receipts all match the finalized checkpoint frame, checkpoint cookie, schema cookie, source token, next-source epoch, and savepoint-scope digest. Stale hot-journal header bytes, unsynced header writes, stale source ids/epochs, and cookie/digest mismatches hold the current source instead of publishing potentially stale database header state.

WordPress smoke: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next225.php` covers a copied `wp_options` import that recovers a hot journal, finalizes plugin savepoints, checkpoints WAL frames, and admits the database header only after all three header receipt regions match the checkpoint cookies.

Focused verification:

```text
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext225Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next225.php
No syntax errors detected in lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext225Test.php
No syntax errors detected in lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next225.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext225Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 62 assertions, 0 failures

php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next225.php
status: wal-hot-journal-savepoint-checkpoint-current-source-next225
headerPublished: true
publishedRegions: change-counter, database-header, wal-index-header
```

Expected dashboard delta: `phpPass` increases by 62 focused PASS lines when accepted. Mapped upstream coverage is unchanged; this is focused WAL/pager publication behavior over existing hot-journal/checkpoint inventory.

Non-overlap: this avoids accepted next219 savepoint-scope finalization, next212 passive reader pins, next172 sync receipts, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, VFS writer/sync/lock clusters, and checkpoint transaction planning. The new surface is database-header receipt admission after savepoint-finalized WAL checkpoint publication.

Dependency closure: no new support component is needed. The slice reuses lane-local next219 savepoint-scope publication metadata, checkpoint cookies, WAL-index header metadata, and native database header write receipts.
