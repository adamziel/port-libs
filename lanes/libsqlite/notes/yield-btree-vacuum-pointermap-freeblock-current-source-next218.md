# B-tree Vacuum Pointer-Map Freeblock Current Source Next218

## Scope

- Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Plan`.
- Builds on next212 current-source apply rows and emits per-page write receipts.
- Confirms pointer-map write receipts precede payload write receipts for each vacuum cursor.
- Carries leaf freeblock receipts and keeps fenced tail pages out of the write sequence.
- Adds WordPress smoke coverage for deleting an overflow-backed copied `wp_options` transient before vacuum write application.

## Non-overlap

This slice does not repeat next212 page apply ordering, next209 source latching, next206 sealing, overflow freelist release, root collapse, page relocation, bulk overflow freeblock materialization, or accepted B-tree freeblock/freelist reuse diagnostics. It is a narrower current-source write receipt layer after the accepted apply rows.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext218Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 1017 assertions, 0 failures
```

PASS-line delta: `+137` focused PASS lines.

WordPress smoke:

```text
php lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next218.php
wordpress-btree-vacuum-pointermap-freeblock-current-source-next218 self-test passed
```

Dependency closure: no new support component needed; next218 reuses native B-tree page parsing, pointer-map metadata, next212 apply rows, and existing test fixtures.
