# Pager Master Journal Reader Cache Current Source Next415-430

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond accepted next399-414 with next415 through next430 statement VDBE lifecycle reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for VDBE finalize, transfer, cursor, sorter, auxdata, memory, column, rowid, seek, B-tree/index/ephemeral/open/close cursor, and rewind state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext430Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next415-430.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext430Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next415-430.php`
