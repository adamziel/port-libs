# WAL hot-journal checkpoint restart current-source next129

- Slice: `wal-hot-journal-checkpoint-restart-current-source-next129`.
- Behavior: after rollback-journal hot recovery, `SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan` distinguishes a pinned current reader that preserves the current WAL bytes from the released `RESTART` checkpoint that writes the checkpointed database image plus a header-only restarted WAL generation.
- WordPress path: copied `wp_options` import/repair tooling can report why a hot-journal recovery cannot reset `-wal` while a reader is pinned, then verify the next-reader restart generation after the reader releases.
- Non-overlap: avoids accepted WAL byte truncation, rollback-journal apply/commit, VFS savepoint rollback, WAL checkpoint transaction planning, reader checkpoint restart savepoint next127, and hot-journal reader checkpoint next120/122 visibility-only surfaces by adding the restart-generation operation boundary.
- Dependency closure: no new support component needed; this reuses native PHP rollback-journal recovery and WAL durable checkpoint primitives.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalCheckpointRestartCurrentSourceNext129Test.php`
  - `php lanes/libsqlite/examples/wordpress-wal-hot-journal-checkpoint-restart-current-source-next129.php`
  - PHP lint for changed PHP files.
  - `git diff --check -- lanes/libsqlite`
