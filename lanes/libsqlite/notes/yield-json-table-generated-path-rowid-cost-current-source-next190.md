# JSON table generated path rowid cost current-source next190

Status: focused PHP behavior growth for `json-table-generated-path-rowid-cost-current-source-next190`.

This slice adds `SQLiteJsonTablePlan::currentSourceGeneratedPathRowidCostCurrentSourceNext190()`. It extends the accepted generated-path rowid current-source yield guard by checking the pinned source generation and final-cost fingerprint against the xColumn materialized row snapshot before emitting the yielded JSON table row. Stale next sources, missing materialized rowids, and non-covering snapshots are blocked with explicit reprepare/reseek opcodes and cost classes.

Application smoke: `application-json-table-generated-path-rowid-cost-current-source-next190.php` covers copied `wp_options` plugin-rule diagnostics where a `json_tree()` generated-path/rowid yield may emit the current xColumn row only while the current source and snapshot fingerprint still match.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  - `No syntax errors detected in lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext190Test.php`
  - `No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext190Test.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next190.php`
  - `No syntax errors detected in lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next190.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext190Test.php`
  - `1 test files, 69 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-cost-current-source-next190.php --self-test`
  - `application-json-table-generated-path-rowid-cost-current-source-next190 self-test passed`

Dependency closure: no new support component is needed; this reuses the native JSON table cursor, generated-path rowid cost/final-cost snapshots, xColumn materialization, and current-source yield guards.

Non-overlap: avoids accepted JSON table cursor/source wiring, hidden/visible constraints, host joins, generated path rowid final-cost next184, resume/yield/cursor next185-next187 behavior, JSON aggregate/window behavior, SQL expression ORDER BY/GROUP/subquery/compound work, VFS/WAL/B-tree/encoding clusters, and suite runner evidence. The new surface is specifically current-source xColumn row emission after a generated-path/rowid yield guard.
