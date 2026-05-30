# WAL hot-journal reader restart current-source next131

- Slice: `wal-hot-journal-reader-restart-current-source-next131`.
- Behavior: after hot rollback-journal recovery and a busy `RESTART` checkpoint, `SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan` verifies that a restarted current reader reuses the preserved current WAL bytes while later readers bind to the released header-only restart generation.
- Application path: copied `wp_options` import/repair tooling can explain why the current reader remains on the old WAL source after hot-journal recovery, while the next open sees the checkpoint database plus restarted WAL generation.
- Non-overlap: avoids accepted `next129` restart checkpoint operation coverage by adding the current-reader restart admission boundary; does not repeat rollback-journal apply/commit, savepoint byte truncation, WAL checkpoint transaction planning, or reader snapshot-only surfaces.
- Dependency closure: no new support component needed; this composes existing native PHP rollback-journal hot recovery and WAL restart checkpoint primitives.
- Verification:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalReaderRestartCurrentSourceNext131Test.php`
  - `php lanes/libsqlite/examples/application-wal-hot-journal-reader-restart-current-source-next131.php`
  - PHP lint for changed PHP files.
  - `git diff --check -- lanes/libsqlite`
