# Pager master-journal statement recovery current-source

Status: focused PHP behavior growth for current-source pager statement recovery.

This slice tightens `SQLitePagerStatementRecoveryPlan` and `SQLiteVfsFileWriter::applyMasterJournalStatementPageRecoveryFromCurrentSource()` so a master-journal member is recovered only when the current statement-journal sidecar still exists. If the sidecar is missing, stale caller-provided preimages are not applied, the database image remains unchanged, and the skipped database records `missing_statement_journal`.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerStatementRecoveryPlan.php && php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php && php -l lanes/libsqlite/tests/SQLitePagerMasterJournalStatementRecoveryCurrentSourceTest.php && php -l lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-source.php`
  - all changed PHP files reported no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalStatementRecoveryCurrentSourceTest.php`
  - `1 test files, 54 assertions, 0 failures`
  - `39` PASS lines.
- `php lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-source.php --self-test`
  - `application-pager-master-journal-statement-recovery-current-source self-test passed`

Dashboard delta: `phpPass` moves from `45302` to `45341` by the verified `39` new PASS lines. Mapped upstream coverage remains `604 / 1589`; this is focused PHP behavior over already mapped pager/master-journal statement recovery primitives.

Non-overlap: this avoids accepted pager master-journal cache-spill savepoints, hot-journal statement cache, statement-journal WAL savepoint handling, super-journal commits, rollback-journal commit/apply, WAL checkpoint/restart/truncate/savepoint recovery, VFS writer/sync/lock clusters, JSON table source/cursor/constraint clusters, B-tree delete/overflow/freeblock/pointer-map clusters, SELECT SQL text/subquery/group/order clusters, and Unicode GLOB behavior. The new surface is current-source sidecar existence gating before statement preimages are applied under a master journal.

Dependency closure: no new support component is needed. The slice reuses the lane-local pager statement recovery plan and bounded VFS file writer, adding only current file-sidecar admission before recovery.

Next task: wire the same sidecar-existence gate into any broader native pager transaction admission path that hydrates statement-journal preimages directly from on-disk journal records.
