# libsqlite B-tree overflow freelist rebalance current-source next130

Timestamp: 2026-05-28T07:44:59Z

Slice: `btree-overflow-freelist-rebalance-current-source-next130`

Behavior added:

- Added `SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNext130Plan` for the current-source B-tree case where obsolete overflow pages are released into an existing freelist, but a smaller replacement overflow chain consumes older freelist leaves first.
- The plan records released overflow pages that remain deferred on the freelist with `FREE_PAGE` pointer-map entries, allocated pages reused from the pre-existing freelist, final freelist order, and page images needed for handoff/application.
- Added WordPress smoke coverage for a copied `wp_options` transient shrink path where obsolete overflow pages must stay safely reusable rather than being immediately reallocated.

Focused evidence:

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreelistRebalanceCurrentSourceNext130Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 137 assertions, 0 failures
```

The focused run emitted 73 `PASS` lines, all from the new lane-scoped test file.

```text
$ php lanes/libsqlite/examples/wordpress-overflow-freelist-rebalance-current-source-next130.php
{
    "releasedOverflowPages": [6, 7],
    "allocatedOverflowPages": [9],
    "deferredReleasedOverflowPages": [6, 7],
    "reusedExistingFreelistPages": [9],
    "finalFreelistPages": [8, 7, 10, 6],
    "releasedPointerMapTypes": ["free-page", "free-page"],
    "allocatedPointerMapTypes": ["first-overflow-page"]
}
```

Non-overlap:

- Avoids accepted overflow freelist release (`SQLiteOverflowFreelistReleasePlan` next125/128-style immediate reuse), bulk overflow freeblocks, pointer-map page relocation, root collapse, and index-interior merge.
- This slice specifically covers deferred obsolete overflow pages left on an existing freelist after a smaller replacement chain consumes older freelist entries first.

Dependency closure:

- No new support component is needed. This reuses existing native PHP B-tree page, overflow page, freelist trunk, pointer-map, and integrity-check primitives.

Next task:

- Apply similar deferred-free safety to broader delete/rebalance application paths only if a real current-source mutation path exposes the same ordering edge.
