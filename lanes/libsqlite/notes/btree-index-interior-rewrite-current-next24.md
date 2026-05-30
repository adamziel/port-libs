# B-tree Index Interior Rewrite Current Next24

This slice adds bounded native PHP redistribution for index-interior sibling
pages. When a copied `wp_options` option-name index parent must borrow from the
right interior sibling, `SQLiteBTreeInteriorRedistributionPlan::indexInterior()`
now rewrites left/right interior pages, yields the current/next child order,
promotes the middle separator payload to the parent divider, and reports
auto-vacuum pointer-map parent ownership for all child pages.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexInteriorRewriteCurrentNext24Test.php`
- Result: `1 test files, 58 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-index-interior-rewrite-current-next24.php`

Non-overlap: this avoids accepted index-interior merge, table/index page
relocation, root collapse, overflow freelist release, freepage allocation,
table-interior redistribution/merge, VFS writer/sync/rollback/lock clusters,
WAL checkpoint/savepoint byte work, JSON table source/cursor/constraint work,
Unicode GLOB, SELECT SQL text/subquery/group/order clusters, and batch21
B-tree freepage allocation. The new behavior is index-interior sibling
redistribution and parent divider payload rewrite, not merge or allocation.

Dependency closure: no new support component is needed. The patch reuses
lane-local index-interior page assembly, index cell parsing/encoding, record
encoding, page-header parsing, and pointer-map metadata.
