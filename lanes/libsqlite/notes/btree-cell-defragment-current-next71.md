# B-tree Cell Defragment Current/Next 71

Adds `SQLiteBTreeCellDefragmentPlan`, a bounded leaf-page materialization helper for the current/next defragment step after deletes leave freeblocks and fragmented bytes between live cells.

- Supports table and index leaf pages through the existing native PHP cell parsers and leaf defragmenter.
- Reports before/after cell offsets, key order, fragmented-byte cleanup, freeblock removal, moved-cell count, and updated page images.
- Keeps rowid/index-key order stable while rebuilding a contiguous cell content area and optionally clearing old free space.

Verification:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeCellDefragmentCurrentNext71Test.php
# 1 test files, 62 assertions, 0 failures

php -d auto_prepend_file=tools/bootstrap.php lanes/libsqlite/examples/application-btree-cell-defragment-current-next71.php --self-test
# application-btree-cell-defragment-current-next71 self-test passed
```

Non-overlap: this avoids accepted page relocation, root collapse, index-interior merge, overflow freelist release/reuse, bulk overflow freeblocks, pointer-map vacuum, freeblock current/next coalesce diagnostics, and mutation-delete fragment absorption. The new surface is the explicit cell-content defragment materialization step for existing table/index leaf pages after current/next free-space fragmentation.

Dependency closure: no new support component is needed. The slice reuses existing native PHP B-tree page-header, table/index cell, record, and leaf-page compaction primitives.
