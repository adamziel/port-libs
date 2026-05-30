# Pager Master Journal Reader Cache Current Source Next527-542

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond merged next511-526 with next527 through next542 statement VDBE virtual-table and opcode reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for VColumn, VNext, VRename, Pagecount, MaxPgcnt, opcode Trace, CursorHint, Noop, Init, Goto, Gosub, Return, Yield, Halt, HaltIfNull, and MustBeInt state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext542Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next527-542.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext542Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next527-542.php`

Non-overlap: builds directly on accepted next511-526 statement VDBE virtual-table open/filter fences. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, or encoding behavior.

Next slice: continue pager master-journal reader-cache current-source statement VDBE opcode operand and record-shaping fences after next542.
