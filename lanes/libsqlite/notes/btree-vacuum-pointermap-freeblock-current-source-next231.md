# B-tree Vacuum Pointer-Map Freeblock Current Source Next231

## Scope

Adds a next-writer current-source handoff layer for the B-tree vacuum
pointer-map/freeblock path. The slice starts after next227 publication sealing
and admits exactly that sealed page set into the next writer source:

- pointer-map handoff rows must remain before payload rows;
- the duplicate pointer-map rewrite for page 105 is preserved as an explicit
  handoff, not collapsed away;
- truncated tail overflow pages 109 and 110 stay fenced;
- the secure-delete leaf freeblock receipt is carried into the next writer
  admission token.

## Evidence

Focused verification passed locally:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext231Test.php
# 1 test files, 1197 assertions, 0 failures
# 141 PASS lines
```

Application smoke passed locally:

```sh
php lanes/libsqlite/examples/application-btree-vacuum-pointermap-freeblock-current-source-next231.php
# application-btree-vacuum-pointermap-freeblock-current-source-next231 self-test passed
```

The lane-status `phpPass` delta is +141, from the accepted batch200 baseline
112201 to 112342 pending integration. No mapped upstream denominator movement
is claimed.

## Non-Overlap

This adds next-writer current-source handoff admission after next227
publication sealing. It does not repeat next227 sealing, next219 readback,
next217 page-write materialization, overflow freelist release, page
relocation, root collapse, bulk overflow freeblocks, or the accepted
batch200 next225-next227 B-tree vacuum surfaces.

## Dependency Closure

No new support component is needed. The slice reuses next227 publication
seals, duplicate pointer-map rewrite receipts, leaf freeblock receipts, and
fenced-tail guards.
