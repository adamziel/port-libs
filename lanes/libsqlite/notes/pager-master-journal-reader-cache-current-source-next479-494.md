# Pager Master Journal Reader Cache Current Source Next479-494

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond merged next463-478 with next479 through next494 statement VDBE expression, subprogram, result-row, collation, function, aggregate, numeric-affinity, cast, permutation, and comparison reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for Program, Param, Variable, Copy, SCopy, IntCopy, ResultRow, CollSeq, Function, AggStep, AggFinal, Real, RealAffinity, Cast, Permutation, and Compare state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext494Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next479-494.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext494Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next479-494.php`
