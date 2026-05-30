### 2026-05-27 PRAGMA integrity index/freelist current next38

This slice extends native PHP `PRAGMA integrity_check` deep b-tree walking for
auto-vacuum databases. Interior table and index b-tree pages now verify that
their left-child and right-most child pages have pointer-map entries of type
`btree-page` whose parent is the owning interior page.

Focused movement:

- Added 50 focused PASS cases in
  `SQLitePragmaIntegrityIndexFreelistCurrentNext38Test.php`.
- Covered table-interior and index-interior valid child maps, type mismatches,
  parent mismatches, right-most child mismatches, bounded error limits, child
  pages beyond the database image, and `quick_check` skipping this deep scan.
- Added a Application smoke showing copied `wp_options` index preflight with
  valid child parentage, a corrupt right-most pointer-map parent, and
  shallow `quick_check` behavior.

Verification:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityIndexFreelistCurrentNext38Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 50 assertions, 0 failures
```

Non-overlap:

- Avoids accepted PRAGMA function/module/collation metadata, table/index info
  cursor surfaces, generated-column `table_xinfo`, deep freelist/pointer-map
  free-page cross-checks, B-tree page relocation/root-collapse/overflow
  freelist release/freeblock coalescing, VFS writer/lock/sync/apply clusters,
  WAL checkpoint/savepoint byte truncation, JSON table source/cursor/constraint
  work, SELECT SQL text/JOIN/GROUP/subquery/ORDER clusters, and Unicode GLOB.
- The new behavior is specifically current-source `PRAGMA integrity_check`
  validation of interior b-tree child pointer-map parentage while preserving
  the accepted freelist scan and `quick_check` boundaries.

Dependency closure:

- Reuses existing native PHP database page, pointer-map, b-tree interior,
  index-cell, table-cell, and freelist trunk helpers.
- No new support component is needed.
