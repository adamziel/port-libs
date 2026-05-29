# Pager Master Journal Reader Cache Current Source Next607-622

This slice extends the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` directly after merged next591-606 with next607 through next622 statement VDBE page-count, control-flow, and literal branch reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source branch tokens for PageCount, MaxPgcnt, OpcodeTrace, CursorHint, Noop, Init, Goto, Gosub, Return, Yield, Halt, HaltIfNull, MustBeInt, String, Blob, and Null state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext622Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next607-622.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext622Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next607-622.php`

Non-overlap: builds directly on accepted next591-606 statement VDBE branch fences and uses distinct next607-622 branch-token fields, avoiding the earlier non-branch VDBE opcode tokens. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, or prior VDBE behavior.

Source class note: extended the established canonical source class for this domain; no new numbered source class was needed.

Next slice: continue pager master-journal reader-cache current-source statement VDBE page-count, control-flow, and literal opcode fences after next622.
