# WAL/VFS Numbered Method Consolidation Seventeenth Pass

This consolidation pass removes a small set of remaining numbered production
method/helper names from WAL/VFS current-source helpers without changing the
underlying behavior.

- Renamed WAL public entrypoints for hot-journal checkpoint reader and reader
  checkpoint/savepoint truncate helpers to descriptive unsuffixed method names.
- Renamed VFS legacy helper prefixes in URI/open/lock-byte and URI/SHM
  lock-byte helpers from worker-numbered names to stable descriptive names.
- Renamed two direct WAL checkpoint current-source seal wrappers to descriptive
  methods and migrated their direct tests/examples.

No new support component is needed; this reuses the existing native WAL, pager,
savepoint, rollback-journal, VFS open, and lock-byte helpers.

Verification:

- `php -l` on 27 changed PHP files passed with no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalCheckpointHotJournalReaderCurrentSourceNext144Test.php lanes/libsqlite/tests/SQLiteWalHotJournalCheckpointReaderCurrentSourceNext120Test.php lanes/libsqlite/tests/SQLiteWalHotJournalCheckpointReaderCurrentSourceNext135Test.php lanes/libsqlite/tests/SQLiteWalHotJournalReaderRestartCurrentSourceNext131Test.php lanes/libsqlite/tests/SQLiteWalHotJournalReaderRestartCurrentSourceNext143Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext724739Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext900915Test.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNext123Test.php lanes/libsqlite/tests/SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNext130Test.php` passed with `9 test files, 623 assertions, 0 failures`.
- Changed Application examples passed with `--self-test`.
- `git diff --check -- lanes/libsqlite` passed.
