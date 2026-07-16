# B-tree Write-Apply Rebalance Corruption Current Source Next106

Status: focused PHP behavior growth for B-tree write-apply current-source admission.

Behavior:

- `SQLiteBTreeDeleteRebalanceFreeblockApplyPlan` now validates the supplied post-delete table/index leaf page against the current database leaf before it defragments the leaf or applies freelist/pointer-map page images.
- Table leaves must equal the current leaf minus the declared deleted rowids, including payload hashes for remaining rows.
- Index leaves must equal the current leaf minus the declared deleted record values, including payload hashes for remaining records.
- Stale delete-result pages, wrong leaf pages, absent rowids, absent index records, and table/index page-type mismatches are rejected before writes are planned.

Evidence:

```text
php -l lanes/libsqlite/src/SQLiteBTreeDeleteRebalanceFreeblockApplyPlan.php
php -l lanes/libsqlite/tests/SQLiteBTreeWriteApplyRebalanceCorruptionCurrentSourceNext106Test.php
php -l lanes/libsqlite/examples/application-btree-write-apply-corruption-current-source-next106.php
No syntax errors detected.

php tools/run-tests.php lanes/libsqlite/tests/SQLiteBTreeWriteApplyRebalanceCorruptionCurrentSourceNext106Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 40 assertions, 0 failures

php lanes/libsqlite/examples/application-btree-write-apply-corruption-current-source-next106.php
stale_delete_result_status: SQLite delete rebalance freeblock apply rejected stale table leaf delete result
```

Dashboard delta:

- `phpPass`: `40990 -> 41030` from 40 new focused PASS lines.
- `benchmarkDenominator.mapped`: unchanged; this is PHP behavior coverage, not a new upstream manifest row.

Non-overlap:

- Avoids accepted overflow freepage/vacuum reuse, freeblock coalescing/defrag, bulk overflow freeblocks, overflow freelist release, pointer-map vacuum, page move, root collapse, index-interior merge, and queued next104/105 freelist/vacuum surfaces.
- This slice is limited to preventing stale current-source write-apply corruption for the existing delete/rebalance/freeblock apply path.

Dependency closure:

- Reuses existing native PHP B-tree page, record, freelist, pointer-map, and `SQLiteDatabase` helpers.
- No new support component is needed.
