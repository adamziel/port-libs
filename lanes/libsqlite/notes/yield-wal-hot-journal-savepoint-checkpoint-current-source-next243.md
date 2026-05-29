# WAL hot-journal savepoint checkpoint current-source next243

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which validates reopened reader snapshot admission after the accepted next240 autocheckpoint baseline. The plan admits the current source only when reopened WordPress readers match the source token, commit generation, schema cookie, database digest, page-cache digest, WAL-index salt/mxFrame, checkpoint frame, commit frames, clean page-cache state, shared lock, and closed savepoint/hot-journal state.

WordPress smoke:

- `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next243.php` models schema, `wp_options`, and autoload-index readers reopening after a copied WordPress import checkpoint.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext243Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next243.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext243Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next243.php --self-test
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +96` focused PASS lines over the current lane status baseline (`122940 -> 123036`). Mapped upstream coverage remains `647 / 1589`; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint current-source inventory.

Non-overlap: next243 validates reopened reader snapshot admission after next240 autocheckpoint baseline. It does not repeat checkpoint publication, WAL byte truncation, VFS savepoint rollback/apply, rollback-journal commit/apply, VFS sync/file writer, process locks, super-journal commits, SELECT/JSON/B-tree work, or the accepted next240 commit receipt baseline.

Dependency closure: no new support component is needed; this reuses next240 autocheckpoint baseline receipts plus native PHP WAL-index salt, page-cache digest, reader readmark, hot-journal, and savepoint-depth metadata.
