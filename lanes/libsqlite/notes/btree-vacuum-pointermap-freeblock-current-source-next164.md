# B-tree Vacuum Pointer-Map Freeblock Current Source Next164

## Scope

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext164Plan`, a focused follow-up for partial auto-vacuum after deleting a Application-sized overflow payload and then allocating a replacement overflow chain.
- The plan validates final overflow next-pointer continuity across a reused surviving page plus appended pages that had just been truncated by vacuum.
- It records source, post-vacuum, and final next pointers, final pointer-map type/parent, materialization status, and page hashes for each released overflow page.

## Non-overlap

- Avoids accepted page relocation/root-collapse/interior-merge and accepted overflow freelist release surfaces.
- Builds on the accepted next144/next161 partial-vacuum freeblock behavior without changing their action labels or duplicating their assertions.
- Does not touch WAL, JSON, VFS, schema, planner, or suite-runner surfaces.

## Verification

- Focused command: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext164Test.php`
- Result: `1 test files, 276 assertions, 0 failures` with 60 PASS lines.
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next164.php`

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP SQLite page, pointer-map, freelist, overflow-page, and table leaf helpers.

## Next

Continue B-tree work in non-overlapping delete/rebalance/freeblock or pointer-map apply paths that materialize page images rather than adding status-only diagnostics.
