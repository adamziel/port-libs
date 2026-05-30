# B-tree vacuum pointer-map freeblock current-source next160

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, which composes the current-source delete/vacuum/freeblock replacement path and verifies a multi-page replacement overflow chain allocated from surviving post-vacuum free pages.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext160Test.php`
  - `1 test files, 406 assertions, 0 failures`
  - `70` PASS lines
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next160.php --self-test`

## Non-overlap

This avoids accepted page relocation, root collapse, index-interior merge, overflow freelist release, bulk overflow freeblocks, freelist trunk pointer-map reuse, next156 single-page replacement reuse, and next157 overflow-next transition classification. The new surface is the multi-page replacement chain after vacuum: surviving current-source free pages are reused in allocation order, overflow next pointers are rewritten to the replacement chain, pointer-map parents follow that chain, and truncated current-source pages remain rejected.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP B-tree delete/freeblock materialization, auto-vacuum pointer-map, freelist allocation/truncation, overflow-page encoding, and database image primitives under `lanes/libsqlite/src`.
