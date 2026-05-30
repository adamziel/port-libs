# B-tree vacuum pointer-map freeblock current-source next196

Status: focused PHP behavior growth for source-next writer handoff after current-source reader validation.

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`. It builds on the accepted next192 reader-validation rows and admits the next writer only after validation tokens still match, pointer-map pages are carried forward before payload pages, leaf freeblock receipts survive the handoff, token chains remain continuous, and fenced tail pages remain blocked from the next writable source.

WordPress smoke: `wordpress-btree-vacuum-pointermap-freeblock-current-source-next196.php` models deleting an overflow-backed copied `wp_options` transient, validating the post-vacuum current source, and carrying the validated header, pointer-map, leaf freeblock, and replacement overflow pages into the next writer source.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext196Test.php`
  - `1 test files, 589 assertions, 0 failures`
  - 109 focused PASS lines
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next196.php`
  - `wordpress-btree-vacuum-pointermap-freeblock-current-source-next196 self-test passed`

Expected dashboard movement: `phpPass +109` from the new focused test file. Mapped upstream coverage remains unchanged; this is current-source PHP behavior over already mapped B-tree vacuum, pointer-map, freeblock, overflow, and source-next writer inventory.

Non-overlap: avoids next192 reader validation, next189 checkpoint construction, next186 cursor visibility, next183 commit receipts, overflow freelist release, page relocation, root collapse, bulk overflow freeblocks, WAL/VFS/JSON/SQL/encoding surfaces, and suite-runner evidence. The new boundary is source-next writer handoff after current-source validation rows are already built.

Dependency closure: no new support component is needed. The slice reuses lane-local database page images, checkpoint and validation tokens, auto-vacuum pointer-map ordering, leaf freeblock metadata, and fenced-tail truncation metadata.
