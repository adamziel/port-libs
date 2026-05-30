# WAL hot-journal savepoint checkpoint current-source next172

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next172`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It builds on the current-source hot-journal/savepoint/checkpoint release-lineage planner and adds the publish gate SQLite needs before reopening readers on the next WAL source: every checkpointed database page must have a synced write receipt for the current source token/epoch and matching checkpoint image, and the next WAL sidecar must have a synced digest/source receipt. Missing, unsynced, stale-source, mismatched-image, stale-WAL, and incomplete release-lineage paths remain blocked.

Application path: `application-wal-hot-journal-savepoint-checkpoint-current-source-next172.php` models copied `wp_options` import recovery where a hot journal restores root/autoload pages, an inner savepoint rolls back active plugin/cron retry pages, checkpoint writes must sync before publication, and stale reader cache pages are invalidated before the next WAL source is visible.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext172Test.php`
- `php -l lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next172.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext172Test.php`
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next172.php --self-test`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 64 assertions, 0 failures`.

Dashboard delta: `phpPass` moves from `76936` to `77000` for the 64 verified PASS lines. Mapped coverage remains `611 / 1589`; this is focused WAL/pager publish-gate behavior over already mapped hot-journal/savepoint/checkpoint inventory rather than a new upstream denominator row.

Dependency closure: no new support component is needed. The slice reuses native PHP WAL parsing, next166 release lineage, checkpoint current-source fencing, and VFS write/sync receipt concepts.

Non-overlap: avoids accepted next166 savepoint release lineage, next161 cache-token fencing, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit/super-journal, WAL checkpoint transaction planning, VFS writer/sync/locked-writer/process-lock clusters, B-tree, JSON, SELECT, and encoding surfaces. The new behavior is database/WAL sync-receipt admission before current-source checkpoint publication.

Next task: continue with broader pager/VFS transaction application or another non-overlapping WAL durability edge; avoid another savepoint wrapper unless it applies a distinct publish or persistence boundary.
