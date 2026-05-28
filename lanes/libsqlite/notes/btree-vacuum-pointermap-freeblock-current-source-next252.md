# B-tree Vacuum Pointer-Map Freeblock Current Source Next252

## Scope

Adds a current-source handoff admission layer after the next248
pointer-map/freeblock publication seal. The slice verifies that vacuum
freeblock reuse for a deleted overflow-backed WordPress option row is admitted
only after:

- the next248 seal page order is preserved;
- pointer-map pages have been admitted before payload reuse;
- freeblock publication receipts are admitted before payload reuse;
- duplicate pointer-map rewrites remain visible in the handoff page list;
- fenced tail overflow pages 109 and 110 stay excluded.

## Evidence

Focused verification passed locally:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext252Test.php
# 1 test files, 1251 assertions, 0 failures
# 131 PASS lines
```

Dashboard delta: `phpPass` moves from `128615` to `128746` for the 131
verified PASS lines. Mapped upstream coverage remains unchanged because this
is focused PHP behavior coverage over the existing B-tree vacuum
pointer-map/freeblock inventory surface.

## Non-Overlap

This does not repeat next248 publication seal construction, next235
checkpoint rows, overflow freelist release, page relocation, root collapse, or
bulk overflow freeblock materialization. It adds the admission gate that
exposes the already sealed current-source vacuum freeblock pages to reuse.

## Dependency Closure

No new support component is needed. The slice reuses the existing B-tree page,
pointer-map, table-leaf delete, and next248 seal helpers.
