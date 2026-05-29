# B-tree page rebalance pointer-map current-source next88

## Behavior

This slice adds `SQLiteBTreeRebalancePointerMapCurrentSourceNextPlan` for the
delete/rebalance path where an overflow-backed index cell is removed from the
left sibling, a cell is redistributed from the right sibling, and obsolete
overflow pages are released to the freelist only after the rebalance image is
formed.

The plan records pointer-map entries at four stages:

- current source database
- after the cell delete page image
- after sibling rebalance
- next database after freelist release

This proves that rebalance reads the current-source pointer-map ownership for
the B-tree pages and overflow chain, while the next image rewrites released
overflow pages to `free-page`.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeRebalancePointerMapCurrentSourceNext88Test.php
```

Result:

```text
1 test files, 167 assertions, 0 failures
82 PASS lines
```

## WordPress smoke

Example:

```sh
php lanes/libsqlite/examples/wordpress-btree-rebalance-pointermap-current-source-next88.php
```

The smoke reports a copied `wp_options` `option_name` index deletion where
sibling rebalance preserves current pointer-map ownership until obsolete
overflow pages are released into the freelist.

## Non-overlap

This avoids the accepted B-tree page relocation, root collapse, overflow
freelist release, overflow delete pointer-map, index-interior merge, and
delete/rebalance freeblock-only clusters. It focuses on the current-source
pointer-map transition across rebalance and next freelist release.

## Dependency closure

No new support component is needed. The slice reuses existing native PHP B-tree
page, index leaf, freelist, pointer-map, and record primitives.
