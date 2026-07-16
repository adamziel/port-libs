# B-tree Freeblock Defrag Current/Next70

Adds `SQLiteBTreeFreeblockDefragPlan`, a bounded native PHP application plan
for table/index leaf page defragmentation after delete-heavy current/next
maintenance. The plan materializes compacted page images through the existing
leaf compactor, clears the freeblock chain, resets fragmented-byte accounting,
rewrites cell pointers to compacted cells, and preserves total free-space
accounting.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeFreeblockDefragCurrentNext70Test.php`
- `php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/application-btree-freeblock-defrag-current-next70.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeFreeblockDefragPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeFreeblockDefragCurrentNext70Test.php`
- `php -l lanes/libsqlite/examples/application-btree-freeblock-defrag-current-next70.php`
- `git diff --check -- lanes/libsqlite`

Non-overlap:

- Avoids accepted current/next56 mutation fragment absorption, current/next31
  adjacent-freeblock coalescing, overflow freelist release, bulk overflow
  freeblocks, page move, root collapse, index-interior merge, pointer-map
  vacuum, and VFS/WAL/JSON/SELECT clusters.
- The new surface is page-image defragmentation after delete fragmentation:
  table/index leaf freeblocks are removed and remaining cells are physically
  compacted for the next allocation.

Dependency closure:

- No new support component is needed. This reuses existing native PHP b-tree
  page headers, table/index leaf cell parsers, record encoding, and leaf page
  compaction helpers.
