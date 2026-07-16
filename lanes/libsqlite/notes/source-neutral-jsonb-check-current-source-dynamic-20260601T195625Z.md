# Source-neutral JSONB CHECK dynamic mutation column

Base accepted HEAD: `1d41b846adc61aa23aecab0fa6f70bcf0975562b`

Owned slice: `source-neutral-src-jsonb-check-current-source-dynamic-20260601T195625Z-0`

Production change:

- `SQLiteJsonbCheckCurrentNextPlan` now validates per-mutation JSON column overrides with the same neutral identifier guard used by the public JSON column option.
- This keeps dynamic current-source JSONB CHECK mutation input on generic SQLite column names and rejects malformed override names before CHECK admission.

Red-first evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` failed before the source edit because `payload-jsonb` was silently accepted as a mutation column.

Verification after fix:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - `1 test files, 23 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteJsonbGeneratedCheckIndexCurrentNext54Test.php` - `5 test files, 389 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` - `1 test files, 8 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php` - no syntax errors
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php` - no syntax errors
- `git diff --check -- lanes/libsqlite` - passed

Dependency closure: no new support component needed; this reuses existing JSONB mutation, CHECK evaluation, and neutral identifier validation.
