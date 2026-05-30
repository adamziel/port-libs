# Pager Master Journal Reader Cache Current Source Next208

This slice adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext208Plan`.
It layers a master-journal read-snapshot digest above the existing member
token, member header, and ordered-member fences. A reader cache entry that was
built from a prior byte-range read of the master journal is rejected even when
the member list and attached rollback-journal metadata still match.

Verification:

- `php -l lanes/libsqlite/src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext208Plan.php`
- `php -l lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext208Test.php`
- `php -l lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next208.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext208Test.php`
  - `1 test files, 55 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next208.php --self-test`
  - `application-pager-master-journal-reader-cache-current-source-next208 self-test passed`
- `git diff --check -- lanes/libsqlite`

Expected dashboard delta: `phpPass` increases by 55 focused PASS lines, from
`100087` to `100142`. Mapped upstream coverage remains unchanged; this is a
focused pager current-source behavior over the existing master-journal
reader-cache inventory.

Non-overlap: avoids accepted next203 member-order fencing, next196 member
header digests, next192 member token fencing, batch187 pager reader-cache
coverage, WAL checkpoint/savepoint/hot-journal behavior, rollback-journal
commit/apply, VFS writer/sync/lock behavior, B-tree, JSON, SELECT, PRAGMA, and
encoding surfaces. The new behavior is only the master-journal byte-range read
snapshot ticket before reader-cache reuse.

Dependency closure: no new support component is needed. The slice reuses
lane-local pager/master-journal reader-cache metadata and adds only a bounded
native PHP digest ticket for the master-journal read snapshot.
