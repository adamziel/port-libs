# Source-Neutral Skip-Scan Dynamic Slice

Session: `port-dev-sqlite-yield-dyn-neutral-domain-classes-20260601T132838Z`
Micro-slice: `source-neutral-src-domain-specific-option-classes-dynamic-20260601T132838Z-0`
Base accepted HEAD: `3fbf3e52f7c6e6a72c8a17054cab01a393183925`

## Change

- Updated `SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan` so the expression range audit tape exposes a generic `sourceValue` field derived from the configured range source column instead of a hardcoded option-name field.
- Reworked the direct skip-scan expression range test and example fixtures from `wp_options` / `option_name` / `option_value` / `autoload` to `app_settings` / `key_name` / `key_value` / `load_policy`.
- Extended `SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest` to guard the skip-scan planner source plus its direct test/example fixtures.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePlannerSkipScanExpressionRangeRecheckTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`
  - Result: `2 test files, 112 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutral*.php`
  - Result: `7 test files, 212 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `1 test files, 6 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLitePlannerSkipScanExpressionRangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLitePlannerSkipScanExpressionRangeRecheckTest.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralDomainSpecificOptionClassesDynamicTest.php`
- `php -l lanes/libsqlite/examples/application-skipscan-expression-range-recheck.php`
- `php lanes/libsqlite/examples/application-skipscan-expression-range-recheck.php`
  - Result: emitted `requires-current-source-range-recheck` with accepted rowids `[1,2]` and rejected rowid `[3]`
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
- `git diff --check -- lanes/libsqlite`

## Dependency Closure

No new support component is needed. The slice reuses the existing expression skip-scan planner and source-neutral guard coverage.

## Non-Overlap

This is source-neutral cleanup only. It does not add upstream corpus admission, dashboard counters, JSON/WAL/VFS/B-tree behavior, or root-suite evidence.

Root harness: not run - isolated micro-slice.
