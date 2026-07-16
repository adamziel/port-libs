# WAL hot-journal savepoint checkpoint current-source next235

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a durable publication receipt fence after accepted next232 reader-slot admission. It admits reopened Application readers only when database, WAL, hot-journal delete, and directory fsync receipts all match the same source token, writer generation, schema cookie, WAL salt, checkpoint pages, and lock receipt.

Application smoke:

- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next235.php` models copied `wp_options` readers staying on the current source only after the checkpoint database image, restarted WAL sidecar, deleted hot journal, and containing directory are durably published.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext235Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next235.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext235Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next235.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +80` focused PASS lines, from `116027` to `116107`. Mapped upstream coverage remains `638 / 1589`; this is focused PHP behavior over existing WAL hot-journal/savepoint/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: next235 validates durable publication receipts after next232 reader-slot admission. It avoids accepted WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, file-writer byte application, WAL-index reopen receipts, reader-slot admission, B-tree, JSON, SELECT, and encoding surfaces.

Dependency closure: no new support component is needed; this reuses next232 reader-slot admission plus native VFS file, journal-delete, WAL reset, and directory-sync receipt evidence.
