# WAL hot-journal savepoint checkpoint current-source next216

Status: focused PHP behavior growth for `wal-hot-journal-savepoint-checkpoint-current-source-next216`.

This slice adds `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`. It models the WAL checkpoint boundary after next212 PASSIVE checkpoint progress was stopped by current readers: active readers must close/release their pins, stale readers must reopen on the post-hot-journal source, and only then may a RESTART or TRUNCATE checkpoint advance from the previously checkpointed frame to the requested frame.

WordPress smoke: `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next216.php` models a copied `wp_options` import where the options import reader and cron reader drain, a stale plugin-settings reader reopens, and the copied database can truncate the retained WAL after the hot-journal/checkpoint recovery path.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext216Test.php`
- `php -l lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next216.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext216Test.php`
  - `1 test files, 80 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next216.php --self-test`
  - `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next216 self-test passed`

Expected dashboard movement: `phpPass` +80 from the 80 independent focused PASS lines in `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext216Test.php`. Mapped upstream coverage remains unchanged; this is focused WAL/pager current-source behavior over existing hot-journal/checkpoint inventory rather than a new manifest-backed upstream row.

Non-overlap: this avoids accepted next212 PASSIVE checkpoint pin detection, next209 writer fences, next206 statement-consumer admission, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal apply/commit, hot-journal recovery, and VFS writer/sync/lock clusters. The new behavior is specifically RESTART/TRUNCATE admission after the next212 current-reader pin drains and stale readers reopen.

Dependency closure: no new support component is needed. The slice reuses lane-local next212 passive checkpoint metadata, current-source digests, writer generation fences, and reader transition rows.

Next task: continue with a broader pager/VFS transaction application edge or a distinct WAL durability gap; avoid another checkpoint-reader wrapper unless it applies a different state transition.
