## Pager Master-Journal Reader Cache Current Source Next975-990

This slice extends the consolidated `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond accepted next959-974 with next975 through next990 VDBE branch-condition handoff reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for NotNull, Ne, Eq, Gt, Le, Lt, Ge, ElseEq, ZeroOrNull, SeekHit, IfNotOpen, NotOpen, IfOpen, Transaction, AutoCommit, and Savepoint branch-condition handoff state.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext959974Test.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext975990Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next975-990.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext959974Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext975990Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next975-990.php --self-test`
- `git diff --check`
