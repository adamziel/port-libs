# yield-sqlite-jsonb-index-constraint-delete-current-next51

2026-05-27 isolated slice `yield-sqlite-jsonb-index-constraint-delete-current-next51`.

## Behavior

- Added `SQLiteGeneratedJsonPathIndexPlan::deleteBtreeYieldPlan()` for current/next DELETE maintenance of JSONB-fed generated-column indexes.
- The plan evaluates generated `jsonb_extract()` columns for current rows, removes selected rowids, emits current index delete entries, materializes before/after index leaf page images, and returns deterministic B-tree delete cells.
- Covered partial `IS NOT NULL` membership, unique generated slug key release after DELETE, skipped missing rowids, descending rank index ordering, enabled-key ordering, and page-size variants.
- Added a copied `wp_options` smoke for plugin-setting JSONB generated indexes.

## Verification

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbIndexConstraintDeleteCurrentNext51Test.php
Focused test run: 1 selected test files (root lock skipped)
...
1 test files, 152 assertions, 0 failures
```

Focused PASS-line delta: `+47` new independent `TestRunner` PASS cases.

```text
php lanes/libsqlite/examples/application-jsonb-index-delete-current-next51.php --self-test
application-jsonb-index-delete-current-next51 self-test passed
```

## Non-overlap

This does not repeat accepted JSONB generated UPDATE index maintenance,
generated-index B-tree UPDATE yield, JSONB table upsert covering-index
maintenance, JSON visible/hidden constraint pushdown, JSON table cursor/source
work, B-tree page move/root-collapse/index-interior merge/overflow freelist
release, VFS writer/sync/lock/rollback clusters, WAL byte/checkpoint
transaction clusters, SQL expression ORDER BY/subquery/grouped text execution,
or Unicode GLOB behavior. The new behavior is DELETE-only current/next index
cell and leaf-image yield for JSONB generated indexes.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSONB,
JSON path extraction, generated-column analysis, CREATE INDEX parsing, index
predicate membership, record encoding, and index leaf page assembly.
