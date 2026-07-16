# Pager Master Journal Reader Cache Current Source Next575-590

This slice extends the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` directly after merged next559-574 with next575 through next590 statement VDBE branch reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for IfNotBranch, IsNullBranch, NotNullBranch, NeBranch, EqBranch, GtBranch, LeBranch, LtBranch, GeBranch, ElseEqBranch, ZeroOrNullBranch, SeekHitBranch, IfNotOpenBranch, NotOpenBranch, IfOpenBranch, and TransactionBranch state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext590Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next575-590.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext590Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next575-590.php`

Non-overlap: builds directly on accepted next559-574 statement VDBE arithmetic, affinity, compare, jump, and first branch fences. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, or prior VDBE behavior.

Source class note: extended the established canonical source class for this domain; no new numbered source class was needed.

Next slice: continue pager master-journal reader-cache current-source statement VDBE branch and comparison opcode fences after next590.
