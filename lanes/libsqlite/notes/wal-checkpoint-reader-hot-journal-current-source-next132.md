# WAL checkpoint reader hot-journal current-source next132

- Slice: `wal-checkpoint-reader-hot-journal-current-source-next132`.
- Behavior: adds `SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan`, which validates that a reader-held WAL source still matches the current WAL source after hot rollback-journal recovery before allowing a restart checkpoint reset. If the reader source is stale, the plan restores the hot-journal database image but preserves the current WAL and requires reader reopen before checkpoint reset.
- WordPress path: copied `wp_options` import/repair tooling can now report when a stale reader WAL source must reopen after hot-journal recovery instead of letting checkpoint reset race against the current source boundary.
- Focused evidence:
  - `php -l lanes/libsqlite/src/SQLiteWalCheckpointReaderHotJournalCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteWalCheckpointReaderHotJournalCurrentSourceNext132Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-wal-checkpoint-reader-hot-journal-current-source-next132.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderHotJournalCurrentSourceNext132Test.php` -> `1 test files, 60 assertions, 0 failures`
  - `php lanes/libsqlite/examples/wordpress-wal-checkpoint-reader-hot-journal-current-source-next132.php` -> stale reader source detected, checkpoint disallowed, reader reopen required.
- Dashboard delta: `phpPass` moves from `55029` to `55089` for the 60 verified PASS lines. Mapped upstream coverage remains conservative because this is an additional behavior-backed current-source WAL guard under existing WAL/checkpoint inventory.
- Non-overlap: avoids accepted WAL hot-journal restart next129, reader checkpoint savepoint truncate next130, hot-journal reader visibility next120/122, WAL byte truncation, rollback-journal apply/commit, VFS savepoint rollback, checkpoint transaction planning, and VFS writer/sync/lock clusters. The new surface is reader WAL source validation before checkpoint reset after hot-journal recovery.
- Dependency closure: no new support component is needed; this reuses native PHP rollback-journal recovery, WAL parsing, reader snapshot, and restart checkpoint primitives.
- Next task: continue with broader pager/VFS transaction application or another distinct WAL durability edge; avoid another checkpoint-reader wrapper unless it applies a new current-source transition.
