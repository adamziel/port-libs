# yield-sqlite-jsonb-tree-generated-index-current-next38

This slice extends `SQLiteGeneratedJsonPathIndexPlan` with
`btreeYieldPlan()`, a bounded native-PHP current/next B-tree yield preview for
generated `jsonb_extract()` index columns.

Behavior covered:

- Reuses the accepted generated JSON path current/next mutation planner.
- Materializes current and next index leaf page images from generated JSONB
  keys and rowids.
- Emits deterministic delete/insert B-tree cell actions for changed partial
  indexes.
- Preserves descending index order, `NOCASE` ordering, unique-index conflict
  checks, and partial-index membership.
- Adds a copied `wp_options` smoke for plugin-setting JSONB generated indexes.

Focused verification:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonBTreeGeneratedIndexCurrentNext38Test.php
Focused test run: 1 selected test files (root lock skipped)
PASS jsonb generated index btree yield current next38 generated column metadata
PASS jsonb generated index btree yield current next38 before and after generated values
PASS jsonb generated index btree yield current next38 logical index updates
PASS jsonb generated index btree yield current next38 materializes ordered leaf images
PASS jsonb generated index btree yield current next38 emits delete and insert cells
PASS jsonb generated index btree yield current next38 keeps unchanged rows and validates conflicts

1 test files, 73 assertions, 0 failures
```

Non-overlap: this does not repeat accepted generated JSON path logical
current/next planning, JSON table source/cursor work, JSON visible/hidden
constraint pushdown, B-tree page relocation/root collapse/interior merge, or
overflow freelist release. It adds the missing generated-JSONB index B-tree
cell/page yield layer on top of the already accepted logical index delta.

Dependency closure: no new support component is needed; the slice reuses
existing native PHP JSONB, generated-column, index-cell, index-leaf, and record
encoding components.
