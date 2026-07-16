# Source-Neutral JSONB CHECK Dynamic Row Identity

Base accepted HEAD: `6a6cf1aff10d18a35ed78eace2a787cb40f2b02d`

This source-neutral slice keeps the JSONB CHECK current/next planner generic by
adding a neutral `rowidColumn` option beside the existing `jsonColumn` option.
Callers can now run JSONB CHECK admission over application tables keyed by
source-specific columns such as `tenant_setting_key` without relying on
historical option-shaped row names.

Changed behavior:

- `SQLiteJsonbCheckCurrentNextPlan::plan()` validates and honors
  `rowidColumn` when indexing current rows, reporting current row ids, and
  assigning candidate INSERT/UPDATE row ids.
- Existing neutral fallback identity columns remain supported for compatibility:
  `rowid`, `setting_id`, and `id`.
- Invalid row identity option names are rejected through the same identifier
  guard used for dynamic JSON column names.

Focused verification:

- `php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php`
  - Result: `4 test files, 326 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php`
  - Result: `2 test files, 4 assertions, 0 failures`
- `git diff --check -- lanes/libsqlite`

Dependency closure: no new support component is needed. The slice reuses the
existing JSONB, JSON mutation, and CHECK-expression evaluators.

Root harness: not run - isolated micro-slice.
