# WAL reader restart checkpoint current-source next136

- Slice: `wal-reader-restart-checkpoint-current-source-next136`.
- Behavior: `SQLiteWalReaderRestartCheckpointCurrentSourceNextPlan` proves that a reader pinned to the original WAL byte source keeps resolving pages from that source across two consecutive `RESTART` checkpoints, while fresh path reopens advance through the first and second restarted WAL generations.
- Application path: copied `wp_options` imports can explain why a long-lived reader keeps its original `.ht.sqlite-wal` handle even after maintenance replaces the WAL path twice during checkpoint/restart cycles.
- Non-overlap: avoids accepted `next133` single restart replacement, `next119` restart/truncate read-mark, WAL checkpoint transaction, savepoint byte truncation, rollback-journal apply/commit, and VFS writer/apply clusters.
- Dependency closure: no new support component needed; this reuses the existing native PHP WAL parser, durable checkpoint result, and current-source reader snapshot helpers.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalReaderRestartCheckpointCurrentSourceNext136Test.php` => `1 test files, 82 assertions, 0 failures`.
  - `php lanes/libsqlite/examples/application-wal-reader-restart-checkpoint-current-source-next136.php --self-test` => self-test passed.
  - PHP lint for changed PHP files.
  - `git diff --check -- lanes/libsqlite`.
