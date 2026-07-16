# B-tree Freeblock Rebalance Cell Overflow Current Next76

This slice adds `SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNextPlan`, a
bounded native page-image plan for an overflow-backed table-cell delete that
first rebalances the current/next leaf siblings and then allocates the next
replacement overflow chain from the freelist state created by that delete.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreeblockRebalanceCellOverflowCurrentNext76Test.php
# 1 test files, 59 assertions, 0 failures
```

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-rebalance-cell-overflow-current-next.php
```

The smoke reports a copied `wp_options` transient cleanup where deleting rowid
`10` releases overflow pages `7,8`, moves two cells from the next sibling into
the current leaf, rewrites the parent divider to key `40`, and then uses pages
`8,7` plus appended tail page `9` for the replacement overflow chain with
auto-vacuum pointer-map ownership rewritten to `first-overflow-page` then
linked `overflow-page` entries.

Non-overlap:

This avoids accepted bulk overflow freeblock materialization, overflow
freelist release, current-next72 release-then-replacement without sibling
rebalance, page relocation, root collapse, index-interior merge, and
standalone B-tree freeblock/freelist rebalance diagnostics. The new behavior is
the composed cell delete, current/next leaf rebalance, obsolete overflow release,
and immediate next overflow-chain allocation from that current freelist state.

Dependency closure:

No new support component is needed. The slice composes existing lane-local
table leaf delete/rebalance, overflow-page chain encoding, freelist
release/allocation, auto-vacuum pointer-map, and database page-image primitives.
