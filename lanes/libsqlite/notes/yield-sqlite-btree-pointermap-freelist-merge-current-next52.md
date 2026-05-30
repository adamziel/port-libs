# yield-sqlite-btree-pointermap-freelist-merge-current-next52

## Delta

- Added `SQLiteFreelistFreePlan::existingTrunkPageNumbers()` and optional summary evidence for freed pages that merge into an existing non-full freelist trunk instead of promoting a new trunk.
- Added focused coverage for copied `wp_options` table and `option_name` index overflow chains followed by current next-page pointers, secure-delete clearing, auto-vacuum pointer-map free-page rewrites across pointer-map pages 2 and 105, and rejection of current trunk / pointer-map page release.
- Added a Application smoke showing obsolete overflow pages appended to existing freelist trunk page 8 with no new trunk promotion.

## Focused Evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapFreelistMergeCurrentNext52Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 64 assertions, 0 failures
```

```text
$ php lanes/libsqlite/examples/application-btree-pointermap-freelist-merge.php
releasedOverflowPages: [20, 22, 106, 107, 21]
existingFreelistTrunks: [8]
newFreelistTrunks: []
pointerMapTypes: ["free-page", "free-page", "free-page", "free-page", "free-page"]
```

## Non-Overlap

This slice does not repeat accepted full-freelist-trunk spill/new-trunk promotion, overflow freelist release, root collapse, page relocation, or bulk overflow freeblock materialization. It covers the current-next overflow-chain path where released pages fit in the current freelist trunk and must preserve existing freelist leaves while rewriting auto-vacuum pointer-map entries.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP SQLite page, overflow-chain, freelist trunk, pointer-map, and database image helpers.
