# B-tree Overflow Vacuum Freeblock Current Source Next137

This slice adds `SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan`.
It composes an existing leaf freeblock coalesce plus overflow-vacuum truncation
with the next overflow allocation. The covered current-source boundary is:

- a copied Application `wp_options` leaf delete coalesces fragmented freeblocks;
- obsolete table/index overflow chains are released;
- incremental vacuum truncates only the tail overflow chain and keeps lower
  released overflow pages as freelist pages;
- a replacement overflow payload immediately reuses the surviving freed pages;
- auto-vacuum pointer-map entries move from `free-page` to the next overflow
  chain owners, while truncated pages remain absent from the next image.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNext137Test.php`
- `php -l lanes/libsqlite/examples/application-btree-overflow-vacuum-freeblock-current-source-next137.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowVacuumFreeblockCurrentSourceNext137Test.php`
- `php lanes/libsqlite/examples/application-btree-overflow-vacuum-freeblock-current-source-next137.php --self-test`
- `git diff --check -- lanes/libsqlite`

Focused result: `1 test files, 197 assertions, 0 failures` with 62 PASS lines.

Non-overlap: avoids accepted batch134 overflow rebalance freelist, next133
pointer-map vacuum overflow page recreation, next122 freeblock vacuum without
subsequent reuse, next121 pointer-map free-then-reuse, overflow freelist
release, bulk overflow freeblocks, page relocation, root collapse, and VFS/WAL
transaction application. The new surface is the current-source handoff from
freeblock+vacuum survivors into immediate overflow allocation and pointer-map
ownership rewrite.

Dependency closure: no new support component is needed. The slice reuses
existing native PHP SQLite page images, freeblock coalescing, overflow vacuum,
freelist allocation, overflow-page assembly, and auto-vacuum pointer-map
helpers.
