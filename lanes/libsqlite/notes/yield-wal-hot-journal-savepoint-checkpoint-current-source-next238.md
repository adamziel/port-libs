# WAL hot-journal savepoint checkpoint current-source next238

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-publication writer-generation fence after accepted next235 durable publication. It admits the first next writer only when reopened readers observe the same database digest, schema cookie, WAL salt, zero-frame restarted WAL, absent hot journal, clean page cache, checkpoint-covered pages, and shared read locks.

WordPress smoke:

- `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next238.php` models a copied `wp_options` import admitting writer generation 239 only after reopened readers are pinned to the clean current source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext238Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next238.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext238Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next238.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: focused `phpPass +72` PASS lines from a new lane test file. Mapped upstream coverage remains `641 / 1589`; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: next238 gates the first post-publication writer after next235 durable publication. It avoids accepted checkpoint byte materialization, savepoint byte truncation, VFS writer/savepoint rollback application, rollback-journal apply/commit, reader-slot admission, durable publication receipt validation, B-tree, JSON, SELECT, and encoding surfaces.

Dependency closure: no new support component is needed; this reuses next235 durable publication plus lane-local reader reopen receipts, WAL read-mark zero, shared-lock, and hot-journal absence evidence.
