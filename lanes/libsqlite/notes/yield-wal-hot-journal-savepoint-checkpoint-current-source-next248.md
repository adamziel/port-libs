# WAL hot-journal savepoint checkpoint current-source next248

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which gates checkpoint WAL truncation until every reopened reader admitted by the next245 checkpoint source has released its snapshot, clean page cache, shared lock, hot-journal fence, and savepoint scope. This covers the later reader-release/truncate lifecycle rather than repeating next245 reopened-reader admission.

Application smoke:

- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next248.php` models `wp_options`, `wp_posts`, and plugin-cache readers releasing after a copied Application import checkpoint so the WAL can be truncated.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext248Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next248.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext248Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next248.php
git diff --check -- lanes/libsqlite
```

Expected dashboard movement: `phpPass +89` focused PASS lines over the current lane status baseline (`126252 -> 126341`). Mapped upstream coverage remains `651 / 1589`; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint current-source inventory.

Non-overlap: next248 gates WAL truncation after admitted reopened readers release. It does not repeat next245 reopened-reader admission, writer commit receipt validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, reader checkpoint snapshots, or the accepted batch213 next244/next245 WAL hot-journal checkpoint behavior.

Dependency closure: no new support component is needed; this reuses next245 reopened-reader admission metadata plus native PHP release receipts, page-cache coverage, WAL frame, lock, hot-journal, and savepoint fence metadata.
