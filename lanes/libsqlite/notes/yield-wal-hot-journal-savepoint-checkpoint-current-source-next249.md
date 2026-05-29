# WAL hot-journal savepoint checkpoint current-source next249

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which verifies reopened file visibility after an admitted next246 durable handoff. The plan admits the current source only when reopened database/page-cache digests match, WAL commit frames are neither missing nor extra, all dirty pages reopen clean, the hot journal is gone, the WAL sidecar remains available for reader continuity, and every accepted reader reopens on the same source token, generation, and checkpoint frame.

WordPress smoke:

- `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next249.php` models a copied WordPress import reopening after a hot-journal savepoint checkpoint and confirming schema, `wp_options`, and autoload readers observe the same durable current source.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext249Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next249.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext249Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next249.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 99 assertions, 0 failures`.

Expected dashboard movement: `phpPass +99` focused PASS lines over the current lane status baseline (`127481 -> 127580`). Mapped upstream coverage remains `654 / 1589`; this is focused PHP behavior over the existing WAL hot-journal/savepoint/checkpoint current-source inventory.

Non-overlap: next249 verifies reopened current-source visibility after an accepted next246 durable handoff. It does not repeat VFS receipt ordering, checkpoint transaction planning, WAL byte truncation, savepoint rollback application, rollback-journal commit/apply, reader snapshot admission, JSON, SELECT, or B-tree behavior.

Dependency closure: no new support component is needed; this reuses next246 durable handoff metadata, reopened file digests, WAL commit-frame inventory, clean page-cache inventory, and reader epoch rows.
