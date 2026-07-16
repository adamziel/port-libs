# B-tree vacuum pointer-map freeblock current-source next157

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext157Plan`, which composes the existing current-source delete/vacuum/freeblock plan and records source-to-next overflow-next pointer transitions for the deleted leaf, surviving freelist trunk, and truncated overflow tail pages.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next157.php --self-test`
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext157Test.php`

## Non-overlap

This avoids accepted page relocation, root collapse, index-interior merge, overflow freelist release, bulk overflow freeblocks, freelist trunk pointer-map reuse, next144 materialized page/hash rows, next148 pointer-map boundary truncation, and next149 replacement-overflow reuse. The new surface is current-source overflow `next` pointer classification after vacuum: the surviving free page has its overflow pointer cleared, non-terminal truncated pages are reported as severed current-source next pointers, and the deleted table leaf remains the materialized freeblock carrier.

## Dependency closure

No new support component is needed. The slice reuses native PHP B-tree delete/freeblock materialization, freelist truncation, pointer-map, and database image primitives under `lanes/libsqlite/src`.
