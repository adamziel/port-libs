# B-tree Empty Leaf Freelist Pointer-map Current-source Next97

## Behavior

This slice adds `SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNextPlan`, a current-source evidence wrapper for multi-page B-tree cleanup after table/index leaf deletes leave pages empty. It composes the existing native empty-leaf batch free primitive, materializes the next database image, and exposes per-page rows showing:

- the deleted empty table/index leaf pages and obsolete overflow pages released in source order;
- current pointer-map ownership before release;
- next pointer-map `free-page` ownership and zero parent values after release;
- freelist trunk/leaf roles, traversal positions, and next allocation positions;
- secure-delete clearing for released freelist leaves.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeEmptyLeafFreelistPointerMapCurrentSourceNext97Test.php`
- Result: `1 test files, 256 assertions, 0 failures`, with `64` PASS lines.

## WordPress Smoke

- `php lanes/libsqlite/examples/wordpress-btree-empty-leaf-freelist-pointermap-next97.php`
- The smoke models copied `wp_options` transient cleanup where an empty table leaf and an empty option-name index leaf are released with their obsolete overflow pages before the next insert reuses the freelist.

## Non-overlap

This avoids accepted page relocation, overflow freelist release, overflow freeblock coalescing, pointer-map vacuum append/freepage, root collapse, index-interior merge, and batch91 overflow pointer-map/freepage surfaces. The new surface is empty non-root table/index leaf release into the freelist with current-source pointer-map rows.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree page assembly, SQLite database image, freelist, and auto-vacuum pointer-map primitives.
