# Pager Master-Journal Reader Cache Current Source Next192

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan`.
It composes the existing next186 recovered-page-set/source fences and adds an
attached rollback-journal member token fence before clean reader-cache pages are
reused after master-journal recovery.

Behavior covered:

- Current master-journal members must each have a current generation token.
- Reader-cache entries carry the attached member tokens that were observed when
  the cache page was admitted.
- Identical page images are still invalidated when an attached member journal
  token changes, because the master-journal current source has changed under a
  multisite/attached-database transaction.
- Next read tickets carry the member-token digest and reopen stale tickets.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext192Test.php`
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next192.php`

Dependency closure: no new support component is required. The slice reuses the
existing pager master-journal reader-cache and current-source model and adds
bounded token comparison in native PHP.

Non-overlap: next192 does not repeat next186 recovered-page-set sequencing,
next183 publication/master-source digest fencing, next185 finite truncation, or
accepted super-journal commit/apply paths. It is focused on attached member
rollback-journal generation tokens.
