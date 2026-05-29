# Pager Master Journal Reader Cache Current Source Next463-478

This slice extends `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan` beyond merged next447-462 with next463 through next478 statement VDBE cursor, write-path, affinity, constraint, foreign-key, and RETURNING reader-cache fences. Reader-cache reuse after master-journal recovery now also requires current-source tokens for OpenPseudo, OpenEphemeral, SorterOpen, Sequence, NewRowid, Insert, Delete, RowData, ColumnMetadata, MakeRecord, Affinity, TypeCheck, Constraint, Conflict, FkCheck, and Returning state.

Focused coverage:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext478Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next463-478.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext478Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next463-478.php`
