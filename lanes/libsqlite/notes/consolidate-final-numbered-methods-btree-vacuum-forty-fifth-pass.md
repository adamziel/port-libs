# B-tree Vacuum Pointer-Map Freeblock Source Cursor Consolidation

## Behavior

Consolidates the B-tree vacuum pointer-map freeblock source-cursor surface into `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan::tableLeafSourceCursorFromDeleteResult()`, which consumes the accepted freelist-link rows and validates the current-source cursor that a later writer would use to reuse those pages. The slice checks source links, duplicate pointer-map replay, pointer-map visibility before payload reuse, retained freeblock receipts, and tail-page exclusion.

## Evidence

- Focused test: `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceCursorTest.php`
- WordPress smoke: `php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-cursor.php`
- PHP lint: changed PHP files only.
- Diff check: `git diff --check -- lanes/libsqlite`

## Non-Overlap

This is a current-source cursor validation layer after next238 freelist admission. It does not repeat next238 freelist-link admission, next235 checkpoint admission, overflow freelist release, page relocation, root collapse, index-interior merge, or bulk overflow freeblock materialization.

## Dependency Closure

No new support component is needed. The implementation reuses existing B-tree page, pointer-map, and current-source plan primitives.
