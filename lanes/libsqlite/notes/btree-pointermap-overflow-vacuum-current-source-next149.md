# B-tree pointer-map overflow vacuum current-source next149

- Behavior: adds `SQLiteBTreePointerMapOverflowVacuumCurrentSourceNextPlan`, a current-source wrapper over the accepted overflow-vacuum materializer that classifies which obsolete overflow pages can be reused after incremental vacuum and which tail pages must remain rejected because they are beyond the final database page count.
- Application smoke: `php lanes/libsqlite/examples/application-btree-pointermap-overflow-vacuum-current-source-next149.php --self-test`
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapOverflowVacuumCurrentSourceNext149Test.php`
- Result: `1 test files / 353 assertions / 0 failures` with 59 PASS lines.

Non-overlap: avoids accepted overflow vacuum pointer-map next145 by adding the current-source reuse/rejection classification after vacuum and replacement allocation. It also avoids accepted overflow freelist release, bulk overflow freeblocks, freelist trunk pointer-map reuse, page relocation, root collapse, index-interior merge, and VFS/WAL storage slices.

Dependency closure: no new support component is needed. This reuses existing native PHP database image, overflow-chain, incremental vacuum, freelist allocation, and auto-vacuum pointer-map primitives under `lanes/libsqlite/src`.
