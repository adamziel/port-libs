# Pager Master Journal Reader Cache Current Source Next180

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next180`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It extends the accepted master-journal reader-cache current-source series with page-1 format-ticket fencing: after master-journal recovery, reader-cache pages are reusable only when the recovered header page-size, reserved-byte count, text encoding, user version, and application id still match the cache ticket. Matching clean pages can be retained or refreshed from the recovered source; dirty pages, stale source/epoch tickets, stale format tickets, and pinned stale images are invalidated before the next read.

WordPress smoke: `wordpress-pager-master-journal-reader-cache-current-source-next180.php` covers copied `wp_options` recovery where the schema cache survives the recovered format ticket, the `wp_options` root page refreshes from the recovered current source, and `active_plugins` cache keyed to the previous format ticket is forced to reopen.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext180Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next180.php`
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext180Test.php`
- `php -l lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next180.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` increases by the focused PASS-line count from the new next180 test. Mapped upstream coverage remains unchanged; this is focused pager behavior over existing master-journal reader-cache inventory rather than a new upstream denominator row.

Non-overlap: avoids accepted next170 rollback-journal source identity fencing, next174 canonical master-member ordering, next176 next-source rollover, and next177 change-counter/schema-cookie/freelist header-ticket fencing. It also avoids accepted WAL/VFS rollback apply, savepoint byte truncation, checkpoint transaction, B-tree, JSON table, SELECT, PRAGMA, trigger, planner, and encoding surfaces.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal member parsing and pager reader-cache current-source ticket primitives.

Next task: wire format-ticket cache admission into a future native pager open/recovery executor once reader-cache entries are owned by the transaction layer instead of bounded current-source plans.
