# B-tree Overflow Rebalance Freepage Current Source Next99

This slice adds `SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext99Plan` for the sequential current-source delete path where an overflow-backed table or index leaf first remains live and is defragmented, then a later delete in the same current leaf frees the emptied leaf page plus its obsolete overflow chain into the existing freelist.

The behavior is intentionally narrower than accepted batch94 next94 coverage: next94 covered a one-cell delete that immediately freed an empty leaf. This next99 slice proves the mixed transition order (`freeblock-rebalance` then `empty-leaf-free`) for table and index leaves, including updated freelist traversal, auto-vacuum pointer-map free-page entries, and secure-delete clearing of the later overflow chain.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext99Test.php`
- Result: `1 test files, 62 assertions, 0 failures`
- New focused PASS lines: `62`
- Application smoke: `php lanes/libsqlite/examples/application-btree-overflow-rebalance-freepage-current-source-next99.php`

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext99Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowRebalanceFreepageCurrentSourceNext99Test.php`
- `php -l lanes/libsqlite/examples/application-btree-overflow-rebalance-freepage-current-source-next99.php`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. This reuses the existing native PHP B-tree leaf delete, defragmentation, freelist, pointer-map, and database page-image helpers.

Non-overlap: avoided accepted overflow freelist release, bulk overflow freeblocks, one-shot next94 empty-leaf freepage, root collapse, page move, index-interior merge, VFS/WAL, JSON, SQL, and encoding clusters.
