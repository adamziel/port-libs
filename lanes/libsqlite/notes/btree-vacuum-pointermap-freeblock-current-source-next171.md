# B-tree vacuum pointer-map freeblock current-source next171

- Behavior: adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext171Plan`, a current-source transition classifier over the accepted next167 final-image audit. It separates stable deleted-leaf freeblock pages, replacement overflow pages reused from the current source after partial vacuum, surviving free current-source pages, and rejected truncated current-source pages.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next171.php --self-test`.
- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext171Test.php`.
- Non-overlap: avoids accepted next167 final leaf freeblock/page-image checks, next164 replacement-chain continuity, next163 admission fencing, overflow freelist release, root collapse, page move, index-interior merge, bulk overflow freeblock materialization, and freelist trunk pointer-map reuse. The new surface is the current-source transition classification and guard that truncated pages are not admitted as materialized output.
- Dependency closure: no new support component needed; this reuses native PHP B-tree leaf/freeblock images, overflow-chain allocation, pointer-map rows, and incremental-vacuum truncation helpers.
