# B-tree Vacuum Pointer-Map Freeblock Current-Source Next299-302

Date: 2026-05-29

This follow-on slice extends the accepted next295-298 freelist-splice admission
surface to next299-302. The shared current-source variant now admits these four
slice numbers through the same pointer-map-scoped freelist splice receipt path,
preserving vacuum tokens, trunk-before-leaf ordering, reusable leaf slot
ordinals, write offsets, tail-page rejection, and link continuity.

Focused verification:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext299302Test.php
php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next299-302.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext299302Test.php
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next299-302.php
git diff --check -- lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext299302Test.php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next299-302.php lanes/libsqlite/notes/yield-btree-vacuum-pointermap-freeblock-current-source-next299-302.md
```

Dependency closure: no new support component is needed. This reuses next261
vacuum finalization rows and the accepted next295-298 freelist-splice variant
shape.

Non-overlap: this only admits and validates next299-302 freelist-splice
receipts. It does not repeat next295-298, next261 reusable-slot finalization,
next259 source-next links, overflow freelist release, bulk overflow freeblocks,
page relocation, root collapse, VFS, WAL, JSON, SQL, or encoding behavior.
