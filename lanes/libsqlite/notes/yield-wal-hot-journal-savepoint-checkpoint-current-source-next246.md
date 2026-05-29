# WAL hot-journal savepoint checkpoint current-source next246

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which validates the durable VFS handoff after the accepted next243 reopened-reader snapshot admission. The plan admits the current source only when database dirty-page writes, WAL commit-frame markers, database/WAL/directory sync receipts, exclusive-lock state, replayable savepoint state, and hot-journal deletion happen in the safe order.

WordPress smoke:

- `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next246.php` models a copied WordPress import promoting a hot-journal savepoint checkpoint after schema, `wp_options`, and autoload-index page writes reach durable storage.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext246Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next246.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext246Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next246.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 90 assertions, 0 failures`.

Expected dashboard movement: `phpPass +90` focused PASS lines over the current lane status baseline (`125265 -> 125355`). Mapped upstream coverage remains `650 / 1589`; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint current-source inventory.

Non-overlap: next246 validates durable VFS handoff ordering after next243 reader admission. It does not repeat reader snapshot matching, checkpoint transaction planning, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, VFS sync planning/apply, file locking, or SELECT/JSON/B-tree surfaces.

Dependency closure: no new support component is needed; this reuses native PHP VFS write receipts, sync targets, WAL commit-frame metadata, hot-journal delete receipts, and next243 reader snapshot admission.
