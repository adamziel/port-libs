# B-tree Vacuum Pointer-map Freeblock Current Source Next245

## Scope

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Plan`, a source-cursor admission layer over the accepted next242 current-source freeblock rows. The slice verifies that reusable freeblock pages are admitted only after a pointer-map epoch is open, current-source tokens match, leaf freeblock receipts remain visible, the trunk candidate is preserved, fenced tail pages stay excluded, and cursor links are continuous.

## Application Scenario

`examples/application-btree-vacuum-pointermap-freeblock-current-source-next245.php` models deletion of an overflow-backed copied `wp_options` transient before vacuum/reuse. The smoke reports admitted pages, pointer-map barriers, reusable freeblock pages, cursor epochs, and cursor-link guards needed before current-source pages are reusable.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext245Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1401 assertions, 0 failures
```

The focused run emitted 141 PASS lines.

## Non-overlap

This slice does not repeat next242 current-source visibility, next238 freelist-link admission, overflow freelist release, bulk overflow freeblocks, page relocation, root collapse, index-interior merge, rollback/VFS writer behavior, JSON table work, or WAL/checkpoint/savepoint clusters.

## Dependency Closure

No new support component is needed. The plan reuses existing native PHP B-tree, pointer-map, table-leaf delete, overflow-chain, current-source, freelist, and freeblock planning helpers.
