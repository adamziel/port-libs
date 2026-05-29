# WAL hot-journal savepoint checkpoint current-source next263

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next263`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next263SealRetryReadReceipts()`. It builds on accepted next262 reader-cache admission and requires each retry read to close with matching current-source token, database digest, page-cache digest, schema cookie, checkpoint frame, retry name, reader name, and page number before the retry reader receipts are sealed.

WordPress smoke: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next263.php` models a copied `wp_options` import that retries option and autoload reads after checkpoint publication, then closes those readers only after no hot-journal or stale WAL generation is visible.

Validation:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext263Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next263.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext260Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext261Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext262Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext263Test.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next260.php --self-test`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next261.php --self-test`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next262.php`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next263.php --self-test`
- `composer dump-autoload`
- `git diff --check`

Dependency closure: no new support component is needed. The slice reuses the admitted next262 reader-cache fence and adds lane-local retry close receipts.

Non-overlap: this does not repeat next260 checkpoint admission, next261 publish sealing, next262 cache fencing, WAL byte truncation, rollback-journal apply/commit, VFS sync/apply, SQL, JSON, encoding, B-tree, suite, status, progress, dashboard, supervisor, or private-file surfaces.
