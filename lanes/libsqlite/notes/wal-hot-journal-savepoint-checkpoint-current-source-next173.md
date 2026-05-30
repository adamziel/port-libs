# WAL hot-journal savepoint checkpoint current-source next173

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next173`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It composes the existing next167 current/next WAL token and publication-fingerprint guard with filesystem byte-hash admission for the database, hot rollback journal, and WAL sidecar before publishing the checkpoint operation order.

Application smoke: `application-wal-hot-journal-savepoint-checkpoint-current-source-next173.php` models a copied `wp_options` import crash where the prepared checkpoint can publish only when the database, journal, and WAL bytes still match the guarded current-source hashes; a stale WAL byte stream blocks publication before deleting the hot journal or writing checkpoint bytes.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext173Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next173.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext173Test.php` -> `1 test files, 60 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next173.php`

Expected dashboard delta: `phpPass` moves from `77680` to `77740` from 60 newly passing focused PASS lines. Mapped upstream coverage remains `612 / 1589`; this is focused WAL/pager current-source behavior over existing checkpoint/hot-journal/savepoint inventory rather than a new manifest row.

Non-overlap: avoids accepted next167 WAL token/fingerprint publication guard, next163 pinned-reader restart checkpoint, next160 truncate/no-reader apply path, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, WAL checkpoint transactions, VFS writer/sync/lock clusters, and pager master-journal cache slices. The new behavior is filesystem byte-hash admission before durable hot-journal savepoint checkpoint publication.

Dependency closure: no new support component is needed. The slice reuses native PHP WAL parsing, hot-journal/savepoint checkpoint current-source guards, and existing VFS write-ordering primitives.

Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another checkpoint wrapper unless it applies a new source-ordering or durable-write rule.
