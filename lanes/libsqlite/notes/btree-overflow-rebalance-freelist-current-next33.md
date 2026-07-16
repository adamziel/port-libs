# B-tree Overflow Rebalance Freelist Current/Next 33

This slice adds `SQLiteDatabase::planOverflowPageAllocation()` for the upstream
B-tree path where a replacement payload needs a new overflow chain after delete
or rebalance freed pages into the current freelist. The allocator reuses the
existing freelist current/next trunk logic, skips pointer-map pages on append,
and writes auto-vacuum pointer-map entries as `first-overflow-page` followed by
`overflow-page` parent links instead of incorrectly marking the chain as B-tree
pages.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowRebalanceFreelistCurrentNext33Test.php`
  - `1 test files, 57 assertions, 0 failures`
- `php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/application-overflow-rebalance-freelist-current-next33.php`
  - emits the copied `wp_options` overflow-chain allocation summary with
    current/next freelist trunk reuse and pointer-map parent chain evidence.

Non-overlap:

- Avoids the accepted overflow freelist release, bulk overflow freeblock, page
  move, root-collapse, and index-interior merge slices. This covers the next
  allocation side of an overflow rebalance: newly allocated overflow pages get
  overflow pointer-map ownership, not generic B-tree page ownership.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  freelist, pointer-map, and page-image helpers.
