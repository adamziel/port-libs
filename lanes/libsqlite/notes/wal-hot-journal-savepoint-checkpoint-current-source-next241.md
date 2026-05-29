# WAL Hot-Journal Savepoint Checkpoint Current Source Next241

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next241`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a post-publication writer-commit fence after accepted next238 writer admission. It advances the next reader to writer generation 241 only when commit, WAL, lock, and directory receipts all match the admitted current source, WAL salt, schema cookie, database digest, checkpoint-covered pages, committed frame set, WAL sync, commit marker, reserved-lock release, preserved shared-lock state, and persisted sidecars.

WordPress smoke: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next241.php` models a copied `wp_options` import where the first autoload-option writer after a hot-journal savepoint checkpoint is not visible to the next reader until committed WAL frames and file-handle receipts are durable.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext241Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next241.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext241Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next241.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: focused `phpPass +91` PASS lines from the new lane test file. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over the existing WAL hot-journal/savepoint/checkpoint inventory rather than a fresh manifest row.

Non-overlap: next241 gates reader advancement after the first post-publication writer commit. It avoids accepted next238 reader reopen admission, next235 durable publication receipts, next233 prepared-statement admission, next223 publication receipt fencing, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal apply/commit, WAL file byte materialization, B-tree, JSON, SELECT, and encoding surfaces.

Dependency closure: no new support component is needed; this reuses next238 writer admission plus native WAL frame sync, commit marker, lock-release, and directory fsync receipts.
