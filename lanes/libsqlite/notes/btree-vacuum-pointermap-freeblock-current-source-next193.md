## B-tree Vacuum Pointer-Map Freeblock Current Source Next193

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext193Plan`,
a published current-source manifest layer over the accepted next189 checkpoint
resume rows. It verifies that a reader following partial vacuum sees only
readable database-header, pointer-map, leaf-freeblock, and replacement overflow
pages while the vacuum-truncated tail pages remain fenced past the final page
count.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext193Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext193Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next193.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext193Test.php` -> `1 test files, 705 assertions, 0 failures` with 100 PASS lines.
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next193.php` -> self-test passed.

Non-overlap:

- Adds publication-manifest checks after next189 checkpoint resume.
- Does not repeat next189 checkpoint construction, next186 cursor visibility,
  next185 durability receipts, overflow freelist release, page relocation,
  root collapse, bulk overflow freeblock materialization, or the accepted
  freelist trunk pointer-map reuse surface.

Dependency closure: no new support component needed; next193 reuses the
existing B-tree page model, next189 checkpoint tokens, current-source high-water
pages, pointer-map ordering, and next-reader EOF fences.
