# Consolidate final numbered WAL methods

This consolidation removes direct production method names in the WAL/pager-WAL
surface that ended in generated `CurrentNextNN` or `CurrentSourceNextNN`
suffixes, and migrates their direct tests and Application examples to stable
descriptive method names.

Canonical method examples:

- `SQLiteWal::checkpointReaderPinSlotHandoffCurrentNext()`
- `SQLiteWal::checkpointRestartTruncateReaderPreserveCurrentSourceNext()`
- `SQLiteWalSavepointCheckpointPlan::checkpointReaderSavepointRecoveryCurrentSourceNext()`
- `SQLiteWalHotJournalSavepointReplayPlan::masterJournalCurrentSourceNext()`
- `SQLitePagerHotJournalWalRecoveryPlan::statementWalRecoveryCurrentSourceNext()`
- `SQLitePagerDirtyPageCacheSpillPlan::journalModeCurrentSourceNext()`

Focused verification:

- `git diff --name-only -- lanes/libsqlite | rg '\.php$' | xargs -r -n1 php -l`
  passed for all changed PHP files.
- `php tools/run-tests.php $(git diff --name-only -- lanes/libsqlite/tests | rg '\.php$')`
  passed with `36 test files, 12157 assertions, 0 failures`.
- Changed Application examples passed with `php <example> --self-test`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed; this is a naming
consolidation over existing WAL, pager, cache-spill, savepoint, and VFS helper
surfaces.

Follow-up: the separate high-volume
`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::nextNN*` method
cluster remains as a distinct generated-method consolidation target. It encodes
step-specific transitions and should be collapsed with a dedicated canonical
step dispatcher rather than hidden compatibility shims.

2026-05-29 thirty-seventh pass:

- Removed the public numbered WAL checkpoint wrappers
  `next1140AfterCurrentCheckpoint()` through `next1149AfterCurrentCheckpoint()`
  and `next1151AfterCurrentCheckpoint()` through `next1155AfterCurrentCheckpoint()`.
- Migrated the direct focused test and Application smoke to the stable
  `afterReadyCheckpointVerification()` dispatcher, while keeping the existing
  `pageCacheSourceTokenAfterCurrentCheckpoint()` descriptive step helper.
- Verification: `php -l` passed for the changed production, test, and example
  PHP files; focused test
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext11401155Test.php`
  passed with `1 test files, 78 assertions, 0 failures`; example smoke
  `php lanes/libsqlite/examples/application-wal-hot-journal-savepoint-checkpoint-current-source-next1155.php --self-test`
  passed; `git diff --check -- lanes/libsqlite` passed.
