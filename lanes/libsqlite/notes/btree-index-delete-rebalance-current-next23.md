# B-tree Index Delete Rebalance Current Next23

## Scope

- Adds `SQLiteBTreeIndexDeleteRebalancePlan` for the upstream B-tree delete path where an index leaf record is deleted, the underfull leaf remains non-empty, and cells are rebalanced with the right sibling through the parent divider.
- This is intentionally narrower than pager/VDBE delete execution. It materializes the affected page images and summary evidence for the index leaf, parent divider, and right sibling.
- Non-overlap: avoids accepted root collapse, table/index page relocation, overflow freelist release, bulk overflow freeblocks, index-interior merge, batch21 freepage allocation, and leaf-balance-only diagnostics.

## Focused Evidence

Command:

```bash
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeIndexDeleteRebalanceCurrentNext23Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
40 PASS lines
1 test files, 139 assertions, 0 failures
```

PHP lint:

```bash
php -l lanes/libsqlite/src/SQLiteBTreeIndexDeleteRebalancePlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeIndexDeleteRebalanceCurrentNext23Test.php
php -l lanes/libsqlite/examples/application-index-delete-rebalance-current-next23.php
```

## Dashboard Delta

- `phpPass`: `8166` -> `8206` from the verified 40 focused PASS lines.
- `benchmarkDenominator.mapped`: `458` -> `459` for one focused B-tree delete/rebalance evidence row.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP b-tree page header, index cell, index leaf page, index interior page, record, and database page-image primitives.
