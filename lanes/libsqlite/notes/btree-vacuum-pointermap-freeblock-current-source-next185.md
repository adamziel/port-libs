# B-tree Vacuum Pointer-map Freeblock Current-source Next185

- Added `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext185Plan`.
- Behavior: records post-apply receipts for the accepted next182 B-tree vacuum/freeblock apply schedule. The receipt layer keeps replayed header, leaf, pointer-map, and replacement overflow pages durable for the next reader, fences truncated tail pages out of the next source, and records the final database page count only after tail truncation receipts.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext185Test.php` -> `1 test files, 636 assertions, 0 failures` with 96 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next185.php --self-test` -> self-test passed.
- Dependency closure: no new support component needed; next185 reuses next182 ordered apply rows, replay hashes, pointer-map dependency receipts, and fenced-tail truncation pages.
- Non-overlap: this adds the post-apply durability receipt and final page-count fence after next182 apply scheduling. It does not repeat next182 scheduling, next177 replay batches, next176 source-boundary checks, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks.
