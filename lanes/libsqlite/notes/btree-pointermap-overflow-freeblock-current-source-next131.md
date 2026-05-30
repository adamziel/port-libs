# B-tree Pointer-map Overflow Freeblock Current-source Next131

This slice adds `SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNext131Plan`.
It composes the current-source freeblock coalescing path with obsolete overflow
release and the next overflow allocation path where one existing freelist page
and two just-released overflow pages become the replacement overflow chain.

Focused verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreePointerMapOverflowFreeblockCurrentSourceNext131Test.php
```

Result: `1 test files, 368 assertions, 0 failures` with `80` PASS lines.

Application smoke:

```sh
php lanes/libsqlite/examples/application-btree-pointermap-overflow-freeblock-current-source-next131.php
```

Result: copied `wp_options`-style transient replacement reports freeblock
fragment coalescing, released overflow pages `[5, 6]`, allocated replacement
chain `[9, 6, 5]`, mixed page origins, next pointer-map ownership
`first-overflow-page, overflow-page, overflow-page`, and final freelist `[8]`.

Non-overlap: avoids accepted batch129 pointer-map freelist overflow reuse,
batch128 overflow freeblock pointer-map reuse, overflow freelist release,
bulk overflow freeblocks, page relocation, root collapse, index-interior merge,
and pointer-map vacuum/rebalance surfaces. The new behavior is the combined
current-source boundary where freeblock coalescing, released overflow pages,
and an existing freelist page must agree before the next overflow chain is
materialized.

Dependency closure: no new support component is needed. The slice reuses
existing native PHP page image, freeblock, overflow-chain, freelist allocation,
and auto-vacuum pointer-map helpers.
