# B-tree Overflow Rebalance Freepage Current Source Next94

## Behavior

This slice adds `SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext94Plan` for the current-source delete path where deleting the current table or index leaf cell empties the leaf. The planner reuses existing leaf-delete helpers, selects the accepted empty-leaf free plan for zero-cell results, and materializes one current database image containing:

- the emptied leaf page released to the freelist;
- obsolete overflow pages released in the same freelist operation;
- secure-delete clearing for released overflow pages;
- auto-vacuum pointer-map entries rewritten to `free-page`.

Non-overlap: this is not another bulk overflow freeblock or overflow-only freelist release. The new behavior is specifically the current-source transition from delete/rebalance to freeing the leaf page itself when the delete leaves zero cells.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext94Test.php`
  - expected: `1 test files, 50 assertions, 0 failures`
  - PASS-line delta: `+50`
- `php lanes/libsqlite/examples/application-btree-overflow-rebalance-freepage-current-source-next94.php`
  - expected: self-test JSON with `step_type` `empty-leaf-free`, freed pages `[3,5,6,7]`, and pointer-map `free-page` entries.

## Dependency Closure

No new support component is needed. The implementation reuses existing native PHP B-tree page deletion, freelist free planning, secure-delete clearing, database page-image materialization, and auto-vacuum pointer-map update primitives.
