# JSON Table Generated Path Rowid Cost Current Source Next205

Status: focused PHP behavior growth for `json-table-generated-path-rowid-cost-current-source-next205`.

This slice adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext205()`. It composes the accepted generated-path rowid alias projection layer with rowid-alias `ORDER BY` consumption so a pinned current-source `json_tree()` cursor can satisfy `ORDER BY rowid`, `_rowid_`, `oid`, or `id` from the xColumn cache without a temp sorter. If the next source changes, the alias cache is unpinned, or the order term cannot be consumed, the plan records a sorter/reprepare boundary and preserves the upstream replan reasons.

Application smoke: `application-json-table-generated-path-rowid-cost-current-source-next205.php` covers copied `wp_options` plugin-rule JSON diagnostics that project `rowid`, `_rowid_`, and `oid` while ordering by `_rowid_ DESC`.

Verification:

```sh
php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php
php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext205Test.php
php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next205.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext205Test.php
php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next205.php --self-test
git diff --check -- lanes/libsqlite
```

Focused result: `1 test files, 50 assertions, 0 failures` with 50 PASS lines.

Expected dashboard movement: `phpPass +50` from `98594` to `98644`; `benchmarkDenominator.mapped` remains unchanged because this is current-source PHP behavior over existing JSON table generated-path rowid planner inventory.

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible constraint extraction, generated path/rowid cost through next203, pinned source, xFilter argv, xColumn cache, xNext continuation, JSON dynamic joins, storage/VFS/B-tree/WAL surfaces, and status-only evidence. The new behavior is specifically rowid-alias `ORDER BY` consumption over the generated-path rowid alias projection cache.

Dependency closure: no new support component is needed. The slice reuses native PHP JSON table row generation, generated-path rowid costing, xColumn cache materialization, and rowid alias projection helpers.
