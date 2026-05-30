# B-tree Overflow Rebalance Freelist Current Source Next134

Behavior slice: adds `SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNextPlan`, a current-source composition for the SQLite path where deleting a Application-sized table leaf cell derives obsolete overflow pages from the current database image, applies the delete/rebalance/free-list transition, then immediately reuses those pages for a replacement overflow chain with pointer-map rewrites.

Non-overlap: this is not the accepted next132 standalone overflow-chain release/reuse wrapper and not the accepted next120 delete/rebalance wrapper alone. The new behavior verifies the combined current-source delete/rebalance plus replacement allocation transition and records before/free/next pointer-map states for reused deleted overflow pages.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowRebalanceFreelistCurrentSourceNext134Test.php`
- Expected: `1 test files, 256 assertions, 0 failures` with 76 PASS lines.

Application smoke:

- `php lanes/libsqlite/examples/application-btree-overflow-rebalance-freelist-current-source-next134.php --self-test`
- Expected: `application-btree-overflow-rebalance-freelist-current-source-next134 self-test passed`

Dependency closure: no new support component is needed; the slice composes existing native PHP B-tree page, overflow page, freelist, pointer-map, and record helpers.
