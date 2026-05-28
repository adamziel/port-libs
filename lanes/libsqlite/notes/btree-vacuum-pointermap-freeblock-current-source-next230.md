# B-tree Vacuum Pointer-Map Freeblock Current Source Next230

## Scope

Adds a current-source finalization layer after the next227 publication seal for
the B-tree vacuum pointer-map/freeblock path. The slice verifies that the
sealed pages are finalized in the same apply order:

- pointer-map pages finalize before any payload page;
- duplicate pointer-map rewrites remain visible in the final page list;
- payload pages depend on the finalized pointer-map set;
- fenced tail overflow pages 109 and 110 stay excluded;
- the secure-delete leaf freeblock receipt is carried through finalization.

## Evidence

Focused verification passed locally:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext230Test.php
# 1 test files, 1497 assertions, 0 failures
# 147 PASS lines
```

The lane-status `phpPass` delta is +147, from the accepted batch200 baseline
112201 to 112348 pending integration. No mapped upstream denominator movement
is claimed.

## Non-Overlap

This does not repeat next227 publication sealing, next219 readback, next217
page-write materialization, overflow freelist release, page relocation, root
collapse, or bulk overflow freeblock materialization.

## Dependency Closure

No new support component is needed. The slice reuses next227 publication seals,
duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail
guards.
