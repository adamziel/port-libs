# WAL hot-journal savepoint checkpoint current-source next175

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next175`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next175Plan()`. It builds on next172 checkpoint publication receipts and adds the page-cache seal gate required before reopened readers can reuse checkpointed current-source pages. Every checkpoint page must have a clean, sealed receipt for the published current source token, epoch, and checkpoint digest; missing, dirty, unsealed, stale-source, and digest-mismatched cache receipts remain blocked.

Application path: `application-wal-hot-journal-savepoint-checkpoint-current-source-next175.php` models copied `wp_options` import recovery where hot-journal pages and savepoint rollback pages are checkpointed and synced, then reopened reader cache reuse is admitted only after all checkpoint pages are sealed to the current source.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext175Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next175.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext175Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next175.php --self-test`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed. The slice reuses native PHP WAL parsing, next166 savepoint release lineage, next172 checkpoint publish receipts, and lane-local page-cache source sealing.

Non-overlap: avoids next172 database/WAL sync receipt admission, next176 hot-journal delete reader tickets, accepted WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, and checkpoint transaction planning. The new behavior is clean page-cache seal admission before reopened reader-cache reuse.
