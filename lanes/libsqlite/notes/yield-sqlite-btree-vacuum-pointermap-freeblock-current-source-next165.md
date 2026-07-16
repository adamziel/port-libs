# B-tree Vacuum Pointer-Map Freeblock Current Source Next165

Implemented `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext165Plan`, a
current-source write-admission layer over the accepted next162 vacuum
pointer-map/freeblock path.

The new behavior compares each writable page against both the current source
image and the post-vacuum allocation image, reports changed writable pages,
unchanged writable pages, rejected truncated current-source pages, overflow
next-page rewrites, and pointer-map parent/type transitions. This closes the
storage-corruption edge where a vacuum/replacement plan must not write stale
current-source pages after the next database image has truncated them.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext165Test.php`
- Result: `1 test files, 478 assertions, 0 failures` with 78 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next165.php --self-test`

Non-overlap:

- Avoids accepted table/index page relocation, root collapse, overflow freelist
  release, bulk overflow freeblocks, delete-overflow vacuum pointer-map
  next119, and vacuum pointer-map/freeblock next156-next162 write-row
  admission.
- This slice only adds current-source versus next-image page-byte and
  pointer-map transition classification for the already planned write set.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  SQLite database/page, freelist allocation, overflow-page, and pointer-map
  helpers under `lanes/libsqlite/src`.
