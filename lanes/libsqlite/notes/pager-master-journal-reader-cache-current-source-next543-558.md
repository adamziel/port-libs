# Pager Master Journal Reader Cache Current Source Next543-558

This slice extends the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` directly after merged next527-542 with next543 through next558 statement VDBE opcode literal and arithmetic reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for String, Blob, Null, SoftNull, Integer, Int64, Real, Boolean, NullRow, RowValue, ZeroBlob, String8, Concat, Add, Subtract, and Multiply state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext558Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next543-558.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext558Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next543-558.php`

Non-overlap: builds directly on accepted next527-542 statement VDBE virtual-table and opcode fences. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, or prior VDBE control-flow behavior.

Source class note: extended the established canonical source class for this domain; no new numbered source class was needed.

Next slice: continue pager master-journal reader-cache current-source statement VDBE arithmetic and bitwise opcode fences after next558.
