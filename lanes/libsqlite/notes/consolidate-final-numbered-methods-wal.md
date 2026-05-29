# Consolidate final numbered WAL methods

This consolidation removes direct production method names in the WAL/pager-WAL
surface that ended in generated `CurrentNextNN` or `CurrentSourceNextNN`
suffixes, and migrates their direct tests and WordPress examples to stable
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
- Changed WordPress examples passed with `php <example> --self-test`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed; this is a naming
consolidation over existing WAL, pager, cache-spill, savepoint, and VFS helper
surfaces.

Follow-up: the separate high-volume
`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::nextNN*` method
cluster remains as a distinct generated-method consolidation target. It encodes
step-specific transitions and should be collapsed with a dedicated canonical
step dispatcher rather than hidden compatibility shims.
