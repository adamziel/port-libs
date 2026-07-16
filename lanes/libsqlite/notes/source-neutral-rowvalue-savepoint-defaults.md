# source-neutral-src-rowvalue-savepoint-defaults

2026-05-30 isolated lane slice `port-dev-sqlite-neutral-rowvalue`.

## Change

- Replaced owned row-value savepoint defaults from WordPress-shaped names such as `wp_options_rowvalue_*`, `wp_outer_*`, and `wp_inner_*` with generic `app_settings_*` / `app_*` names.
- Replaced row-value window source internals that counted hardcoded `wp_options` rows with a table-neutral `primarySourceRows()` helper.
- Updated direct row-value tests and application examples that asserted the changed default savepoint names.

## Evidence

- `php tools/run-tests.php $(git diff --name-only -- lanes/libsqlite/tests | tr '\n' ' ')`
  - `108 test files, 7093 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php`
  - `1 test files, 3 assertions, 0 failures`
- `php -l` for every changed PHP file
  - all changed PHP files reported no syntax errors
- Changed row-value examples with `--self-test`
  - all changed examples passed their self-tests
- `git diff --check -- lanes/libsqlite`
  - passed

## Dependency Closure

No new support component is needed. The slice is source-neutral naming cleanup over existing native row-value savepoint and RETURNING-window behavior.
