# Source-Neutral JSON Path Option Defaults

Micro-slice: `source-neutral-src-option-table-defaults-dynamic-20260601T075712Z-0`

## Scope

- Neutralized the JSON path strict/lax current-source diagnostics in `SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNextPlan` from `option_id` / `option_name` / `option_value` to `setting_id` / `key_name` / `key_value`.
- Neutralized the default JSON column and row identity in `SQLiteJsonPathIndexedUpdatePlan` from option-shaped defaults to `key_value` and `setting_id`.
- Updated directly coupled JSON path tests and generic application examples to use `module_*`, `setting_id`, `key_name`, `key_value`, and `load_policy` fixtures.
- Added source-neutral dynamic guard coverage for both owned JSON path source files.

## Verification

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonPathStrictLaxNegativeIndexCurrentSourceNext110Test.php lanes/libsqlite/tests/SQLiteJsonPathIndexedUpdateTest.php lanes/libsqlite/tests/SQLiteJsonPathIndexMutationCurrentNext73Test.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteRealUpstreamJson102IndexedMutationDynamicTest.php`
  - `5 test files, 15222 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `1 test files, 5 assertions, 0 failures`
- `php -l` on changed PHP files
  - all changed PHP files reported no syntax errors
- Example smokes:
  - `php lanes/libsqlite/examples/application-json-path-strict-lax-negative-index-current-source-next110.php`
  - `php lanes/libsqlite/examples/application-json-path-indexed-update.php`
  - `php lanes/libsqlite/examples/application-json-path-index-mutation-current-next73.php`
- `git diff --check -- lanes/libsqlite`
  - passed

## Dependency Closure

No new support component is needed. This slice reuses existing JSON path inspection, JSON mutation, JSON extract, JSONB, and subtype helpers while keeping the behavior and upstream indexed-mutation coverage intact.

## Exclusions

Remaining source-neutral cleanup outside these two JSON path helpers is left for follow-up slices. The root harness was not run because this is an isolated source-neutral micro-slice.
