# JSON table generated path rowid cost current-source next177

Slice: `json-table-generated-path-rowid-cost-current-source-next177`

Behavior:

- Adds `SQLiteJsonTablePlan::generatedPathRowidXFilterProgramPlan()`.
- Extends the accepted next174 generated-path rowid alias plan with an xFilter program/reset layer.
- Records generated-path and canonical rowid argv bindings, xFilter opcodes, source-pin keys, residual/omitted constraint columns, yield rowids/paths, cost class, and current/next replan reasons.
- Prevents a stale current-source cursor from silently reusing contradictory rowid aliases or a changed generated-path rowset.

Verification:

- `php -l lanes/libsqlite/src/SQLiteJsonTablePlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXFilterProgramPlanTest.php`
- `php -l lanes/libsqlite/examples/application-json-table-generated-path-rowid-xfilter-program.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXFilterProgramPlanTest.php`
  - `1 test files, 57 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-json-table-generated-path-rowid-xfilter-program.php --self-test`
  - `application-json-table-generated-path-rowid-xfilter-program self-test passed`

Application path:

- `examples/application-json-table-generated-path-rowid-xfilter-program.php` models copied `wp_options.active_plugins` diagnostics where a generated JSON path and rowid aliases can stay on the pinned current `json_tree()` source, while changed next-source JSON forces an empty/reset xFilter program instead of stale row reuse.

Non-overlap:

- Does not repeat accepted JSON table cursor/source wiring, hidden/visible constraint extraction, rowid alias dedupe next174, xBestIndex state next173, source-fence/yield profiles, or the accepted next174 generated-path rowid alias/cost behavior. This slice is only the xFilter program/reset admission layer above the accepted planner profiles.

Dependency closure:

- No new support component is needed. The slice reuses existing native PHP JSON table planning, JSON path validation, JSON tree row materialization, generated-path profiles, and rowid alias helpers.
