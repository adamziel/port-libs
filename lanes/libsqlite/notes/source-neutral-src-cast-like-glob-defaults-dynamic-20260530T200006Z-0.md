# Source-Neutral CAST/LIKE/GLOB Defaults Dynamic Slice

Base accepted HEAD: `688b5b5b02ee30d2a82f4468b5b909f17254ae0e`

Owned production source:

- `lanes/libsqlite/src/SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan.php`

Source-neutral cleanup:

- Replaced the production rowid fallback key from the legacy option-shaped id to `setting_id`.
- Reworded the scalar-value diagnostic from option values to setting values.
- Updated the direct LIKE/GLOB affinity range test and application example from legacy domain-shaped fixture rows to `setting_id`, `key_name`, and `key_value`.
- Preserved the observable SQLite behavior assertions for LIKE range bounds, GLOB range bounds, affinity coercion, cursor invalidation reasons, UTF-16 byte encoding, source/schema cookie invalidation, and malformed input rejection.

Verification:

- `php -l lanes/libsqlite/src/SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNextPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNext104Test.php`
- `php -l lanes/libsqlite/examples/application-like-glob-affinity-range-current-source-next.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteEncodingLikeGlobAffinityRangeCurrentSourceNext104Test.php` -> `1 test files, 57 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 3 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-like-glob-affinity-range-current-source-next.php --self-test`

Dependency closure:

- No new support component is needed. This slice reuses existing native PHP LIKE/GLOB, affinity, collation, and UTF-16 encoding helpers.

Next:

- Continue neutralizing remaining older domain-shaped production source files in bounded groups; this slice intentionally does not touch unrelated trigger, planner, JSON, or PRAGMA families.
