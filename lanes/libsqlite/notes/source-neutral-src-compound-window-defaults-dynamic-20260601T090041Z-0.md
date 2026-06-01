# Source-neutral compound/window defaults dynamic cleanup

- Micro-slice: `source-neutral-src-compound-window-defaults-dynamic-20260601T090041Z-0`
- Base accepted HEAD: `d7a19889d5388512c58bffdd0bf40a928a255617`
- Scope: bounded retry-window row-value RETURNING defaults in `SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNextPlan::executePartitionedRetryWindow()` and `executeExcludeCurrentRetryWindow()`, plus their direct next234/next237 tests and application examples.

Changes:

- Replaced the owned row-value retry-window default columns from option/blog terms to `setting_id`, `tenant_id`, and `key_name`.
- Renamed owned window metadata from option-shaped labels to generic key/row-id labels: `window_lag_key_name`, `window_lead_key_name`, `window_frame_row_ids`, `window_peer_key_names_excluding_current`, `window_peer_row_ids_excluding_current`, and summary `row_ids`/`peer_row_ids`.
- Converted the direct next234/next237 tests and examples from `wp_options`/`wp_optionmeta` rows to generic `app_settings`/`app_setting_targets` rows while preserving the same rollback/retry/window assertions.
- Expanded the existing compound/window source-neutral guard to cover the owned partitioned and EXCLUDE CURRENT row-value window source segments.

Verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext234Test.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext237Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` passed: 4 files, 152 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralCompoundWindowDefaultsDynamicTest.php` passed: 1 file, 3 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext234Test.php` passed: 1 file, 73 assertions, 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningWindowCurrentSourceNext237Test.php` passed: 1 file, 71 assertions, 0 failures.
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next234.php --self-test` passed.
- `php lanes/libsqlite/examples/application-rowvalue-returning-window-current-source-next237.php --self-test` passed.
- `php -l` passed for changed PHP source, tests, and examples.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:

- No new support component is needed. This reuses the existing row-value UPDATE/DELETE RETURNING executor, savepoint rollback/retry flow, and partitioned window materialization.

Exclusions:

- Remaining legacy defaults elsewhere in the large row-value/window current-source plan are intentionally left for later bounded source-neutral slices.
- Root harness: not run - isolated micro-slice.
