# WAL hot-journal reader restart current-source next143

- Behavior: `SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan` composes hot rollback-journal recovery with WAL reader restart source tracking. A current reader remains pinned to the hot-recovered current WAL source while a later opener uses a distinct restarted WAL generation.
- Application smoke: `examples/application-wal-hot-journal-reader-restart-current-source-next143.php` models a copied `wp_options` database that recovers hot rollback-journal pages, preserves the current reader source, and separates later option writes onto a restarted `-wal` generation.
- Focused verification:
  - `php -l lanes/libsqlite/src/SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan.php`
  - `php -l lanes/libsqlite/tests/SQLiteWalHotJournalReaderRestartCurrentSourceNext143Test.php`
  - `php -l lanes/libsqlite/examples/application-wal-hot-journal-reader-restart-current-source-next143.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalReaderRestartCurrentSourceNext143Test.php`
  - `php lanes/libsqlite/examples/application-wal-hot-journal-reader-restart-current-source-next143.php`
  - `git diff --check -- lanes/libsqlite`
- Assertion delta: +67 focused assertions in one new test file.
- Dependency closure: no new support component is needed; this reuses native rollback-journal hot recovery, WAL parsing, and reader snapshot current-source helpers.
- Non-overlap: avoids accepted next131 hot-journal preserved-reader restart, next140 checkpoint reader restart, next136 restart generations, next134/137 truncate reader behavior, savepoint byte truncation, checkpoint transaction, and VFS writer/apply clusters. The new surface is the combined hot-recovered current source remaining pinned while a separate restarted WAL generation serves subsequent readers.
