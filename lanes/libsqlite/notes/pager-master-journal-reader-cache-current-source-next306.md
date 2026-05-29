# Pager master-journal reader cache current-source next306

This slice extends the consolidated pager master-journal reader-cache current-source planner from next302 through next306.

It adds four current-source pager-state fences:

- next303: reader-cache cache-size token
- next304: reader-cache WAL autocheckpoint token
- next305: reader-cache query-only token
- next306: reader-cache foreign-key token

Reader-cache pages that pass the accepted next302 mmap-size fence still reopen when any of these pager configuration tokens predates the recovered master-journal current source. Next-read tickets are checked independently so a cache page can remain current while an older reader ticket still forces a reopen before WordPress option/user reads continue.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext306Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next306.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext306Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next306.php --self-test`
- `git diff --check`
