# B-tree overflow freeblock vacuum current-source next140

- Behavior: adds `SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNext140Plan`, which validates the current overflow chain for an overflow-backed `wp_options` row, applies the table-leaf delete/freeblock image, releases obsolete overflow pages to the freelist, and classifies partial incremental-vacuum output.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockVacuumCurrentSourceNext140Test.php` -> `1 test files / 210 assertions / 0 failures` with 66 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-overflow-freeblock-vacuum-current-source-next140.php --self-test`.
- Non-overlap: avoids accepted page relocation, root collapse, index-interior merge, bulk overflow freeblocks, overflow freelist release, pointer-map overflow/freeblock next138, pointer-map freeblock vacuum next135, overflow vacuum/freeblock reuse next137, and freelist trunk pointer-map reuse. The new surface is current overflow-chain validation plus post-delete leaf freeblock and partial-vacuum survivor/truncation classification in one current-source path.
- Dependency closure: no new support component is needed; this reuses existing native PHP table-leaf delete, overflow-chain reader, pointer-map, freelist release, and incremental-vacuum primitives.
