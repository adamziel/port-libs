# JSON table generated path rowid cost current-source next168

Status: focused PHP behavior growth for `json-table-generated-path-rowid-cost-current-source-next168`.

This slice adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext168()`. It layers bounded cursor-yield behavior on top of the accepted generated-path/rowid current-source planner: a pinned `json_tree()` current source can emit rowid batches with a stable resume token, while a changed next source with generated-path drift is marked for cursor reprepare before yield.

Application smoke: `application-json-table-generated-path-rowid-cost-current-source-next168.php` covers copied `wp_options` plugin rule diagnostics where the current option yields rowids `[5, 6]` from a pinned generated-path/rowid plan and the next imported option changes generated path coverage enough to force reprepare.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext168Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next168.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext168Test.php`
  - `1 test files, 58 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next168.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next168 self-test passed`

Dashboard delta:

- `phpPass`: `75459 -> 75517` from the 58 focused PASS lines above.
- `benchmarkDenominator.mapped`: unchanged at `611 / 1589`; this is current-source PHP behavior over the existing JSON table planner surface, not a newly hydrated upstream inventory row.

Non-overlap: this avoids accepted JSON table cursor/source wiring, hidden and visible constraint pushdown, generated path/rowid next145/159/160/161/164 admission/order behavior, JSON grouped rows, JSON aggregate/window behavior, malformed JSONB planner diagnostics, and non-JSON B-tree/WAL/VFS/SQL/encoding clusters. The new surface is specifically the current-source rowid-yield and resume-token layer after generated-path/rowid cost admission.

Dependency closure: no new support component is needed. The slice reuses native JSON table path validation, rowid seek planning, current-source admission, and ORDER BY profiles.
