# B-tree Delete Rebalance Overflow Freeblock Current Source Next115

This slice extends `SQLiteBTreeDeleteRebalanceFreeblockApplyPlan` with
current-source to next-page evidence for delete/rebalance application. The plan
now records source/deleted/next leaf page hashes, cell/freeblock/fragment/content
area deltas, and a deterministic write order that includes the leaf rewrite,
freed overflow pages, freelist trunk pages, pointer-map pages, and the database
header page.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeDeleteRebalanceOverflowFreeblockCurrentSourceNext115Test.php`
  - `1 test files, 61 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-btree-delete-rebalance-overflow-freeblock-current-source-next115.php`
  - `Application transient delete source hash: 68e108348c7b`
  - `Application transient delete next hash: 71af2d5ab799`
  - `Application transient delete freed overflow pages: 6,7,8`
  - `Application transient delete write order: 3,6,7,8,2,1`
  - `Application transient delete freeblock delta: -172`

Non-overlap:

Avoids accepted bulk overflow-backed delete/freeblock materialization, overflow
freelist release, incremental vacuum reuse, pointer-map page relocation, root
collapse, index-interior merge, rebalance cell overflow apply, and batch107/108
B-tree surfaces. This patch is additive metadata/application evidence on the
existing delete/rebalance freeblock apply path for the current-source next115
boundary.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP SQLite
database image, page header, table/index leaf, freelist, pointer-map, and secure
delete helpers.
