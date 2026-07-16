# Pager Master Journal Reader Cache Current Source Next495-510

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond merged next479-494 with next495 through next510 statement VDBE branch, null-test, comparison-branch, seek-hit, and if-not-open reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for Jump, Once, If, IfNot, IsNull, NotNull, Ne, Eq, Gt, Le, Lt, Ge, ElseEq, ZeroOrNull, SeekHit, and IfNotOpen state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext510Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next495-510.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext510Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next495-510.php`
