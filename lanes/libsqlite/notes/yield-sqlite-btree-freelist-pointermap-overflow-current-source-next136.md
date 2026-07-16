# B-tree Freelist Pointer-Map Overflow Current Source Next136

## Scope

This slice adds focused current-source B-tree coverage for a Application-style
delete/reinsert path where obsolete overflow pages are released to the freelist
and a larger replacement overflow payload consumes every freelist entry,
including the freelist trunk page itself.

The behavior is intentionally distinct from accepted `next132` overflow page
reuse: `next136` verifies the trunk page transition from freelist trunk bytes
to an overflow-chain page, the pointer-map rewrite from `free-page` to
`overflow-page`, and the final empty-freelist header state.

## Evidence

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNext136Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 233 assertions, 0 failures
```

The focused test emits 73 PASS lines for `SQLiteBTreeFreelistPointerMapOverflowCurrentSourceNext136Test.php`.

Application smoke:

```text
php lanes/libsqlite/examples/application-btree-freelist-pointermap-overflow-current-source-next136.php
```

The smoke prints the allocated overflow chain `[12, 9, 8, 7, 6, 10]`, final
freelist count `0`, and a trunk-overflow row showing page `10` changing from a
freelist trunk to an `overflow-page` with parent page `6`.

## Non-Overlap

Avoids accepted `SQLiteBTreeFreelistOverflowPointerMapCurrentSourceNextPlan`
coverage for released overflow leaf reuse that leaves the freelist trunk in
place. Avoids accepted overflow freelist release, page relocation, root
collapse, index-interior merge, and bulk overflow freeblock materialization.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local native
SQLite B-tree page, freelist, overflow-chain, and pointer-map primitives.
