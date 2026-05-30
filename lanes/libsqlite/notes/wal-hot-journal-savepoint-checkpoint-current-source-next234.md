# WAL Hot-Journal Savepoint Checkpoint Current Source Next234

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next234`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It verifies a durable current-source handoff after the accepted next231 WAL-index reopen/readmark fence: the repaired source is only servable when database, WAL, SHM, journal-unlink, directory-sync, reader-cache, writer-generation, checkpoint-cookie, schema-cookie, and WAL-digest receipts all match the reopened checkpoint source.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next234.php` models a copied `wp_options` import after hot-journal recovery and savepoint checkpoint publication. It holds the repaired source until the checkpointed database, restarted WAL, synced SHM, unlinked hot journal, and containing directory receipts are all durable.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext234Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next234.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext234Test.php`
  - `1 test files, 80 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next234.php --self-test`
  - `application-wal-hot-journal-savepoint-checkpoint-current-source-next234 self-test passed`

Expected dashboard delta: `phpPass` moves from `115305` to `115385` from 80 newly passing focused PASS lines. Mapped upstream coverage remains `637 / 1589`; this is focused WAL/pager current-source durability behavior over existing mapped journal/WAL inventory rather than a new upstream manifest row.

Non-overlap: avoids accepted next230-next231 WAL hot-journal savepoint checkpoint behavior, next231 readmark reopen checks, next227 publish sealing, next218 reset admission, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, VFS sync planning/apply, locked writer, WAL checkpoint transactions, pager master-journal reader-cache, B-tree, JSON, SQL executor, and encoding clusters. The new behavior is the durable database/WAL/SHM/journal/directory handoff receipt fence after the reopened WAL-index current source.

Dependency closure: no new support component is needed. The slice reuses lane-local WAL-index reopen metadata plus bounded VFS sync/file-handle receipt metadata.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another next231/next234 receipt wrapper unless it applies a new durable state transition.
