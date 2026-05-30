# B-tree vacuum pointer-map freeblock current-source next162

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext162Plan`, a current-source write-admission wrapper over the accepted delete/vacuum/freeblock replacement path.
- Focused surface: after a copied `wp_options` transient delete releases a five-page overflow chain, incremental vacuum truncates tail pages, surviving free pages are reused for a replacement overflow chain, and only materialized current-source pages are admitted to the next write set.
- Write evidence records database header, leaf freeblock page, pointer-map page cell offsets, replacement overflow page hashes/next pointers, and explicitly rejected truncated pages.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext162Test.php`
  - `1 test files, 456 assertions, 0 failures`
  - `72` PASS lines
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next162.php --self-test`

## Non-overlap

This avoids accepted next160 replacement-chain pointer-map validation, next159 reused/truncated chain classification, next156 allocation/reuse, overflow freelist release, bulk overflow freeblocks, freelist trunk pointer-map reuse, pointer-map vacuum append/apply, root collapse, page relocation, index-interior merge, and freeblock-only diagnostics. The new behavior is the current-source write-admission set after that path: truncated pages 109/110 are excluded while pages 1, 3, 105, 106, 107, and 108 remain materialized, hashable, and safe to write.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP SQLite database images, B-tree delete/freeblock materialization, auto-vacuum pointer-map helpers, freelist allocation/truncation, and overflow-page encoding.
