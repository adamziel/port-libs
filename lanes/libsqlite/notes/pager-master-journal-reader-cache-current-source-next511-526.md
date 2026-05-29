# Pager Master Journal Reader Cache Current Source Next511-526

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond merged next495-510 with next511 through next526 statement VDBE open, transaction, savepoint, checkpoint, journal-mode, vacuum, expire, table-lock, and virtual-table reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for NotOpen, IfOpen, Transaction, AutoCommit, Savepoint, Checkpoint, JournalMode, Vacuum, IncrVacuum, Expire, TableLock, VBegin, VCreate, VDestroy, VOpen, and VFilter state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext526Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next511-526.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext526Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next511-526.php`

Non-overlap: builds directly on accepted next495-510 statement VDBE branch and comparison fences. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, or encoding behavior.

Next slice: continue pager master-journal reader-cache current-source statement VDBE virtual-table and opcode-state fences after next526.
