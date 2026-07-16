# B-tree Overflow Freeblock Pointer-map Current Source Next128

- Slice: `btree-overflow-freeblock-pointermap-current-source-next128`.
- Behavior: coalesces current leaf freeblocks left by an overflow-backed delete, releases obsolete overflow pages to the auto-vacuum freelist, then allocates a replacement current/next overflow chain from those just-freed pages.
- Non-overlap: avoids accepted bulk overflow freeblocks, overflow freelist release, rootpage reuse, page relocation, and freeblock vacuum/truncation slices by asserting the combined pointer-map transition through obsolete overflow -> free-page -> replacement overflow ownership on the current source image.
- Application path: copied `wp_options` transient replacement during import/update without ext/sqlite.
- Dependency closure: no new support component is needed; this reuses existing bounded B-tree page, freelist, overflow-page, and pointer-map primitives.
- Focused evidence: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockPointerMapCurrentSourceNext128Test.php` passes with 80 PASS lines / 1 file / 206 assertions / 0 failures.
