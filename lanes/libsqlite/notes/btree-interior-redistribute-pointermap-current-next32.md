# B-tree Interior Redistribute Pointer-Map Current Next32

## Delta

Adds `SQLiteBTreeInteriorRedistributionApplyPlan`, a bounded application layer
over the existing table/index interior redistribution planner. The new helper
materializes sibling page images into a current database image, rewrites the
parent divider cell, applies auto-vacuum pointer-map ownership updates, and
returns post-apply pointer-map entries for current/next inspection.

## Verification

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeInteriorRedistributePointerMapCurrentNext32Test.php
```

Result: `1 test files, 62 assertions, 0 failures`.

```bash
php lanes/libsqlite/examples/application-btree-interior-redistribute-pointermap-current-next32.php
```

Result: exits 0 and reports copied `wp_options` option-name index interior
redistribution with parent divider replacement and pointer-map parent rewrites.

## Non-overlap

This does not repeat the accepted standalone table-interior redistribution
planning, table/index page relocation, index-interior merge, root collapse, or
overflow freelist release clusters. The new coverage is the current database
application surface: parent divider page image plus pointer-map page images and
post-apply pointer-map entries.

## Dependency Closure

No new support component is required. The patch reuses lane-local SQLite page
assemblers, record encoding, auto-vacuum pointer-map mutation, and current page
image inspection primitives.
