# B-tree Interior Delete Merge Current/Next 27

## Scope

Adds bounded table-interior delete rebalance application for the current child plus its next sibling. The helper derives the next sibling and separator from the parent interior page, merges the pages, removes the parent divider and next-child pointer, frees the obsolete next sibling, and applies auto-vacuum pointer-map rewrites for moved children.

## Evidence

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeInteriorDeleteMergeCurrentNext27Test.php`
- Result: `1 test files, 225 assertions, 0 failures`
- PASS-line delta: `+51` focused PHP PASS cases.

## Non-Overlap

This does not repeat accepted root collapse, table/index page relocation, index-interior merge, overflow freelist release, or bulk overflow freeblock behavior. The new surface is parent-page materialization for a delete merge of the current table-interior child with its next sibling.

## Dependency Closure

No new support component is needed. This reuses existing native PHP b-tree page assembly, database page-image, freelist, and pointer-map primitives.

## Next

Extend the same current/next parent-derived application pattern to index-interior parent pages if a later slice needs index divider payload extraction from the parent.
