# B-tree Vacuum Pointer-Map Freeblock Current Source Next220

## Behavior

This slice adds `SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan`, a commit-fenced layer over the accepted next217 page-write materialization.

The plan preserves the current-source B-tree vacuum flow for deleting an overflow-backed copied `wp_options` transient:

- pointer-map page writes are committed before payload/freeblock page writes;
- the duplicate pointer-map page rewrite for page `105` is retained as a real commit row;
- leaf freeblock receipt metadata is carried into the commit rows;
- fenced tail pages `109` and `110` remain excluded from committed writes;
- commit tokens chain from the next217 source write tokens.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext220Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 963 assertions, 0 failures
123 PASS lines
```

PHP lint:

```text
php -l lanes/libsqlite/src/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourcePlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeVacuumPointerMapFreeblockCurrentSourceNext220Test.php
php -l lanes/libsqlite/examples/wordpress-btree-vacuum-pointermap-freeblock-current-source-next220.php
```

## Non-Overlap

This does not repeat next217 write-row construction, next212 apply ordering, overflow freelist release, page relocation, root collapse, bulk overflow freeblock materialization, or accepted batch196 next217 coverage. It adds commit-fenced current-source publication after the existing next217 write materialization.

## Dependency Closure

No new support component is needed. The slice reuses native B-tree page assembly, pointer-map entries, overflow page-chain metadata, and the next217 write rows.
