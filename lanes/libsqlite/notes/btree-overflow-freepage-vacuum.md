# B-tree Overflow Freepage Vacuum Overflow Freepage Vacuum

This slice extends `SQLiteBTreeOverflowVacuumFreepagePlan` with a current-source
to next-vacuum reuse summary for obsolete overflow pages after they have been
released to the freelist.

## Behavior

- Starts from table/index overflow chains released to free pages under
  auto-vacuum.
- Allocates the released free pages back through SQLite freelist order for a
  vacuum/rebuild destination b-tree.
- Reports current pointer-map `free-page` ownership, next pointer-map
  `btree-page` ownership, allocation source (`freelist-leaf` before
  `freelist-trunk`), source chain labels, and secure-delete state before reuse.
- Rejects negative allocation counts, invalid b-tree parents, and over-allocation
  when append is disabled.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreepageVacuumTest.php`
  - `1 test files, 278 assertions, 0 failures`
  - `60` PASS lines

## Non-overlap

Avoids accepted overflow freelist release, pointer-map free-page next91,
tail-truncation next92, freeblock pointer-map vacuum next93, rebalance freepage
next99, bulk overflow freeblocks, page moves, root collapse, and VFS/WAL
application clusters. The new surface is the allocation/reuse side of vacuum
after released overflow pages become freelist pages.

## Dependency Closure

No new support component is needed. The patch composes existing native PHP
database image, overflow-chain, freelist allocation, pointer-map, and
secure-delete helpers.
