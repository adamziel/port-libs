# B-tree Vacuum Pointer-Map Freeblock Current Source Next167

- Added `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Plan` as an additive audit on top of the accepted next164 chain continuity behavior.
- The slice verifies that deleting a Application-like `wp_options` transient leaves the post-vacuum table leaf freeblock image unchanged while reused current-source overflow pages receive replacement overflow pointer-map entries.
- Focused tests: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext167Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next167.php --self-test`
- Dependency closure: no new support component needed; this reuses native b-tree page parsing, overflow allocation, pointer-map image application, and vacuum truncation helpers.
- Non-overlap: this does not repeat next164 overflow chain continuity, next163 current-source fencing, root collapse, page move, overflow freelist release, or bulk overflow freeblock materialization.
