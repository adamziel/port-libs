# Consolidate numbered WAL current-source plans

This consolidation replaces the duplicate numbered production families for `SQLiteWal*CurrentSourceNextPlan` with canonical classes named by removing only the numeric suffix:

- `SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan`
- `SQLiteWalHotJournalCheckpointReaderCurrentSourceNextPlan`
- `SQLiteWalHotJournalReaderRestartCurrentSourceNextPlan`
- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan`
- `SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNextPlan`

The canonical classes expose explicit `nextNN...` variant entry methods so migrated tests and examples can keep exercising the accepted behavior while production no longer carries separate numbered classes for those duplicate families. Singleton WAL current-source families were left untouched.

Verification:

- `php -l` passed for the five canonical classes plus direct modified source-reference files.
- `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 \( -name 'SQLiteWalCheckpointHotJournalReaderCurrentSourceNext*Test.php' -o -name 'SQLiteWalHotJournalCheckpointReaderCurrentSourceNext*Test.php' -o -name 'SQLiteWalHotJournalReaderRestartCurrentSourceNext*Test.php' -o -name 'SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext*Test.php' -o -name 'SQLiteWalReaderCheckpointSavepointTruncateCurrentSourceNext*Test.php' \) | sort)` passed: 102 selected test files, 7602 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next262.php` passed and emitted the canonical next262 admitted reader-cache summary.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed; this is production-class consolidation only and reuses the existing WAL/Pager/VFS helpers.

Non-overlap: this does not implement a new WAL behavior and does not repeat accepted checkpoint, savepoint byte truncation, rollback-journal, VFS writer, or reader snapshot behavior. It only consolidates the numbered WAL duplicate class families requested by the supervisor override.
