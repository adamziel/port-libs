# Pager Master Journal Reader Cache Current Source Next447-462

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond merged next431-446 with next447 through next462 statement VDBE row movement, rowset, index, and cursor-open reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for VDBE MoveTo, IndexRowid, RowSetRead, RowSetTest, RowSetAdd, IdxInsert, IdxDelete, IdxRowid, IdxGE, IdxGT, IdxLE, IdxLT, IdxKeyInfo, OpenRead, OpenWrite, and OpenDup state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext462Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next447-462.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext462Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next447-462.php`
