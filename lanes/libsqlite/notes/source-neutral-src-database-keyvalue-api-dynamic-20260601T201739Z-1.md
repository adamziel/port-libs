# source-neutral-src-database-keyvalue-api-dynamic-20260601T201739Z-1

## Scope

- Renamed the two directly coupled key-value indexed lookup examples:
  - `application-json-option-value-list.php` -> `application-json-setting-value-list.php`
  - `application-option-value-integer-list.php` -> `application-setting-value-integer-list.php`
- Switched their expression-index probes from `wp_options` / `option_value` to `app_settings` / `key_value`.
- Renamed user-facing output keys from option-shaped result names to generic `settings` and `appSettings...IndexRootPage` keys.
- Added local `--self-test` fixtures that assemble minimal SQLite page images with native PHP b-tree/index encoders.
- Extended `SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php` and `SQLiteNoDomainSpecificApiTest.php` so the renamed examples stay covered by the source-neutral guards.

## Verification

- `php -l lanes/libsqlite/examples/application-json-setting-value-list.php`: passed.
- `php -l lanes/libsqlite/examples/application-setting-value-integer-list.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: passed.
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php`: passed.
- `php lanes/libsqlite/examples/application-json-setting-value-list.php --self-test`: passed.
- `php lanes/libsqlite/examples/application-setting-value-integer-list.php --self-test`: passed.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralDatabaseKeyValueApiDynamicTest.php`: 1 file / 9 assertions / 0 failures.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`: 1 file / 8 assertions / 0 failures.
- `php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status.json valid\n";'`: passed.
- `git diff --check -- lanes/libsqlite`: passed.

## Dependency Closure

No new support component is needed. The example self-tests reuse existing native SQLite record, table leaf page, index leaf page, and key-value row lookup helpers.

## Non-Overlap

This is source-neutral cleanup only. It does not add upstream PASS rows, does not change `phpPass` or mapped coverage, and does not touch the accepted JSON subtype, UPSERT RETURNING, WAL/VFS, B-tree, or broad release-parity failure surfaces.
