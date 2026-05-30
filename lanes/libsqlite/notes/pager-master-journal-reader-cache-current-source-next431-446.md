# Pager Master Journal Reader Cache Current Source Next431-446

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond accepted next415-430 with next431 through next446 statement VDBE cursor-motion and seek reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for VDBE Next, Prev, NextIfOpen, PrevIfOpen, SorterNext, SeekLT, SeekLE, SeekGE, SeekGT, SeekScan, NotFound, Found, NotExists, Last, IfNoSuchRow, and DeferredSeek state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext446Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next431-446.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext446Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next431-446.php`
