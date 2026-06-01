# source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T220509Z-0

## Scope

- Production row-value/savepoint source scan was already clean for the forbidden `wp_`, `wp_options`, `option_*`, `blog_id`, and `autoload` terms on this accepted base.
- Neutralized the directly coupled ordered-subquery row-value savepoint retry fixture pair from old option/table terminology to generic `app_settings` and `app_setting_targets`.
- Replaced fixture columns with `setting_id`, `tenant_id`, `key_name`, `key_value`, and `load_policy`, preserving the same ordered subquery, LIMIT/OFFSET, rollback-to-savepoint, retry, malformed input, and dependency assertions.
- Updated `application-rowvalue-ordered-subquery-savepoint-retry.php` to use the repo bootstrap, fixing the missing `SQLiteRowIdColumn` load, and added an explicit `--self-test` branch.
- Extended both the source-neutral row-value guard and the no-domain API guard over the neutralized ordered-subquery test/example pair.

## Verification

- `php -l lanes/libsqlite/tests/SQLiteRowValueOrderedSubquerySavepointRetryTest.php`
- `php -l lanes/libsqlite/examples/application-rowvalue-ordered-subquery-savepoint-retry.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueOrderedSubquerySavepointRetryTest.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `3 test files, 110 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-rowvalue-ordered-subquery-savepoint-retry.php --self-test`
  - Result: passed.

## Dependency Closure

No new support component is needed. This reuses the existing native row-value UPDATE/DELETE RETURNING executor, ordered subquery tuple handling, row-id resolution, and savepoint retry planner.

## Non-Overlap

Source-neutral cleanup only. No upstream PASS-line, mapped-coverage, or lane-status counter movement is claimed. Broader historical row-value fixtures are left for separate bounded source-neutral slices.

Root harness: not run - isolated micro-slice.
