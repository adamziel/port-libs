# B-tree Pointer-Map Freelist Vacuum Current Source Next121

- Slice: `btree-pointermap-freelist-vacuum-current-source-next121`.
- Behavior: obsolete overflow pages from a deleted Application-sized option value are first written into the freelist with auto-vacuum pointer-map entries changed to `free-page`, then the same pages are reused by a follow-up B-tree allocation with pointer-map entries changed to `btree-page` or `root-page`.
- Non-overlap: this is not the accepted overflow freelist release, freelist trunk reuse, bulk overflow freeblock, or page relocation slice. It covers the combined current-source free-then-reuse/vacuum handoff so stale overflow pointer-map parents cannot survive reuse.
- Focused verification: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapFreelistVacuumCurrentSourceNext121Test.php`.
- Application smoke: `php lanes/libsqlite/examples/application-btree-pointermap-freelist-vacuum-current-source-next121.php`.
- Dependency closure: no new support component is needed; the slice reuses existing native PHP page image, overflow-chain, freelist, pointer-map, and B-tree page assembly helpers.
