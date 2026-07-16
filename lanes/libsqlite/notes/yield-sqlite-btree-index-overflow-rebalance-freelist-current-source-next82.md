# B-tree Index Overflow Rebalance Freelist Current Source Next82

## Behavior

Adds `SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNextPlan` for the index-leaf side of delete/rebalance: delete an overflow-backed index record from the current leaf, rebalance with the next sibling, release obsolete overflow pages into the freelist, rewrite auto-vacuum pointer-map entries to free-page, and materialize one current-source page-image set.

This intentionally avoids accepted table overflow/freeblock/reuse, bulk overflow freeblock materialization, overflow freelist release, page relocation, root collapse, and index-interior merge surfaces.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexOverflowRebalanceFreelistCurrentSourceNext82Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 134 assertions, 0 failures
```

The focused run emits 61 PASS lines for the new current-source B-tree behavior.

## Application Smoke

Command:

```bash
php lanes/libsqlite/examples/application-index-overflow-rebalance-freelist-next82.php
```

The smoke builds a copied `wp_options`-style option-name index where deleting an overflow-backed transient index key rebalances the index leaf and releases obsolete overflow pages into the freelist.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP B-tree page assembly/parsing, overflow chain, freelist, pointer-map, and record helpers.
