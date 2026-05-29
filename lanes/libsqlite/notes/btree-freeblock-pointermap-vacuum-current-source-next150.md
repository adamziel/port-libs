# B-tree Freeblock Pointer-map Vacuum Current-source Next150

This slice adds `SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext150Plan`, a replayable current-source summary over the existing native freeblock coalesce and incremental-vacuum primitives.

Focused behavior:

- a copied `wp_options` transient delete leaves the table leaf page materialized with a coalesced freeblock;
- obsolete tail overflow pages are released and then truncated by incremental vacuum;
- the auto-vacuum pointer-map page at the tail boundary is represented as a truncated pointer-map page, not as a freelist page;
- page hashes, pointer-map pages, materialized/truncated row state, overflow next pointers, and freeblock integrity are exposed for current-source replay.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreeblockPointerMapVacuumCurrentSourceNext150Test.php
# Focused test run: 1 selected test files (root lock skipped)
# 1 test files, 366 assertions, 0 failures
# 86 PASS lines

php lanes/libsqlite/examples/wordpress-btree-freeblock-pointermap-vacuum-current-source-next150.php --self-test
# wordpress-btree-freeblock-pointermap-vacuum-current-source-next150 self-test passed
```

Non-overlap:

This avoids accepted overflow freelist release, bulk overflow freeblocks, pointer-map vacuum/freeblock next135 and next144 row materialization, overflow vacuum next145, freelist-vacuum behavior, root collapse, page relocation, index-interior merge, and freelist trunk pointer-map reuse. The new surface is the current-source page-image rowset that ties together the materialized leaf freeblock, truncated released overflow pages, and truncated pointer-map boundary page for replay/audit.

Dependency closure:

No new support component is needed. The slice reuses existing native PHP SQLite database images, B-tree freeblock coalescing, overflow vacuum truncation, freelist traversal, and auto-vacuum pointer-map helpers.
