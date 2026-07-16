# B-tree Vacuum Pointer-map Freeblock Current-source Next177

## Scope

This slice adds a deterministic next-source replay batch on top of the accepted
next174 cursor rows. The behavior is intentionally after cursor fencing: only
readable current-source pages are grouped into replay batches, pointer-map
dependency pages are carried beside the batch, and truncated pages remain
fenced out of the downstream handoff.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext177Test.php`
- Application smoke: `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next177.php --self-test`
- PHP lint: changed PHP files only.
- Diff hygiene: `git diff --check -- lanes/libsqlite`

## Non-overlap

This does not repeat next174 cursor resume generation, next171 transition
classification, next166 write admission, overflow freelist release, root
collapse, page move, bulk overflow freeblocks, or accepted batch162 next174
reader cursor behavior. It adds the downstream replay-batch contract required
before a later writer can consume current-source page images without reading
truncated source pages.

## Dependency Closure

No new support component is needed. The slice reuses native b-tree page images,
pointer-map type/parent data, current-source cursor rows, and truncation fences.
