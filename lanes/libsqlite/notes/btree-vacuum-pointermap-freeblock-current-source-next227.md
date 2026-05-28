# B-tree Vacuum Pointer-Map Freeblock Current Source Next227

## Scope

Adds a durable publication-seal layer for the current-source B-tree vacuum
pointer-map/freeblock path. The slice starts after next219 readback and checks
that the exact readable page set is the final publishable set:

- pointer-map pages are sealed before payload pages;
- duplicate pointer-map rewrites remain visible as rewrites, not collapsed away;
- tail overflow pages 109 and 110 remain fenced from the seal;
- the secure-delete leaf freeblock receipt is carried into the final seal.

## Evidence

Focused verification passed locally:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext227Test.php
# 1 test files, 1175 assertions, 0 failures
# 135 PASS lines
```

The lane-status `phpPass` delta is +135, from the accepted batch198 baseline
108262 to 108397 pending integration. No mapped upstream denominator movement
is claimed.

## Non-Overlap

This does not repeat next219 readback, next217 page-write materialization,
next212 apply ordering, overflow freelist release, page relocation, root
collapse, or bulk overflow freeblock materialization.

## Dependency Closure

No new support component is needed. The slice reuses next219 readback rows,
duplicate pointer-map rewrite receipts, leaf freeblock receipts, and fenced-tail
guards.
