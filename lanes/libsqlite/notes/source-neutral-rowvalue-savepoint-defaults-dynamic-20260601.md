# Source-neutral row-value savepoint defaults dynamic

Slice: `source-neutral-src-rowvalue-savepoint-defaults-dynamic-20260601T084921Z-0`

Base accepted HEAD: `6c5f68290192c5bf57e0f3c2cca80b604bf38511`

Changes:

- Neutralized the consolidated `SQLiteRowValueUpdateDeleteReturningSavepointPlan` row-id defaults from `option_id` to `setting_id`.
- Reused `SQLiteRowIdColumn` to resolve actual identifier columns for source-row summaries, including single-row tables after retry deletes.
- Extended the source-neutral row-value savepoint guard to include the consolidated row-value savepoint source file.

Verification:

- `php -l lanes/libsqlite/src/SQLiteRowIdColumn.php`
- `php -l lanes/libsqlite/src/SQLiteRowValueUpdateDeleteReturningSavepointPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteReturningSavepointCurrentSourceNext176Test.php` -> `2 test files, 81 assertions, 0 failures`
- `php tools/run-tests.php $(rg -l 'SQLiteRowValueUpdateDeleteReturningSavepointPlan::' lanes/libsqlite/tests | sort)` -> `70 test files, 4568 assertions, 0 failures`

Dependency closure:

No new support component is needed. This reuses the existing native row-value UPDATE/DELETE RETURNING, savepoint current-source, and generic row-id resolver components.

Dashboard impact:

No `phpPass` or mapped-coverage counter change claimed. This is source-neutral production cleanup with focused regression coverage.
