# Pager Master Journal Reader Cache Current Source Next559-574

This slice extends the canonical `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` directly after merged next543-558 with next559 through next574 statement VDBE arithmetic, affinity, compare, jump, and branch reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for Divide, Remainder, BitAnd, BitOr, ShiftLeft, ShiftRight, AddImm, BitNot, RealAffinity, CastAffinity, PermutationAffinity, CompareAffinity, CompareCollseq, JumpDestination, OnceFlag, and IfBranch state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext574Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next559-574.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext574Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next559-574.php`

Non-overlap: builds directly on accepted next543-558 statement VDBE literal and arithmetic fences. It does not repeat earlier reader-cache source, recovery receipt, snapshot, generation, rollback-source, WAL, VFS, B-tree, JSON, SQL executor, PRAGMA, trigger, encoding, or prior VDBE control-flow behavior.

Source class note: extended the established canonical source class for this domain; no new numbered source class was needed.

Next slice: continue pager master-journal reader-cache current-source statement VDBE branch and comparison opcode fences after next574.
