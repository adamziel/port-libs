# Pager Master-Journal Reader Cache Current Source Next184

## Behavior

Adds `SQLitePagerMasterJournalReaderCacheCurrentSourceNext184Plan`, a bounded
pager reader-cache fence for rollback transactions using a master journal.
Reader-cache pages are admitted only when the current master-journal member set
and the master-journal read token still match the cache source. The read token
includes path, device, inode, generation, size, timestamps, full-read bounds,
member text, and byte digest, so an unlinked/recreated master-journal sidecar
invalidates stale reader-cache pages even if the textual member list is
unchanged.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePagerMasterJournalReaderCacheCurrentSourceNext184Test.php`
  - `1 test files, 98 assertions, 0 failures`
  - 98 PASS lines
- `php lanes/libsqlite/examples/application-pager-master-journal-reader-cache-current-source-next184.php`
  - self-test passed

Expected dashboard movement: `phpPass +98`, from `86745` to `86843`.
Mapped upstream coverage is unchanged because this is focused current-source
pager behavior over existing rollback/master-journal inventory.

## Non-Overlap

This avoids accepted next181 pending master-journal membership rejection,
rollback-journal source digest/page-set fences, member delete/recovered-state
fences, WAL checkpoint/savepoint byte materialization, VFS rollback/apply/sync
clusters, B-tree freeblock/freelist/overflow/page-move clusters, JSON table
source/constraint clusters, SELECT SQL text/order/group/subquery clusters, and
encoding/GLOB behavior. The new surface is master-journal file generation and
complete-read token identity before reader-cache reuse.

## Dependency Closure

No new support component is needed. The slice reuses lane-local pager
reader-cache source tracking and master-journal membership parsing.

## Next

Continue pager work only on a non-overlapping transaction application or
durability edge; avoid another reader-cache wrapper unless it checks a distinct
current-source primitive.
