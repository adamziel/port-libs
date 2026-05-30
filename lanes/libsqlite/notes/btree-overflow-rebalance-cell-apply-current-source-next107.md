# B-tree Overflow Rebalance Cell Apply Current Source Next107

This slice adds a current-source wrapper for replacing an overflow-backed table
or index leaf cell with a smaller local cell after delete. It composes the
existing cell reuse primitive against the current database page, materializes
the updated leaf/freelist/pointer-map page images into a post-apply
`SQLiteDatabase`, and exposes released-page transition rows.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNext107Test.php`
- `php lanes/libsqlite/examples/application-overflow-rebalance-cell-apply-current-source-next107.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowRebalanceCellApplyCurrentSourceNext107Test.php`
- `php -l lanes/libsqlite/examples/application-overflow-rebalance-cell-apply-current-source-next107.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap: this does not repeat accepted overflow freelist release, bulk
overflow freeblocks, page relocation, root collapse, index-interior merge,
freelist vacuum reuse, or overflow freepage diagnostics. The new behavior is
the current-source apply path for the replacement cell plus materialized
post-apply database state.

Dependency closure: no new support component is needed; the slice reuses the
bounded native PHP page, overflow, freelist, and pointer-map helpers already in
`lanes/libsqlite/src`.
