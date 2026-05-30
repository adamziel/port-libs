# B-tree Interior Overflow Pointer-Map Current/Next 40

## Behavior

- Extends index-interior sibling merge planning so overflow-backed index interior cells can be parsed, reassembled, and reported with auto-vacuum pointer-map updates.
- The first overflow page for a moved index-interior payload is retargeted to the merged b-tree page; continuation overflow pages keep `overflow-page` entries parented by the previous overflow page.
- This is intentionally narrower than accepted table/index page relocation, root collapse, overflow freelist release, and index-interior merge apply: it covers overflow pointer-map ownership for index-interior payload cells that survive a current/next sibling merge.

## Focused Evidence

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeInteriorOverflowPointerMapCurrentNext40Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 74 assertions, 0 failures
```

The run emits 50 PASS lines for the new focused test file.

## Application Smoke

```text
php lanes/libsqlite/examples/application-btree-interior-overflow-pointermap-current-next40.php
```

The smoke models a copied `wp_options` option-name index interior merge where a plugin-settings separator payload spills into overflow pages and needs pointer-map ownership retargeted to the merged page.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP b-tree page, index cell, overflow page, pointer-map, freelist, and SQLite database image helpers.
