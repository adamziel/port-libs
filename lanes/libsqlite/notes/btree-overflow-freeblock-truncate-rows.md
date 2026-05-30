# B-tree Overflow Freeblock Truncate Rows

This consolidation replaces the former numbered truncate helper with the stable
`SQLiteOverflowVacuumTruncatePlan::overflowFreeblockTruncateRows()`.
It records each obsolete overflow page by delete-result source, captures the
source database overflow next-pointer and pointer-map type before freelist
release, and compares that with the materialized next database after
incremental-vacuum tail truncation. Surviving pages are reported as freelist
pages; omitted tail pages are reported as truncated.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowFreeblockTruncateRowsTest.php`
  - `1 test files, 237 assertions, 0 failures`
  - 61 focused PASS lines

Application smoke:

- `php lanes/libsqlite/examples/application-btree-overflow-freeblock-truncate-rows.php`
  - emits copied `wp_options` overflow/freeblock release and truncate
    row evidence without `ext/sqlite`.

Non-overlap:

- Avoids accepted overflow freelist release, pointer-map vacuum truncate/apply,
  bulk overflow freeblocks, overflow freelist release, page relocation, root
  collapse, index-interior merge, VFS writer/sync/lock/rollback clusters, WAL
  byte/checkpoint clusters, JSON table source/cursor/constraint clusters,
  SELECT SQL text/group/order/subquery clusters, and Unicode GLOB behavior.
  The new surface is preserving source overflow-chain evidence at the
  freeblock/truncate current-next boundary while materializing a shorter next
  database.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  SQLite database, pointer-map, freelist, overflow, and truncation primitives.
