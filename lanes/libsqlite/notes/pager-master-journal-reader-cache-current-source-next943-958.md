## Pager Master-Journal Reader Cache Current Source Next943-958

This slice extends the consolidated `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond accepted next927-942 with next943 through next958 VDBE literal/arithmetic branch-condition handoff reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for Null, SoftNull, Integer, Int64, RealValue, Boolean, NullRow, RowValue, Zeroblob, String8, Concat, Add, Subtract, Multiply, Divide, and Remainder branch-condition handoff state.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext943958Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next943-958.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext927942Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext943958Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next943-958.php`
- `git diff --check`
