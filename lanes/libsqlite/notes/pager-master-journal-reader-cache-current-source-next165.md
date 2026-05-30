# Pager master-journal reader-cache current-source next165

Status: focused PHP behavior growth for `pager-master-journal-reader-cache-current-source-next165`.

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext165Plan`.
It fences reader-cache reuse after current master-journal recovery on both
master-journal membership and page-1 header generation: change counter, schema
cookie, and current WAL end-frame. A cache page is retained only when its image,
source id, epoch, master-journal digest, change counter, schema cookie, and
end-frame all match the recovered current source. Stale tickets reopen against
current source pages even when their cached image bytes still match.

Application smoke:
`application-pager-master-journal-reader-cache-current-source-next165.php` models
a copied `wp_options` recovery where page-1 is retained, while `active_plugins`
and autoload index reader-cache entries with stale header tickets reopen from
the recovered current source.

Focused verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext165Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext165Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next165.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext165Test.php`
  - `1 test files, 91 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next165.php`
  - `application-pager-master-journal-reader-cache-current-source-next165 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: `phpPass` +91, from `74089` to `74180`, from the
91 independent PASS lines in the focused test. Mapped upstream coverage remains
`610 / 1589`; this is focused pager behavior over existing mapped pager
inventory rather than a fresh upstream denominator row.

Non-overlap: avoids accepted next158-next162 pager master-journal reader-cache
membership/source-id/current-source slices, pager cache-spill master/savepoint
work, VFS savepoint rollback/write/sync/lock clusters, rollback-journal
commit/apply, super-journal commit, WAL checkpoint/hot-journal/truncate
clusters, B-tree, JSON, SQL executor, and encoding surfaces. The new surface is
the header-generation fence for reader-cache tickets after current
master-journal recovery.

Dependency closure: no new support component is needed. The slice reuses
lane-local native PHP master-journal member parsing, reader-cache source
validation, and page-image digest checks.
