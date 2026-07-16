# B-tree Delete Rebalance Freelist Current Source Next84

This slice adds `SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan`, a bounded
current-source application layer for repeated table/index leaf deletes. Each
delete is generated from the database image produced by the prior
delete/rebalance/freeblock step before obsolete overflow pages are connected
into the freelist and pointer-map entries are rewritten as free pages.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeDeleteRebalanceFreelistCurrentSourceNext84Test.php`
  - `1 test files, 63 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-btree-delete-rebalance-freelist-current-source-next84.php`
  - emits copied `wp_options` transient cleanup diagnostics with current-source
    deleted rowids, released overflow pages, final freelist count, and
    materialized page numbers.
- `php -l lanes/libsqlite/src/SQLiteBTreeDeleteRebalanceFreelistCurrentSourcePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeDeleteRebalanceFreelistCurrentSourceNext84Test.php`
- `php -l lanes/libsqlite/examples/application-btree-delete-rebalance-freelist-current-source-next84.php`

## Non-overlap

This does not repeat accepted page relocation, root collapse, index-interior
merge, bulk overflow freeblocks, overflow freelist release, current/next80
overflow materialization, or batch74 delete/rebalance freeblock application.
The new behavior is the current-source sequencing boundary: the second and
later delete must read from the materialized page image produced by the earlier
step, so stale repeated rowids are rejected and cumulative freelist/pointer-map
state is preserved.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP
database page images, table/index leaf cell deletion, freeblock defragmentation,
freelist release, and auto-vacuum pointer-map helpers.
