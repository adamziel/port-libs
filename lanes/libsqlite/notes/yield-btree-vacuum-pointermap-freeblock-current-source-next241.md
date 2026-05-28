# B-tree Vacuum Pointer-Map Freeblock Current Source Next241

## Behavior

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext241Plan`, which consumes the accepted next238 freelist-link rows and validates the current-source cursor that a later writer would use to reuse those pages. The slice checks next-page links, duplicate pointer-map replay, pointer-map visibility before payload reuse, retained freeblock receipts, and tail-page exclusion.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext241Test.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next241.php`
- PHP lint: changed PHP files only.
- Diff check: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This is a current-source cursor validation layer after next238 freelist admission. It does not repeat next238 freelist-link admission, next235 checkpoint admission, overflow freelist release, page relocation, root collapse, index-interior merge, or bulk overflow freeblock materialization.

## Dependency Closure

No new support component is needed. The implementation reuses existing B-tree page, pointer-map, and current-source plan primitives.
