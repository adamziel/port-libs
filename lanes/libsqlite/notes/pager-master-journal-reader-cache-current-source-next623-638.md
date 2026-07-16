# Pager Master Journal Reader Cache Current Source Next623-638

This slice extends the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` directly after integrated next607-622 with next623 through next638 statement VDBE literal and arithmetic reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for SoftNull, Integer, Int64, RealValue, Boolean, NullRow, RowValue, Zeroblob, String8, Concat, Add, Subtract, Multiply, Divide, Remainder, and BitAnd state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext638Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next623-638.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext638Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next623-638.php`

Non-overlap: builds directly on accepted next607-622 statement VDBE branch fences and uses distinct next623-638 literal/arithmetic opcode-token fields. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, prior VDBE opcode, or prior VDBE branch behavior.

Source class note: extended the established canonical source class for this domain; no new numbered source class was needed.

Next slice: continue pager master-journal reader-cache current-source statement VDBE arithmetic and affinity fences after next638.
