# B-tree Vacuum Pointer-map Freeblock Current-source Next250

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext250Plan`.
- Focused behavior: after accepted next247 checkpoint admission, the next current-source handoff opens pointer-map barriers first, then the table freeblock source, then overflow payload source pages. Payload pages remain blocked until the freeblock source is open and the checkpoint row is ready.
- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext250Test.php` with focused PASS coverage.
- Adds Application smoke `application-btree-vacuum-pointermap-freeblock-current-source-next250.php` for copied `wp_options` transient cleanup after vacuumed overflow pages.
- Verification:
  - `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext250Plan.php`
  - `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext250Test.php`
  - `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next250.php`
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext250Test.php` -> `1 test files, 1579 assertions, 0 failures` with 139 PASS lines.
  - `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next250.php` -> self-test passed.
  - `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `git diff --check -- lanes/libsqlite`
- Non-overlap: this is a next250 handoff-barrier validation after next247 checkpoint admission. It does not repeat next247 checkpoint construction, next244 publish ordering, next241 source cursor rows, next238 freelist-link admission, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, accepted batch107/108/109-113 surfaces, or suite/status-only evidence.
- Dependency closure: no new support component is needed; it composes existing native B-tree, pointer-map, overflow, table-leaf, and current-source checkpoint helpers.
