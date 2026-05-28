# Pager Master-Journal Reader Cache Current Source Next178

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext178Plan`, a bounded
pager recovery plan for attached rollback-journal transactions. Reader-cache
pages are admitted only when the current master journal still names the member,
the member journal generation matches, the member was recovered, and the member
rollback journal has been deleted. Pages whose attached member is unrecovered,
not deleted, dirty, pinned to a stale image, or stale by source/epoch/master
digest are invalidated before the next read/write source is served.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext178Test.php`
  - `1 test files, 89 assertions, 0 failures`
  - 89 PASS lines
- `php lanes/libsqlite/examples/wordpress-pager-master-journal-reader-cache-current-source-next178.php`
  - self-test passed

## Non-Overlap

This does not repeat accepted checksum fencing in next175, membership-only
reader-cache reuse in next169, source/header/cache-token fences in
next161/164/166/172/175, rollback-journal apply, super-journal commit, or WAL
checkpoint/savepoint byte materialization. The new fence is the member journal
generation plus delete/recovered state before reader-cache reuse.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager
master-journal membership and reader-cache source tracking primitives.

## Next

Clean integration should rerun the focused next178 test and the WordPress smoke,
then include the +89 PASS-line delta if full libsqlite remains green.
