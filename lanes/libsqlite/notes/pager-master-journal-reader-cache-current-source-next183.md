# Pager Master Journal Reader Cache Current Source Next183

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next183`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`. It extends the existing master-journal reader-cache current-source series with a publication-generation fence: after a newer master-journal recovery source is opened, a reader-cache page that passed the earlier format-ticket checks is still rejected if its published generation or master-source digest belongs to the prior source. Matching clean pages can still be retained or refreshed; stale publication tickets force reader reopen before the next read.

Application smoke: `application-pager-master-journal-reader-cache-current-source-next183.php` covers copied `wp_options` recovery where the schema page remains cache-admissible, the options page refreshes from the recovered source, and an `active_plugins` reader cache published under the prior master source is forced to miss.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext183Test.php` - `1 test files, 52 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next183.php` - self-test passed
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext183Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next183.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` increases by 52 focused PASS lines, from `86003` to `86055`. Mapped upstream coverage remains unchanged; this is focused pager behavior over existing master-journal reader-cache inventory rather than a new manifest-backed upstream row.

Non-overlap: avoids accepted next180 page-1 format-ticket checks, next168 source-page digest fences, next161 master-journal digest membership fences, and the accepted batch167 pager master-journal reader-cache publication surface by adding the later publication-generation/master-source-digest replay guard only.

Dependency closure: no new support component is needed. The slice reuses lane-local master-journal reader-cache current-source primitives.

Next task: wire publication-generation cache admission into a future native pager transaction/open executor once bounded reader-cache plans are replaced by transaction-owned cache entries.
