# B-tree vacuum pointer-map freeblock current-source next202

Status: focused PHP behavior growth for current-source cursor finalization after source-next writer handoff.

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext202Plan`. It builds on next196 source-next handoff rows and admits the current-source cursor only when writer-visible pages advance monotonically, pointer-map guard pages are present before payload pages, leaf freeblock receipts remain visible, fenced tail pages stay blocked, and resume tokens chain across batches.

Application smoke: `application-btree-vacuum-pointermap-freeblock-current-source-next202.php` models deleting an overflow-backed copied `wp_options` transient and advancing the next writer over validated header, pointer-map, table leaf freeblock, and replacement overflow page batches.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext202Test.php`
  - `1 test files, 755 assertions, 0 failures`
  - 115 focused PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next202.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext202Plan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext202Test.php`
- `php -l lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next202.php`
- `git diff --check -- lanes/libsqlite`

Expected dashboard movement: focused `phpPass +115` from `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext202Test.php`. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over already mapped B-tree vacuum, pointer-map, freeblock, and overflow inventory.

Non-overlap: avoids next196 handoff, next192 reader validation, next189 checkpoint construction, next186 cursor visibility, next183 commit receipts, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, WAL/VFS/JSON/SQL/encoding surfaces, and suite-runner evidence. The new boundary is current-source cursor finalization after the source-next handoff is already built.

Dependency closure: no new support component is needed. The slice reuses lane-local database page images, source-next tokens, auto-vacuum pointer-map carry-forward flags, leaf freeblock receipts, and fenced-tail metadata.
