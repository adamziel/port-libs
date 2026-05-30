# WAL Hot-Journal Savepoint Checkpoint Current-Source Entrypoint Consolidation

Consolidated six numbered production entrypoint names in
`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` into descriptive
stable methods:

- `hotJournalSavepointCheckpointCurrentSourcePlan()`
- `hotJournalSavepointCheckpointAppendPlan()`
- `savepointCheckpointReaderFrameFencePlan()`
- `checkpointDatabaseVisibilityPlan()`
- `publishDurableReaderCurrentSources()`
- `checkpointGenerationLeasePlan()`

The direct tests and WordPress smoke examples were renamed to matching
descriptive filenames. Observable status strings, dependency receipt keys, and
non-overlap text remain unchanged so existing current-source evidence keeps the
same behavior.

Verification:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php -l` for the six renamed direct tests and six renamed examples
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceHotJournalRecoveryTest.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAppendTest.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointReaderFrameFenceTest.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointDatabaseVisibilityTest.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourcePublicationTest.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointGenerationLeaseTest.php`
  - `6 test files, 420 assertions, 0 failures`
- Six renamed WordPress examples with `--self-test`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext*Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpoint*Test.php`
  - `2 test files, 11757 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component needed; this is naming
consolidation over the existing WAL, rollback journal, savepoint, checkpoint,
and VFS helper contracts.
