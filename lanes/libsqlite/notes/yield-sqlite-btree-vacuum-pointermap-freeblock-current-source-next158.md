# B-tree Vacuum Pointer-Map Freeblock Current-Source Next158

## Behavior

This slice covers the current-source B-tree path where deleting a copied
`wp_options` transient row releases an overflow chain, auto-vacuum truncates
the tail pages, and the surviving released page is immediately reused as a new
overflow page. The new plan verifies that the survivor moves from
`free-page` pointer-map ownership to `first-overflow-page`, while truncated
tail pages remain unavailable for allocation.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext158Test.php`
  - `1 test files, 205 assertions, 0 failures`
  - `61` focused PASS lines
- Application smoke:
  - `php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next158.php --self-test`

## Non-Overlap

This does not repeat accepted overflow freelist release, pointer-map vacuum
freeblock diagnostics, or page relocation work. It specifically adds the
post-vacuum allocation step that reuses the surviving free page as an overflow
page and checks that truncated tail pages are not reused.

## Dependency Closure

No new support component is needed. The slice reuses lane-local B-tree
delete/freeblock, auto-vacuum pointer-map, freelist allocation, and overflow
page image primitives.
