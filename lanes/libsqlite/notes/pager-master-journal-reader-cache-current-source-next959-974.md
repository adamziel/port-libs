## Pager Master-Journal Reader Cache Current Source Next959-974

This slice extends the consolidated `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond accepted next943-958 with next959 through next974 VDBE arithmetic/comparison branch-condition handoff reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for BitAnd, BitOr, ShiftLeft, ShiftRight, AddImm, BitNot, Affinity, CastAffinity, PermutationAffinity, CompareAffinity, CompareCollSeq, JumpDestination, OnceFlag, If, IfNot, and IsNull branch-condition handoff state.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext959974Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next959-974.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext943958Test.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext959974Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next959-974.php`
- `git diff --check`
