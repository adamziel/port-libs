# Source-neutral database key-value API dynamic cleanup

Slice: `source-neutral-src-database-keyvalue-api-dynamic-20260601T115423Z-0`

Scope:
- Renamed the directly coupled key-value example entrypoints so their filenames use `setting` language instead of the historical `option` wording.
- Extended `SQLiteNoDomainSpecificApiTest.php` to guard key-value fixture filenames against `wp`, `blog`, `option`, and `autoload` naming regressions.
- Updated lane-local notes that referenced the renamed example paths.

Verification:
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php -l lanes/libsqlite/examples/application-composite-indexed-generated-setting-insert-plan.php`
- `php -l lanes/libsqlite/examples/application-index-split-setting-replacement-plan.php`
- `php lanes/libsqlite/examples/application-composite-indexed-generated-setting-insert-plan.php`
- `php lanes/libsqlite/examples/application-index-split-setting-replacement-plan.php`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` -> `1 test files, 6 assertions, 0 failures`
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteApplicationCurrentSmokePlanTest.php lanes/libsqlite/tests/SQLiteApplicationJsonUpsertMigrationCurrentNext27Test.php lanes/libsqlite/tests/SQLiteApplicationSettingsImportWalCurrentNext34Test.php lanes/libsqlite/tests/SQLiteApplicationSettingsTenantWalCurrentNext42Test.php lanes/libsqlite/tests/SQLiteMalformedTextCurrentNext70Test.php lanes/libsqlite/tests/SQLiteTenantKeyValueImportSavepointCurrentNext37Test.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinityCurrentSourceNext85Test.php lanes/libsqlite/tests/SQLiteUtf16CollationAffinitySourceSwitchCurrentSourceNext100Test.php` -> `9 test files, 454 assertions, 0 failures`

Dependency closure:
- No new support component is needed. This reuses the existing `SQLiteDatabase` key-value insert/replace APIs, `SQLiteKeyValueRow` mapping, and existing WAL/tenant key-value helpers.

Root harness:
- Not run - isolated micro-slice.

Next:
- Continue bounded source-neutral cleanup for directly owned key-value fixture surfaces if new lower-case legacy names appear in the API guard.
