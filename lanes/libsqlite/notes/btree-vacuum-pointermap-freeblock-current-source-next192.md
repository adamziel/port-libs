# B-tree vacuum pointer-map freeblock current-source next192

Status: focused PHP behavior growth for post-checkpoint current-source reader validation.

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`. It builds on the accepted next189 checkpoint rows and admits the next reader only after checkpoint tokens still match, page hashes replay, pointer-map pages precede payload pages, the leaf freeblock page is validated, and fenced tail pages remain excluded.

WordPress smoke: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next192.php` models deleting an overflow-backed copied `wp_options` transient, vacuuming obsolete overflow tail pages, and admitting the next reader for the committed header, pointer-map, leaf freeblock, and replacement overflow pages.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext192Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next192.php`

Expected dashboard movement: `phpPass +106` from the new focused test file. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over already mapped B-tree vacuum, pointer-map, freeblock, overflow, and current-source reader inventory.

Non-overlap: avoids next189 checkpoint construction, next186 cursor visibility, next183 commit receipts, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, WAL/VFS/JSON/SQL/encoding surfaces, and suite-runner evidence. The new boundary is next-reader validation after resumable checkpoint rows are already built.

Dependency closure: no new support component is needed. The slice reuses lane-local database page images, cursor page hashes, checkpoint tokens, auto-vacuum pointer-map ordering, leaf freeblock metadata, and fenced-tail truncation metadata.
