# B-tree Vacuum Pointer-Map Freeblock Current-Source Next259

## Behavior

- Added `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext259Plan`.
- The plan consumes next255 publication rows and validates current-source next links before freeblock and reusable overflow payload pages are read.
- It verifies publication token carry-forward, source-next page links, duplicate pointer-map generations, freeblock source ordering, payload wait guards, fenced tail pages, and source-next token chaining.

## Application Smoke

- Added `examples/application-btree-vacuum-pointermap-freeblock-current-source-next259.php`.
- The smoke models deletion of an overflow-backed copied `wp_options` transient and verifies source pages `[2, 3, 105, 106, 105, 107, 108]`, source-next links `[3, 105, 106, 105, 107, 108, null]`, duplicate pointer-map source page `[105]`, and payload reuse only after the leaf freeblock source is visible.

## Verification

- Red-first check before validator repair:
  - `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext259Test.php`
  - failed because duplicate pointer-map generations after the first freeblock source were incorrectly rejected.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext259Test.php`
  - `1 test files, 1564 assertions, 0 failures`
  - 136 PASS lines
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext259Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext259Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next259.php`
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next259.php`
  - emitted `application-btree-vacuum-pointermap-freeblock-current-source-next259 self-test passed`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - emitted `lane-status json ok`
- `git diff --check -- lanes/libsqlite`
  - passed with no output

## Non-Overlap

This slice adds current-source next-link validation after next255 publication. It avoids accepted next255 publication, next254 write slots, next251 admission, next248 sealing, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, VFS, WAL, JSON, SQL planner, and encoding clusters.

## Dependency Closure

No new support component is needed. The slice reuses native SQLite database/page parsing, pointer-map entries, table leaf delete helpers, and existing B-tree current-source/freeblock plans.
