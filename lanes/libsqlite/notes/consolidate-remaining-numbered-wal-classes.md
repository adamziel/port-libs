# Consolidate Remaining Numbered WAL Classes

Scope: `consolidate-remaining-numbered-wal-classes`.

This slice consolidates a focused WAL/VFS application family that still exposed generated numbered production helper names. The public production entry points now use stable descriptive names:

- `SQLiteVfsFileWriter::applyWalCheckpointHotJournalReader()`
- `SQLiteVfsFileWriter::applyWalHotJournalSavepointCheckpoint()`
- `SQLiteVfsFileWriter::publishWalHotJournalSavepointCheckpoint()`
- `SQLiteVfsFileWriter::applyWalHotJournalStatementRollback()`
- `SQLiteVfsFileWriter::applyHotJournalSavepointCheckpoint()`
- `SQLiteVfsFileWriter::applyHotJournalSavepointCheckpointPinnedReader()`
- `SQLiteWalCheckpointHotJournalReaderCurrentSourceNextPlan::checkpointHotJournalReaderPlan()`
- `SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::publishDurableHotJournalSavepointCheckpointPlan()`
- `SQLiteWalHotJournalSavepointReplayPlan::statementHotJournalRollbackPlan()`

Direct WAL tests and WordPress examples for this focused family were renamed away from generated numeric filenames and migrated to the descriptive methods above.

Verification:

- `php -l` passed for the changed production PHP files, migrated tests, and migrated examples.
- `php tools/run-tests.php` over the 10 migrated WAL test files passed: `10 test files, 574 assertions, 0 failures`.
- The 10 migrated WordPress examples were linted; examples with `--self-test` passed and the remaining examples executed successfully.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component needed; this consolidation only renames existing native WAL/VFS planning and apply surfaces and preserves the existing behavior.

Follow-up: broader WAL production source still contains other historical numbered method/status families outside this focused patch; continue consolidating those in subsequent WAL consolidation lanes without adding compatibility shims.
