# Pager Master-Journal Reader Cache Current Source Next195

- Slice: `pager-master-journal-reader-cache-current-source-next195`.
- Behavior: adds a per-master-member journal digest ticket fence on top of the accepted next191 master-journal delete/directory-sync reader-cache admission. A reader-cache page with identical page bytes is reopened when its ticket was derived from an obsolete attached rollback-journal member.
- Application path: copied `wp_options` plus attached user/meta databases can reuse schema pages, refresh changed active plugin pages, and reject byte-identical alloptions cache rows when the attached rollback journal changed underneath the master journal cleanup.
- Dependency closure: no new support component needed; this reuses lane-local master-journal parsing and reader-cache current-source primitives.
- Non-overlap: does not repeat next191 delete-token/directory-sync fencing, next190 per-page source digest fencing, rollback-journal commit/apply, or VFS writer application.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterMemberTicketFenceTest.php`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-master-member-ticket-fence.php --self-test`
- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext195Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheMasterMemberTicketFenceTest.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-master-member-ticket-fence.php`
- `git diff --check -- lanes/libsqlite`
