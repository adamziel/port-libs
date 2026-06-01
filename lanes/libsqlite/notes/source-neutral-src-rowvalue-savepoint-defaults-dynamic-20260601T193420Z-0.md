# source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T193420Z-0

Micro-slice: `source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T193420Z-0`
Base accepted HEAD: `17d7fcad81b2831d9e7a6affe5ec8cee04f52d4f`

## Delta

- Neutralized the remaining `wp.` release/cursor defaults in `SQLiteRowValueUpdateDeleteReturningSavepointPlan::executeReleaseFollowupReadSavepoint()` to generic `app.rowvalue.*` tokens.
- Renamed the distinct tuple savepoint production dependency label from an optionmeta-shaped application id to `application-rowvalue-distinct-setting-targets-savepoint`.
- Converted the directly coupled release-followup and distinct-tuple row-value savepoint tests/examples from `wp_options`/`wp_optionmeta`, `option_*`, `blog_id`, and `autoload` fixtures to `app_settings` / `app_setting_targets` with `setting_id`, `tenant_id`, `key_name`, `key_value`, and `load_policy`.
- Extended the existing no-domain/source-neutral row-value guard to include the consolidated row-value savepoint source file and reject `wp.` / optionmeta-shaped source terms.

## Verification

- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueReleaseFollowupReadSavepointTest.php` passed.
- `php -l lanes/libsqlite/tests/SQLiteRowValueDistinctTupleSavepointTest.php` passed.
- `php -l lanes/libsqlite/examples/application-rowvalue-release-followup-read-savepoint.php` passed.
- `php -l lanes/libsqlite/examples/application-rowvalue-distinct-tuple-savepoint.php` passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueReleaseFollowupReadSavepointTest.php lanes/libsqlite/tests/SQLiteRowValueDistinctTupleSavepointTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 4 test files, 171 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-rowvalue-release-followup-read-savepoint.php` passed.
- `php lanes/libsqlite/examples/application-rowvalue-distinct-tuple-savepoint.php --self-test` passed.
- `git diff --check -- lanes/libsqlite` passed.

## Dependency Closure

No new support component is needed. This reuses the existing row-value UPDATE/DELETE RETURNING executor, savepoint current-source images, DISTINCT tuple-source parsing, and row-id resolver.

## Exclusions

- This is source-neutral production cleanup only. No `phpPass` or mapped-coverage counter change is claimed.
- Broader legacy row-value fixtures outside the two directly coupled examples/tests are intentionally left for separate bounded source-neutral slices.
- Root harness: not run - isolated micro-slice.
