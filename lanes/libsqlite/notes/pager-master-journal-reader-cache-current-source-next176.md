# Pager Master-Journal Reader Cache Current Source Next176

- Scope: pager master-journal reader-cache reuse across a current-source to next-source rollover.
- Behavior: reader-cache entries admitted for the current master-journal source are not reused for the next source unless the cache ticket already names the next source id, epoch, digest, and member set. Matching page bytes alone are not enough to hit cache after the next master-journal source is read.
- Application path: copied `wp_options` readers retain the current schema cache for the current source, reopen schema/rewrite readers for the next master-journal source, and reuse only an `active_plugins` cache entry already ticketed to the next source.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext176Test.php` passed with `1 test files, 88 assertions, 0 failures`; `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next176.php` passed.
- Non-overlap: avoids accepted next173 current master-journal membership/digest fencing and next170 rollback-journal source fencing by proving the subsequent source rollover boundary that prevents a current-source cache hit even when the page image still matches.
- Dependency closure: no new support component needed; this reuses lane-local master-journal member parsing and pager reader-cache ticket primitives.
