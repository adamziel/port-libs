# WAL hot-journal savepoint replay current-next38

Status: focused PHP corpus growth for WAL replay after hot rollback-journal recovery when a current savepoint truncates the WAL prefix before the next reader opens.

Changes:
- Added `SQLiteWalHotJournalSavepointReplayPlan::replayCurrentNext()` to compose existing hot rollback-journal recovery, savepoint WAL prefix truncation, WAL transaction recovery, and current/next reader visibility into one bounded planner.
- Added `SQLiteWalHotJournalSavepointReplayCurrentNext38Test.php` with 67 independent PASS cases covering hot-journal-first ordering, savepoint prefix selection, current/next reader page sources, checkpoint images, skipped hot-journal paths, nested savepoints, and invalid input guards.
- Added `application-wal-hot-journal-savepoint-replay.php` to smoke a copied `wp_options` plugin import where a crashed hot rollback journal is recovered before failed savepoint WAL frames are omitted.

Verification:
- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointReplayPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointReplayCurrentNext38Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-replay.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointReplayCurrentNext38Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-replay.php --self-test`
- `git diff --check -- lanes/libsqlite`

Non-overlap:
- Avoids accepted hot rollback-journal recovery/application, rollback-journal commit/apply, super-journal commit, VFS savepoint rollback application, WAL savepoint byte truncation-only diagnostics, WAL savepoint recovery current/next, WAL checksum recovery, WAL append/checkpoint/restart/current-reader clusters, VFS writer/sync/lock clusters, JSON table source/cursor/constraint work, SELECT SQL text/subquery/group/order clusters, B-tree page/freelist clusters, and Unicode GLOB behavior.
- The new surface is the ordered combined replay decision after startup recovery: recover the hot rollback journal image first, then expose only the savepoint-retained WAL prefix to the current reader and the next recovered reader.

Dependency closure:
- No new support component is needed. This reuses lane-local rollback-journal parsing/recovery, savepoint WAL frame bookkeeping, WAL checksum/transaction recovery, and reader snapshot primitives.
