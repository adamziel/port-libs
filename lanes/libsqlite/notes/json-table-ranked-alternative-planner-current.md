# JSON table ranked alternative planner current

Status: focused PHP behavior growth for `libsqlite-json-table-planner-current`.

Implemented `SQLiteJsonTablePlan::rankedAlternativePlan()` as a stable unsuffixed planner entry point for OR-style `json_each()` / `json_tree()` alternatives. It composes existing xBestIndex constraint planning, ranks runnable branches by cost/row estimate/branch index, merges duplicate virtual-table rows deterministically, and forces global ORDER BY sorting when multiple branches cannot consume order as one contiguous scan.

WordPress path: `wordpress-json-table-ranked-alternative-planner.php` models copied `wp_options` JSON diagnostics where plugin settings need OR-branch planning over `json_tree()` for names, priorities, and object rows without ext/sqlite.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableRankedAlternativePlannerCurrentTest.php`
- `php -l lanes/libsqlite/examples/wordpress-json-table-ranked-alternative-planner.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableRankedAlternativePlannerCurrentTest.php`
  - `1 test files, 52 assertions, 0 failures`
- `php lanes/libsqlite/examples/wordpress-json-table-ranked-alternative-planner.php --self-test`
  - `wordpress-json-table-ranked-alternative-planner self-test passed`

Non-overlap: this avoids accepted JSON table cursor/source wiring, hidden/visible constraint extraction, host/dynamic joins, generated path rowid cost variants, malformed JSONB planner work, SELECT SQL JSON source execution, VFS/WAL/B-tree surfaces, and the forbidden numbered production-class pattern.

Dependency closure: no new support component is needed. The slice reuses native JSON table xBestIndex planning, residual filtering, ordering, and row identity helpers already present in `SQLiteJsonTablePlan`.
