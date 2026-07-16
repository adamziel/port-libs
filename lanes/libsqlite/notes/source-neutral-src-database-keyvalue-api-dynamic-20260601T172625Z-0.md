# Source-Neutral Database Key-Value API Dynamic

Slice: `source-neutral-src-database-keyvalue-api-dynamic-20260601T172625Z-0`

Base accepted HEAD: `d278a362872b5dd07eba5a9fb5c667433c85ead6`

## Change

- Centralized the generic key-value row source identifiers on `SQLiteKeyValueRow`:
  `app_settings`, `setting_id`, `key_name`, `key_value`, and `load_policy`.
- Rewired `SQLiteDatabase` key-value row insert/replace/read/range/index helpers
  and direct `SQLiteKeyValueRow*` plan serializers to use those neutral
  identifiers instead of repeating string literals through the API surface.
- Added `SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php`, which reflects
  the owned `SQLiteDatabase` key-value methods and dynamically scans the row
  helper files for legacy domain terms.

## Verification

- `php -l` passed for all changed PHP files.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php`
  passed: 1 file / 8 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: 1 file / 7 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  passed: 3 files / 61 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteApplicationCurrentSmokePlanTest.php lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php lanes/libsqlite/tests/SQLiteApplicationSettingsTenantWalCurrentNext42Test.php lanes/libsqlite/tests/SQLiteTenantKeyValueImportSavepointCurrentNext37Test.php`
  passed: 4 files / 237 assertions / 0 failures.
- Expanded key-value-adjacent `SQLiteHeaderTest.php` was attempted and still
  reports the accepted-base 16 known failures already listed in
  `lane-status.json`; it is not counted as a passing focused gate for this
  handoff.

## Dependency Closure

No new support component is needed. The slice reuses native `SQLiteDatabase`
b-tree scans, index lookup/range helpers, WAL import helpers, and key-value row
write plan serializers with generic application settings identifiers.

## Next

Keep the broad 16-failure libsqlite bucket and full-lane memory exhaustion as
the next integration blockers. Additional source-neutral work should target
remaining production-source files outside this key-value API surface.
