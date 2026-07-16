# Pager master-journal reader cache current-source next302

This slice extends the consolidated pager master-journal reader-cache current-source planner from next298 through next302.

It adds four current-source pager-state fences:

- next299: reader-cache locking-mode token
- next300: reader-cache journal-mode token
- next301: reader-cache synchronous token
- next302: reader-cache mmap-size token

Reader-cache pages that pass the accepted next298 spill-epoch fence still reopen when any of these pager configuration tokens predates the recovered master-journal current source. Next-read tickets are checked independently so a cache page can remain current while an older reader ticket still forces a reopen before Application option/user reads continue.

Validation:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext302Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next302.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext302Test.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next302.php --self-test`
- `git diff --check`
