## B-tree Pointer-Map Vacuum Current/Next 68

This slice removes the contradictory append fallback guard in
`SQLiteBTreeOverflowAutoVacuumPointerMapPlan::allocateCurrentNextChain()`.
When a pointer-map vacuum truncates the old tail through a pointer-map page,
the next overflow insert can now append from the shortened current image,
skip the pointer-map page number, recreate that pointer-map page, and write
`first-overflow-page` / `overflow-page` ownership for the new chain.

Focused evidence:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumAppendCurrentNext68Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 56 PASS lines
# 1 test files, 224 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowAutoVacuumPointerMapCurrentNext53Test.php lanes/libsqlite/tests/SQLiteBTreePointerMapVacuumAppendCurrentNext68Test.php
# Focused test run: 2 selected test files (root lock skipped)
# 109 PASS lines
# 2 test files, 734 assertions, 0 failures

php lanes/libsqlite/examples/application-btree-pointermap-vacuum-append-current-next68.php
# reports vacuum final page 207, appended overflow pages 209-211, recreated
# pointer-map page 208, and correct overflow parent ownership.
```

Expected dashboard movement: `phpPass` +56 from `25285` to `25341`.
Mapped upstream denominator is unchanged.

Dependency closure: no new support component is needed. The slice reuses the
existing native PHP database header/page image, freelist truncation,
overflow-chain encoding, and auto-vacuum pointer-map primitives.

Non-overlap: this does not repeat accepted B-tree page relocation, root
collapse, overflow freelist release, pointer-map vacuum materialized apply,
VFS writer/lock/sync paths, WAL checkpoint/savepoint paths, SQL executor
clusters, or JSON table planner/cursor clusters.
