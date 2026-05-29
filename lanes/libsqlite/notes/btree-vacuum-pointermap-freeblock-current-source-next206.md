# B-tree Vacuum Pointer-map Freeblock Current-source Next206

## Behavior

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan`, a current-source writer seal built on top of the accepted next203 cursor admission. It converts cursor batches into ordered pointer-map and payload seal rows so a next writer can replay the readable source pages without exposing truncated tail pages.

The behavior is intentionally narrower than overflow release, page relocation, root collapse, and bulk freeblock materialization. It validates that pointer-map pages are sealed before payload/freeblock pages, leaf freeblock receipts survive the handoff, cursor tokens remain chained, and vacuum-fenced tail pages remain absent.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext206Test.php`
- `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next206.php`
- PHP lint on changed PHP files
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses native b-tree leaf/freeblock deletion, overflow-chain traversal, incremental-vacuum truncation fences, auto-vacuum pointer-map metadata, and the accepted next203 current-source cursor batches.
