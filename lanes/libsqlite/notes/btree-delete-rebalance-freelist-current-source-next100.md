# B-tree Delete Rebalance Freelist Current Source Next100

Slice: `btree-delete-rebalance-freelist-current-source-next100`

Implemented a current-source delete/rebalance freelist edge where sequential
deletes release obsolete overflow pages into a nearly full freelist trunk. The
first released page fills the existing trunk leaf array; the next released page
is promoted to a new first trunk that links to the old full trunk. The plan now
exposes final freelist trunk pages and allocation order from the materialized
post-delete database image.

Focused evidence:

- `php -l lanes/libsqlite/src/SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeDeleteRebalanceFreelistCurrentSourceNext100Test.php`
- `php -l lanes/libsqlite/examples/application-btree-delete-rebalance-freelist-current-source-next100.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeDeleteRebalanceFreelistCurrentSourceNext100Test.php`
  - `1 test files, 53 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-btree-delete-rebalance-freelist-current-source-next100.php`
  - reports deleted transient rows, released overflow pages `[6, 7]`,
    final freelist count `122`, the new first trunk `7 -> 5`, and the
    allocation-order head.

Non-overlap:

- Avoids accepted overflow freelist release, bulk overflow freeblocks,
  B-tree page relocation, root collapse, index-interior merge, freeblock
  defragmentation, and pointer-map vacuum/truncate clusters. This slice is
  specifically the current-source sequential delete transition from appending
  to a nearly full freelist trunk into promoting the next obsolete page as a
  new trunk.

Dependency closure:

- No new support component is needed. The slice reuses lane-local native PHP
  B-tree page assembly/parsing, freelist traversal/free planning, pointer-map
  updates, and the root `TestRunner`; it does not require `ext/sqlite`, the
  SQLite shell/Tcl runner, network services, or a new shared dependency row.
