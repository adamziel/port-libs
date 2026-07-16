Source-neutral JSON table current-source cleanup

Micro-slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T110706Z-0`
Base accepted HEAD: `87b9b5e4231e455752546908281e85ed6f228913`

Changed behavior:
- `SQLiteJsonTablePlan.php` no longer emits legacy current-source token fields such as `sourceOptionId`, `sourceOptionName`, `source_option_id`, `option_id`, `option_name`, or `option_value` from the owned generated-path/current-source profiles. The replacement fields are generic `sourceSettingId`, `sourceKeyName`, `source_setting_id`, `setting_id`, `key_name`, and `key_value`.
- Source payload discovery now uses generic setting/value helpers while preserving caller-provided source columns and generated path/root behavior.
- JSON table projected `value` cells normalize internal JSON subtype wrappers to canonical JSON text for xColumn/SELECT rows, while internal transition comparisons compare JSON subtype values by canonical JSON content.
- `SQLiteSelectSql` keeps JSON table hidden `json`/`root` columns as constraint inputs instead of advertising or leaking them as joined output columns.

Direct test updates:
- Updated directly coupled generated-path rowid current-source tests to assert neutral setting/key/source-token names.
- Extended `SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` to scan `SQLiteJsonTablePlan.php`.
- Adjusted current-source cache and rowid-hidden plan tests to the neutral source token and alias-qualified plan row keys.

Verification:
- `php -l` on all changed PHP files: passed.
- Focused guard/direct set:
  `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostSourceTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasPlanTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext172Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidXFilterProgramPlanTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceCursorTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidMaterializationPlanTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCostCurrentSourceNext166Test.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceCostTest.php lanes/libsqlite/tests/SQLiteJsonTableHiddenRowidPathCurrentSourceTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedHiddenCostTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidAliasLimitTest.php lanes/libsqlite/tests/SQLiteJsonTableGeneratedPathRowidCurrentSourceCacheTest.php lanes/libsqlite/tests/SQLiteJsonTableRowidHiddenConstraintCurrentSourceNext84Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  Result: `15 test files, 789 assertions, 0 failures`.
- JSON table family:
  `php tools/run-tests.php $(find lanes/libsqlite/tests -maxdepth 1 -type f -name 'SQLiteJsonTable*Test.php' | sort)`
  Result: `304 test files, 20264 assertions, 0 failures`.
- Production source scan:
  `rg -n "sourceOption|source_option|option_id|option_name|option_value|autoload|blog_id|wp_options|wp_|WordPress|wordpress" lanes/libsqlite/src/SQLiteJsonTablePlan.php`
  Result: no matches.

Dependency closure:
- No new support component is needed. The patch reuses existing JSON table planner, cursor, rowid, and SELECT wiring.

Root harness:
- Not run - isolated micro-slice.
