# B-tree vacuum pointer-map freeblock current-source next175

Status: focused PHP behavior growth for `btree-vacuum-pointermap-freeblock-current-source-next175`.

Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext175Plan`, which composes the accepted next173 transition rows into a final current-source admission fence. Replacement overflow pages are admitted only after their final next-pointers no longer target a truncated current-source tail page, the stable leaf freeblock page remains hash/freeblock stable after allocation, and the truncated tail page is explicitly rejected from final current-source admission.

Application smoke: `application-btree-vacuum-pointermap-freeblock-current-source-next175.php` models a copied `wp_options` database deleting an overflow-backed transient, partially vacuuming the tail, and rewriting replacement overflow pages before admitting the current source for import continuation.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext175Test.php`
- Result: `1 test files, 433 assertions, 0 failures` with 83 PASS lines.
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next175.php --self-test`
- Result: `application-btree-vacuum-pointermap-freeblock-current-source-next175 self-test passed`

Dependency closure: no new support component needed; next175 reuses native current-source transition rows, b-tree freeblock page images, overflow next-pointer decoding, and auto-vacuum pointer-map metadata.

Non-overlap: additive after accepted next173 transition rows. It does not repeat next173 transition-row enumeration, next167 final leaf audit, next166 write admission, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or accepted WAL/VFS/JSON/SQL surfaces.
