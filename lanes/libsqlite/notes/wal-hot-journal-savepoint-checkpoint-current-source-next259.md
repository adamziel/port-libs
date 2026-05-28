# WAL hot-journal savepoint checkpoint current-source next259

Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Plan`, which admits the first writer generation after the next252 post-truncate current-source seal. The admission requires a fresh WAL header salt, reset SHM `mxFrame`, reset read marks, advanced schema cookie, confirmed hot-journal absence, durable receipts, and writer-lock release ordering.

WordPress smoke:

- `examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next259.php` models a WordPress database copy/import path where front-page, plugin-cache, and import readers have released and the checkpoint reset has sealed. It verifies that a new writer generation is admitted only after WAL/SHM/readmark/schema-cookie fences are current and durable.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Plan.php
php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Test.php
php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next259.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Test.php
php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next259.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 94 assertions, 0 failures`, producing `+94` focused PASS lines over the current lane status baseline (`136435 -> 136529`). Expected dashboard movement is focused PHP PASS-line growth only; mapped upstream coverage remains unchanged because this is current-source behavior coverage.

Non-overlap: next259 admits the first writer generation after a sealed post-truncate checkpoint. It does not repeat next252 sealing, next248 release/truncate admission, durable page writes, WAL byte truncation, rollback-journal apply/commit, VFS savepoint rollback, VFS sync, SELECT, JSON, or B-tree surfaces.

Dependency closure: no new support component is needed; this reuses next252 post-truncate seal metadata with native PHP WAL-header, SHM, readmark, schema-cookie, hot-journal absence, and writer-lock receipt checks.
