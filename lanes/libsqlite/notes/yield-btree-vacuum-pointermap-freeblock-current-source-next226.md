# B-tree vacuum pointer-map freeblock current-source next226

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext226Plan`,
which consumes the accepted next219 readback rows and emits final publish-fence
receipts for the current-source page set. It preserves duplicate pointer-map
rewrite receipts for page 105, confirms readback/current-source tokens, keeps
tail pages 109/110 excluded, and requires pointer-map publication before any
payload page is published.

Application smoke:
`application-btree-vacuum-pointermap-freeblock-current-source-next226.php` models
deleting an overflow-backed copied `wp_options` transient and publishing only
the safe pointer-map/freeblock pages after readback verification.

Verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext226Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 593 assertions, 0 failures
```

Expected dashboard delta: `phpPass +98`, from `108262` to `108360`, from the
98 verified focused PASS lines. Mapped upstream coverage remains `625 / 1589`;
this is focused native B-tree current-source behavior over already mapped
pointer-map/freeblock/vacuum inventory, not a fresh upstream denominator row.

Non-overlap: this is after next219 readback and does not repeat next219
readback rows, next217 page writes, next212 apply ordering, overflow freelist
release, page relocation, root collapse, or bulk overflow freeblock
materialization.

Dependency closure: no new support component needed. The slice reuses native
current-source readback rows, read tokens, duplicate pointer-map rewrite
receipts, and fenced-tail guards already present in the lane.
