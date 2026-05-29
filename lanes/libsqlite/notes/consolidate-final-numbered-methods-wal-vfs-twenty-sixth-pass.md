## WAL/VFS Numbered Method Consolidation - Twenty-Sixth Pass

Consolidated eight remaining public WAL hot-journal checkpoint admission entrypoints in
`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` from numbered worker
method names to stable descriptive names:

- `checkpointPageCacheLeasePlan`
- `checkpointReaderLeasePlan`
- `statementConsumerAdmissionPlan`
- `writeCursorAdmissionPlan`
- `readerSlotCheckpointAdmissionPlan`
- `writerGenerationAdvancePlan`
- `appendBatchCommitAdmissionPlan`
- `readerAcknowledgementFencePlan`

Direct tests and WordPress smoke examples for the same WAL/VFS checkpoint family now call
the descriptive entrypoints. Behavioral status strings and existing fixture/example file
names were left intact so accepted assertions continue to prove the same scenarios.

Verification:

- `php -l` passed for the changed production class, eight focused tests, and eight
  changed WordPress examples.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext203Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext205Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext206Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext207Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext208Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext209Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext210Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext211Test.php`
  passed: 8 test files, 547 assertions, 0 failures.
- Changed examples `wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next203.php`,
  `next205.php`, `next206.php`, `next207.php`, `next208.php`, `next209.php`,
  `next210.php`, and `next211.php` passed with `--self-test`.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure: no new support component is needed; this is a naming consolidation
over existing WAL/VFS checkpoint admission behavior.
