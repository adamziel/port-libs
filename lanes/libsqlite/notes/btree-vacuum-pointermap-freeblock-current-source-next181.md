# B-tree Vacuum Pointer-map Freeblock Current-source Next181

## Behavior

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Plan`.
- Builds on accepted next178 publication receipts and creates the next-reader current-source snapshot after a delete, partial vacuum, and replacement overflow write.
- Admits only published leaf/overflow pages, carries leaf freeblock and overflow next-pointer receipts forward, and quarantines truncated tail pages so stale current-source bytes cannot be replayed.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext181Test.php`
  - `1 test files, 820 assertions, 0 failures`
  - `100` PASS lines
- `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next181.php`
  - self-test passed

## Non-overlap

This is additive after next178 publication receipts. It does not repeat next178 publication, next175 admission fencing, next173 transition rows, overflow freelist release, page relocation, root collapse, or bulk overflow freeblocks. The new surface is the next-reader snapshot boundary for published current-source pages and quarantined truncated tails.

## Dependency Closure

No new support component is needed. The slice reuses native b-tree page images, leaf freeblock receipts, overflow next-pointer receipts, and auto-vacuum pointer-map metadata already present in the libsqlite lane.
