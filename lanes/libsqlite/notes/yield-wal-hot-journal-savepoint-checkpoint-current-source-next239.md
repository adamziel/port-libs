# WAL hot-journal savepoint checkpoint current-source next239

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, an atomic commit barrier after accepted next236 statement finalizers. It admits a checkpoint current-source switch only when database, WAL, hot-journal delete, and directory receipts share the same source token, writer generations, schema cookie, database digest, exclusive lock, fsync evidence, and finalized statement coverage.

WordPress smoke:

- `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next239.php` models a copied `wp_options` import switching reopened readers only after the database image, restarted WAL, deleted hot journal, and directory fsync receipts all cover finalized statements under one atomic barrier.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext239Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next239.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext239Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next239.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +87` focused PASS lines over the current lane status baseline (`119121 -> 119208`). Mapped upstream coverage remains `642 / 1589`; this is focused PHP behavior over existing WAL hot-journal/savepoint/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: next239 validates an atomic commit barrier after next236 finalizers. It does not repeat durable publication receipts, statement finalizers, reader-slot admission, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, VFS sync apply, super-journal commits, checkpoint transaction planning, B-tree, JSON, SELECT, or encoding surfaces.

Dependency closure: no new support component is needed; this reuses next236 finalizer admission plus existing native VFS fsync, lock, WAL reset, hot-journal delete, and directory commit receipt evidence.
