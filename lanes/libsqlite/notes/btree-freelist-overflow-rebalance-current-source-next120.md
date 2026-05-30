# B-tree Freelist Overflow Rebalance Current Source Next120

## Behavior

Adds `SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNextPlan`, a current-source B-tree delete/rebalance materializer that:

- deletes table leaf rowids and index leaf records from the current page image;
- derives obsolete overflow chain page numbers by reading current overflow next pointers;
- routes non-empty leaves through freeblock rebalance materialization and empty leaves through full leaf-page freelist release;
- rewrites freelist trunk/header/page images and auto-vacuum pointer-map entries for table and index leaves; and
- exposes transition rows, materialized page numbers, derived overflow chains, final freelist allocation order, and write ordering.

This is intentionally narrower than accepted next113/115/116 B-tree work: it does not repeat freelist trunk pointer-map reuse, overflow freeblock diagnostics, or table-only overflow freepage application. The new coverage adds index-leaf current-source overflow derivation and combined leaf-plus-overflow freelist application after rebalance.

## Application smoke

`examples/application-btree-freelist-overflow-rebalance-current-source-next120.php` models a copied `wp_options` cleanup where oversized transient option rows and matching index records are deleted without ext/sqlite. The smoke proves both table and index leaf pages plus their current-source overflow chains become freelist-owned and pointer-map entries become `free-page`.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistOverflowRebalanceCurrentSourceNext120Test.php`
  - `1 test files, 76 assertions, 0 failures`

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP page, overflow, freelist, pointer-map, table-leaf, and index-leaf primitives.

## Next

Continue B-tree work in non-overlapping page-image application paths: delete/rebalance behavior that updates interior parents or incremental-vacuum pointer-map state beyond this full leaf/overflow freelist materialization.
