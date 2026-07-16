# B-tree Cell Payload Split Overflow Current Next36

This slice adds `SQLiteBTreeCellPayloadSplitPlan`, a bounded native PHP helper
for materializing SQLite table-leaf and index-cell payload split metadata:
local payload bytes, overflow payload bytes, first overflow page, current/next
overflow page links, and per-page payload byte counts.

The focused tests cover table and index payload thresholds, Application-sized
`wp_options` table and `option_name` index records, generated multi-page
current/next overflow chains, and invalid overflow-page metadata.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteBTreeCellPayloadSplitPlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeCellPayloadSplitOverflowCurrentNext36Test.php
php -l lanes/libsqlite/examples/application-btree-cell-payload-split-current-next36.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeCellPayloadSplitOverflowCurrentNext36Test.php
php lanes/libsqlite/examples/application-btree-cell-payload-split-current-next36.php
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 324 assertions, 0 failures` with 54 PASS cases.

Non-overlap: this does not repeat accepted B-tree page relocation,
root-collapse, index-interior merge, overflow freelist release, bulk overflow
freeblocks, table/index delete rebalance, or VFS/WAL/SQL/JSON accepted
clusters. It only exposes the cell-local/overflow split needed before later
delete/rebalance materializers decide which overflow current/next pages must be
rewritten or released.

Dependency closure: no new support component is needed; the slice reuses the
existing table/index cell local-payload formulas, record encoder, and overflow
page count semantics.
