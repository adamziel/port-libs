# Pager Master Journal Reader Cache Current Source Next399-414

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond accepted next383-398 with next399 through next414 VDBE statement-reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for explain mode, VDBE debug/listing/trace/addoptrace/eqptrace/coverage/comment/profile/scanstatus/reprep/run/yield/pause/reset state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext414Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next414.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext414Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next414.php`
