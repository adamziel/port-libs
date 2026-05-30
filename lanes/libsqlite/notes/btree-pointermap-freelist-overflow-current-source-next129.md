# btree pointer-map freelist overflow current-source next129

- Implemented `SQLiteBTreePointerMapFreelistOverflowCurrentSourceNext129Plan`.
- Behavior: after a Application-sized option delete releases obsolete overflow pages into the freelist, a new overflow chain may allocate from both those released pages and older freelist leaf pages. The plan records the current pointer-map state, free-page state, and next overflow-chain state so stale parents cannot survive the mixed allocation.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapFreelistOverflowCurrentSourceNext129Test.php` -> `1 test files, 270 assertions, 0 failures` with 66 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-pointermap-freelist-overflow-current-source-next129.php`.
- Non-overlap: avoids accepted overflow freelist release, bulk overflow freeblocks, rootpage reuse, trunk pointer-map reuse, free-then-reuse/vacuum handoffs, page relocation, root collapse, index-interior merge, pointer-map vacuum freeblock, and freelist trunk pointer-map reuse. This slice covers the mixed-source overflow allocation chain after release.
- Dependency closure: no new support component is needed; it reuses existing native PHP overflow-chain encoding, freelist release/allocation, page image, and auto-vacuum pointer-map primitives.
