# B-tree Overflow Delete Balance Current Next30

This isolated B-tree slice adds table-leaf current/next sibling balance
application after deleting an overflow-backed row from the current leaf.
`SQLiteBTreeTableDeleteRebalancePlan` removes the current row, records obsolete
overflow page numbers from the live overflow chain callback, keeps the current
leaf non-empty, then delegates to `SQLiteBTreeTableLeafBalanceApplyPlan` to move
rows from the next sibling and rewrite the table-interior parent divider key.

Verification:

- `php -l lanes/libsqlite/src/SQLiteBTreeTableLeafBalanceApplyPlan.php`
- `php -l lanes/libsqlite/src/SQLiteBTreeTableDeleteRebalancePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteBTreeOverflowDeleteBalanceCurrentNext30Test.php`
- `php -l lanes/libsqlite/examples/application-btree-overflow-delete-balance-current-next30.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeOverflowDeleteBalanceCurrentNext30Test.php`
  - `1 test files, 253 assertions, 0 failures`
  - 50 PASS lines
- `php lanes/libsqlite/examples/application-btree-overflow-delete-balance-current-next30.php`
  - emitted copied `wp_options` transient cleanup diagnostics with
    `table-delete-rebalance-apply`, moved current/next rowids, rewritten parent
    divider, and `requires_ext_sqlite: false`.

Status delta:

- `phpPass`: `10028 -> 10078` from the exact focused PASS-line delta.
- `benchmarkDenominator.mapped`: unchanged; this is focused native PHP behavior
  evidence, not a fresh upstream inventory mapping.

Non-overlap:

- Avoids accepted overflow freelist release, bulk overflow freeblock
  materialization, table/index page relocation, root collapse, index-interior
  merge, B-tree page move, and earlier index-only delete rebalance surfaces.
  This patch covers the table-leaf current/next sibling balance after a current
  overflow-backed delete.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP
  b-tree page, table leaf/interior cell, overflow page, record, and database
  image helpers.
