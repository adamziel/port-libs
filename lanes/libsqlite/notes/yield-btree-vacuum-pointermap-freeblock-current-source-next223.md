# B-tree Vacuum Pointer-Map Freeblock Current Source Next223

## Scope

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNextPlan::tableLeafFromDeleteResultNext223()`.
- Builds on next218 per-page write receipts and publishes current-source source receipts for those pages.
- Confirms pointer-map source receipts are visible before payload source receipts for each vacuum cursor.
- Carries secure-delete freeblock receipts and keeps fenced vacuum tail pages out of the source publication set.
- Adds WordPress smoke coverage for deleting an overflow-backed copied `wp_options` transient before vacuum source publication.

## Non-overlap

This slice does not repeat next218 per-page write receipts, next212 apply ordering, next209 source latching, overflow freelist release, root collapse, page relocation, or accepted freeblock materialization. It is a narrower current-source publication fence after the accepted write rows.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext223Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 1138 assertions, 0 failures
```

PASS-line delta: `+148` focused PASS lines.

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next223.php
wordpress-btree-vacuum-pointermap-freeblock-current-source-next223 self-test passed
```

Dependency closure: no new support component needed; next223 reuses native B-tree page parsing, pointer-map metadata, next218 write receipts, and existing current-source fixtures.
