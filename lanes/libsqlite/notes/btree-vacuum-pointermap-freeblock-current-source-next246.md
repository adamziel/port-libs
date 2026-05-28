# B-tree Vacuum Pointer-map Freeblock Current Source Next246

## Scope

Adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Plan`, a vacuum reuse-cursor publication layer over the accepted next242 current-source handoff. The slice verifies that reusable freeblock pages are not allocated into the next source until current-source tokens match, pointer-map generations are current, duplicate pointer-map page generations are visible, leaf freeblock receipts remain current, the freelist trunk lease is stable, and fenced tail pages remain excluded.

## WordPress Scenario

`examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next246.php` models deletion of an overflow-backed copied `wp_options` transient before vacuum reuse. The smoke reports the reuse cursor pages, pointer-map barrier pages, allocated freeblock pages, duplicate pointer-map generation page, and the guards needed before a copied WordPress database image can reuse the pages.

## Evidence

Focused command:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext246Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1334 assertions, 0 failures
```

The focused run emitted 134 PASS lines.

## Non-overlap

This slice adds a reuse-cursor publication fence after next242 current-source handoff. It does not repeat next242 handoff visibility, next238 freelist admission, overflow freelist release, page relocation, root collapse, index-interior merge, bulk overflow freeblocks, accepted next242 B-tree vacuum pointer-map/freeblock behavior, JSON, WAL, pager, VFS, or SQL executor clusters.

## Dependency Closure

No new support component is needed. The plan reuses existing native PHP B-tree, pointer-map, table-leaf delete, overflow-chain, current-source, and freelist/freeblock planning helpers.
