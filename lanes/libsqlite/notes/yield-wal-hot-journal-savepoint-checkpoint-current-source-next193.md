# WAL hot-journal savepoint checkpoint current-source next193

Slice: `wal-hot-journal-savepoint-checkpoint-current-source-next193`

Behavior added:

- Adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`, a bounded planner for publishing retry WAL reader-mark slots after the accepted next187 hot-journal retry-source handoff.
- The new behavior admits reader marks only when the next187 retry reader token is current, stale post-apply tokens are retired, expected pages are covered, checkpoint-database marks have no WAL frame, and next-WAL marks carry a positive frame index.
- This models the WordPress import retry case where readers must not keep a mark pinned to the hot-journal-recovered source after the retry WAL source is admitted.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext193Test.php`
- Result: `1 test files, 56 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next193.php --self-test`
- Result: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next193 self-test passed`
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext193Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next193.php`
- Result: no syntax errors.

Non-overlap:

- This does not repeat hot-journal recovery, VFS apply, rollback-journal apply, savepoint byte truncation, checkpoint transactions, next184 salt/checkpoint source separation, or next187 token retirement.

Dependency closure:

- No new support component is needed. The slice composes next187 retry-source admission with lane-local reader-mark slot metadata.
