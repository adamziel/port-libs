# WAL hot-journal checkpoint reader current-source next135

- Slice: `wal-hot-journal-checkpoint-reader-current-source-next135`.
- Behavior: after hot rollback-journal recovery and a checkpoint-allowed current WAL reader source, `SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan` verifies that the current reader remains pinned to its WAL source while a later writer opens a separated next WAL generation.
- Application path: copied `wp_options` import/repair tooling can explain why an active reader keeps the recovered current-source page images while subsequent option updates move into a new WAL generation.
- Non-overlap: avoids accepted next132 stale/current source checkpoint gating by adding next-generation WAL separation after the checkpoint-allowed current reader; does not repeat WAL byte truncation, checkpoint transaction planning, rollback-journal apply/commit, VFS writer/sync/lock application, or hot-journal reader restart next131.
- Dependency closure: no new support component needed; this composes existing native PHP WAL parsing, rollback-journal hot recovery, and current-source reader validation.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalCheckpointReaderCurrentSourceNext135Test.php` => `1 test files, 59 assertions, 0 failures`.
  - `php lanes/libsqlite/examples/application-wal-hot-journal-checkpoint-reader-current-source-next135.php`
  - PHP lint for changed PHP files.
  - `git diff --check -- lanes/libsqlite`
