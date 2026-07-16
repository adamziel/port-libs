# WAL hot-journal savepoint checkpoint current-source next252

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, which verifies the durable post-truncate current-source seal after next248 has admitted reader release and checkpoint WAL truncation. The seal requires WAL truncation, SHM mxFrame reset, read-mark reset, hot-journal unlink, directory sync, released-reader coverage, checkpoint-page coverage, exclusive lock retention, and closed savepoint scope before advancing to the next current-source generation.

Application smoke:

- `examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next252.php` models a copied Application database checkpoint after front-page, plugin-cache, and import readers have released. It verifies that the post-truncate seal can advance the source generation only after the WAL, SHM, readmarks, hot journal, and directory entry are durable.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext252Test.php
php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next252.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext252Test.php
php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next252.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 94 assertions, 0 failures`, producing `+94` focused PASS lines over the current lane status baseline (`129612 -> 129706`). Mapped upstream coverage remains `659 / 1589`; this is current-source focused PHP behavior coverage.

Non-overlap: next252 verifies the durable post-truncate current-source seal after next248 reader release/truncation admission. It does not repeat next248 release/truncate admission, next245 reader admission, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, or the accepted batch216 next248 WAL hot-journal checkpoint behavior.

Dependency closure: no new support component is needed; this reuses next248 truncation admission with native PHP receipt checks for WAL truncation, SHM/read-mark reset, hot-journal unlink, directory sync, released reader coverage, and checkpoint page coverage.
