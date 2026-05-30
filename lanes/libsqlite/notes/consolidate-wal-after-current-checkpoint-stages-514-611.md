## WAL After-Current Checkpoint Stage Consolidation 514-611

This consolidation removes the remaining production numbered after-current checkpoint wrappers for
stages 514 through 611 from
`SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan` and routes their direct tests and
examples through the canonical `afterCurrentCheckpointStage()` entry point.

Observable stage numbers, reason strings, status keys, dependency strings, operation names, and
receipt validation behavior are preserved by carrying the old verification-step mapping into the
canonical stage table. No new support component is needed; this is production suffix cleanup only.

Focused evidence to run:

- `php -l lanes/libsqlite/src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages500515Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages516531Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages532547Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages548563Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages564579Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages580595Test.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointAfterCurrentStages596611Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext*Test.php`
- self-test the updated Application examples for stages 515, 531, 547, 563, 579, 595, and 611.
