# yield-sqlite-btree-interior-freelist-rebalance-current-next48

## Scope

- Added `SQLiteBTreeInteriorFreelistRebalancePlan` for non-empty table/index interior delete rebalances.
- Covers retained interior page freeblock accounting plus obsolete child and overflow page release into the freelist.
- Applies existing auto-vacuum pointer-map updates through `SQLiteDatabase::planPageFreeList()`.
- Keeps this distinct from accepted leaf freeblock/freelist rebalance, overflow freelist release, page relocation, root collapse, and index-interior merge slices.

## Focused evidence

```text
$ php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeInteriorFreelistRebalanceCurrentNext48Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS plans table-interior current-next48 freelist rebalance child1 overflowno secureno existingno
PASS plans table-interior current-next48 freelist rebalance child1 overflowyes secureno existingno
PASS plans table-interior current-next48 freelist rebalance child2 overflowno secureno existingno
PASS plans table-interior current-next48 freelist rebalance child2 overflowyes secureno existingno
PASS plans table-interior current-next48 freelist rebalance child3 overflowno secureno existingno
PASS plans table-interior current-next48 freelist rebalance child3 overflowyes secureno existingno
PASS plans table-interior current-next48 freelist rebalance child1 overflowno secureno existingyes
PASS plans table-interior current-next48 freelist rebalance child1 overflowyes secureno existingyes
PASS plans table-interior current-next48 freelist rebalance child2 overflowno secureno existingyes
PASS plans table-interior current-next48 freelist rebalance child2 overflowyes secureno existingyes
PASS plans table-interior current-next48 freelist rebalance child3 overflowno secureno existingyes
PASS plans table-interior current-next48 freelist rebalance child3 overflowyes secureno existingyes
PASS plans table-interior current-next48 freelist rebalance child1 overflowno secureyes existingno
PASS plans table-interior current-next48 freelist rebalance child1 overflowyes secureyes existingno
PASS plans table-interior current-next48 freelist rebalance child2 overflowno secureyes existingno
PASS plans table-interior current-next48 freelist rebalance child2 overflowyes secureyes existingno
PASS plans table-interior current-next48 freelist rebalance child3 overflowno secureyes existingno
PASS plans table-interior current-next48 freelist rebalance child3 overflowyes secureyes existingno
PASS plans table-interior current-next48 freelist rebalance child1 overflowno secureyes existingyes
PASS plans table-interior current-next48 freelist rebalance child1 overflowyes secureyes existingyes
PASS plans table-interior current-next48 freelist rebalance child2 overflowno secureyes existingyes
PASS plans table-interior current-next48 freelist rebalance child2 overflowyes secureyes existingyes
PASS plans table-interior current-next48 freelist rebalance child3 overflowno secureyes existingyes
PASS plans table-interior current-next48 freelist rebalance child3 overflowyes secureyes existingyes
PASS plans index-interior current-next48 freelist rebalance child1 overflowno secureno existingno
PASS plans index-interior current-next48 freelist rebalance child1 overflowyes secureno existingno
PASS plans index-interior current-next48 freelist rebalance child2 overflowno secureno existingno
PASS plans index-interior current-next48 freelist rebalance child2 overflowyes secureno existingno
PASS plans index-interior current-next48 freelist rebalance child3 overflowno secureno existingno
PASS plans index-interior current-next48 freelist rebalance child3 overflowyes secureno existingno
PASS plans index-interior current-next48 freelist rebalance child1 overflowno secureno existingyes
PASS plans index-interior current-next48 freelist rebalance child1 overflowyes secureno existingyes
PASS plans index-interior current-next48 freelist rebalance child2 overflowno secureno existingyes
PASS plans index-interior current-next48 freelist rebalance child2 overflowyes secureno existingyes
PASS plans index-interior current-next48 freelist rebalance child3 overflowno secureno existingyes
PASS plans index-interior current-next48 freelist rebalance child3 overflowyes secureno existingyes
PASS plans index-interior current-next48 freelist rebalance child1 overflowno secureyes existingno
PASS plans index-interior current-next48 freelist rebalance child1 overflowyes secureyes existingno
PASS plans index-interior current-next48 freelist rebalance child2 overflowno secureyes existingno
PASS plans index-interior current-next48 freelist rebalance child2 overflowyes secureyes existingno
PASS plans index-interior current-next48 freelist rebalance child3 overflowno secureyes existingno
PASS plans index-interior current-next48 freelist rebalance child3 overflowyes secureyes existingno
PASS plans index-interior current-next48 freelist rebalance child1 overflowno secureyes existingyes
PASS plans index-interior current-next48 freelist rebalance child1 overflowyes secureyes existingyes
PASS plans index-interior current-next48 freelist rebalance child2 overflowno secureyes existingyes
PASS plans index-interior current-next48 freelist rebalance child2 overflowyes secureyes existingyes
PASS plans index-interior current-next48 freelist rebalance child3 overflowno secureyes existingyes
PASS plans index-interior current-next48 freelist rebalance child3 overflowyes secureyes existingyes
PASS rejects duplicate interior current-next48 released pages
PASS rejects leaf page images for interior current-next48 rebalance

1 test files, 1202 assertions, 0 failures
```

## Status delta

- `phpPass`: `17373 -> 17423` from 50 newly passing focused PHP `TestRunner` cases.
- `benchmarkDenominator.mapped`: unchanged; no new upstream manifest unit mapped.
- Root harness: not run, isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP page-image parsing, freelist planning, and pointer-map update components.
