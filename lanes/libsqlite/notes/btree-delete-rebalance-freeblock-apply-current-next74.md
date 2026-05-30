# B-tree Delete Rebalance Freeblock Apply Current/Next74

Adds `SQLiteBTreeDeleteRebalanceFreeblockApplyPlan`, a bounded native PHP
write-apply plan for delete-heavy table/index leaves. The plan consumes the
current deleted leaf image, materializes the next defragmented leaf image,
preserves surviving cell order, and composes obsolete overflow pages into the
freelist plus auto-vacuum pointer-map updates.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeDeleteRebalanceFreeblockApplyCurrentNext74Test.php`
  - `1 test files, 63 assertions, 0 failures`
- `php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/application-btree-delete-rebalance-freeblock-apply-current-next74.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeDeleteRebalanceFreeblockApplyPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeDeleteRebalanceFreeblockApplyCurrentNext74Test.php`
- `php -l lanes/libsqlite/examples/application-btree-delete-rebalance-freeblock-apply-current-next74.php`
- `git diff --check -- lanes/libsqlite`

Application smoke:

The copied `wp_options` transient delete smoke reports the deleted option
rowid, defragmented leaf freeblock byte transition, freed obsolete overflow
pages, pointer-map page updates, and page images that must be written for a
repair/import transaction without requiring `ext/sqlite`.

Non-overlap:

This avoids accepted B-tree page relocation, root collapse, index-interior
merge, overflow freelist release, bulk overflow-backed freeblocks,
freeblock-only defragment diagnostics, pointer-map vacuum apply, and
batch72/73 pointer-map rebalance surfaces. The new surface is applying a
current deleted leaf page into its next defragmented page image while also
materializing freelist and pointer-map side effects for obsolete overflow pages.

Dependency closure:

No new support component is needed. The slice reuses lane-local B-tree page
headers, table/index leaf page compaction, record/cell codecs, freelist
planning, and pointer-map update primitives.
