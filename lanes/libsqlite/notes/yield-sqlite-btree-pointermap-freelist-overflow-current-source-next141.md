# B-tree Pointer-Map Freelist Overflow Current Source Next141

## Behavior

Adds `SQLiteBTreePointerMapFreelistOverflowCurrentSourceNext141Plan`, a bounded
auto-vacuum B-tree transition plan for stale prepared overflow chains. The plan
compares prepared-source overflow pages with the current database image, releases
only the current-source overflow chain into the freelist, and reallocates the
replacement chain with fresh pointer-map ownership.

The Application path models a stale prepared `wp_options` transient row whose old
overflow pages are no longer the current cell payload. The current autoload
index overflow chain is freed and reused, while the stale prepared pages remain
owned as ordinary b-tree pages and are not accidentally inserted into the
freelist.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapFreelistOverflowCurrentSourceNext141Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 273 assertions, 0 failures
PASS_LINES=77
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-pointermap-freelist-overflow-current-source-next141.php --self-test
```

Result:

```text
application-btree-pointermap-freelist-overflow-current-source-next141 self-test passed
```

## Non-Overlap

This does not repeat next132 single-source overflow freelist reuse, next138
freeblock-coalesced current-source reuse, accepted overflow freelist release,
page-move, root-collapse, or bulk overflow freeblock clusters. The new behavior
is the prepared-vs-current source guard that prevents freeing stale prepared
overflow pages when the current source has moved to a different overflow chain.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP
`SQLiteOverflowFreelistReleasePlan`, `SQLiteFreelistAllocationPlan`,
`SQLiteOverflowPage`, and auto-vacuum pointer-map page image application.
