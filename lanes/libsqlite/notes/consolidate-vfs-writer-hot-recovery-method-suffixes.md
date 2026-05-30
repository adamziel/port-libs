# VFS writer hot recovery method suffix consolidation

## Change

Consolidated numbered `SQLiteVfsFileWriter` hot-recovery entry points into stable method names:

- `applyMasterJournalHotRollbackFromCurrentSource()`
- `applyMasterSuperJournalHotRecovery()`
- `applySuperJournalHotRollbackFromCurrentSource()`
- `applyMasterJournalStatementPageRecoveryFromCurrentSource()`

Direct pager tests, Application examples, and notes were migrated to the stable names. Existing dependency markers and recovery status/action strings remain unchanged for observable compatibility.

## Verification

Run from the isolated worktree:

```text
php -l lanes/libsqlite/src/SQLiteVfsFileWriter.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalHotRollbackCurrentSourceNextTest.php
php -l lanes/libsqlite/tests/SQLitePagerHotJournalSuperMasterRecoveryTest.php
php -l lanes/libsqlite/tests/SQLitePagerStatementJournalRecoveryCurrentSourceTest.php
php -l lanes/libsqlite/tests/SQLitePagerSuperJournalHotRollbackCurrentSourceTest.php
php -l lanes/libsqlite/tests/SQLitePagerMasterJournalStatementRecoveryCurrentSourceTest.php
php -l lanes/libsqlite/examples/application-pager-master-journal-hot-rollback-current-source-next.php
php -l lanes/libsqlite/examples/application-pager-hot-journal-super-master-recovery.php
php -l lanes/libsqlite/examples/application-pager-statement-current-source.php
php -l lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-source.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalHotRollbackCurrentSourceNextTest.php lanes/libsqlite/tests/SQLitePagerHotJournalSuperMasterRecoveryTest.php lanes/libsqlite/tests/SQLitePagerStatementJournalRecoveryCurrentSourceTest.php lanes/libsqlite/tests/SQLitePagerSuperJournalHotRollbackCurrentSourceTest.php lanes/libsqlite/tests/SQLitePagerMasterJournalStatementRecoveryCurrentSourceTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCache*.php
php lanes/libsqlite/examples/application-pager-hot-journal-super-master-recovery.php --self-test
php lanes/libsqlite/examples/application-pager-statement-current-source.php --self-test
php lanes/libsqlite/examples/application-pager-master-journal-statement-recovery-current-source.php --self-test
git diff --check -- lanes/libsqlite
```

Results:

- PHP lint passed for the changed production file, direct tests, and Application examples.
- Direct focused tests: `5 test files, 308 assertions, 0 failures`.
- Pager-master affected family: `164 test files, 10996 assertions, 0 failures`.
- Application example smokes exited `0` and reported applied recovery summaries.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component is needed. This is a production naming consolidation over existing native VFS rollback-journal and file-writer behavior.
