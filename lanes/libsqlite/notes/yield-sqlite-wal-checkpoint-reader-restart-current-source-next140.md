# WAL checkpoint reader restart current-source next140

- Behavior: `SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan` covers a reader that restarts its read transaction on the still-open original WAL source after an unpinned `RESTART` checkpoint has replaced the filesystem `-wal` path with a new generation. It contrasts the old reader frame, the restarted current-source reader frame, the checkpoint database image, and a fresh path reader on the restarted generation.
- WordPress smoke: `examples/wordpress-wal-checkpoint-reader-restart-current-source-next140.php` models copied `wp_options` / cron / transient / rewrite-session pages so the current WordPress reader can keep its original source while a fresh path reader sees the restarted WAL generation.
- Focused verification:
  - `php -l lanes/libsqlite/src/SQLiteWalCheckpointReaderRestartCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteWalCheckpointReaderRestartCurrentSourceNext140Test.php`
  - `php -l lanes/libsqlite/examples/wordpress-wal-checkpoint-reader-restart-current-source-next140.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointReaderRestartCurrentSourceNext140Test.php`
  - `php lanes/libsqlite/examples/wordpress-wal-checkpoint-reader-restart-current-source-next140.php --self-test`
  - `git diff --check -- lanes/libsqlite`
- Dependency closure: no new support component is needed; this reuses the native PHP WAL parser, durable checkpoint result, and reader snapshot page image helpers.
- Non-overlap: avoids accepted next136 consecutive restart generation behavior, next133 path replacement behavior, savepoint byte truncation, checkpoint transaction planning, hot-journal restart/truncate, and VFS writer/apply clusters. The new surface is the restarted read transaction staying bound to the original current WAL source while fresh path readers bind to the restarted generation.
