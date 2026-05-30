# B-tree Vacuum Pointer-map Freeblock Current Source Next242

## Scope

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Plan`, a current-source freeblock handoff layer over the accepted next238 freelist-link admission. The slice verifies that reusable freeblock pages are exposed only after pointer-map barriers, leaf freeblock receipts are visible, the freelist trunk candidate remains stable, reusable pages are monotonic, and fenced tail pages stay excluded.

## Application Scenario

`examples/application-btree-vacuum-pointermap-freeblock-current-source-next242.php` models deletion of an overflow-backed copied `wp_options` transient before vacuum/reuse. The smoke reports pointer-map source pages, reusable freeblock pages, trunk candidate pages, and the receipt/barrier guards needed before the current source can reuse those pages.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext242Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1255 assertions, 0 failures
```

The focused run emitted 135 PASS lines.

## Non-overlap

This slice does not repeat next238 freelist-link admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index-interior merge, rollback/VFS writer behavior, or accepted WAL/checkpoint/savepoint clusters.

## Dependency Closure

No new support component is needed. The plan reuses existing native PHP B-tree, pointer-map, table-leaf delete, overflow-chain, and freelist/freeblock planning helpers.
