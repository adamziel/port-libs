# source-neutral-src-jsonb-check-current-source-dynamic-20260601T164324Z-0

Base accepted HEAD: `a73a1bf2eb438c0b7d1aaf949b3c1caa3e3707b1`

This source-neutral cleanup extends the JSONB CHECK current-source guard to the
adjacent JSON schema/WAL planner and keeps its direct smoke path generic.

Changed behavior:

- `SQLiteJsonSchemaWalPlan` diagnostics now refer to JSON setting names instead
  of historical option-shaped wording.
- The JSONB/current-source source-neutral guards now scan the JSON schema/WAL
  planner for legacy setting-table vocabulary.
- `SQLiteApplicationJsonSchemaWalCurrentNext43Test.php` and
  `application-json-schema-wal-current-next43.php` use neutral `app_settings`,
  `setting_id`, `key_name`, `key_value`, and `load_policy` fixtures.
- The example smoke now uses the current `inserted_key_names` result key.

Focused verification:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php lanes/libsqlite/tests/SQLiteApplicationJsonSchemaWalCurrentNext43Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - Result: `3 test files, 82 assertions, 0 failures`
- `php -l lanes/libsqlite/src/SQLiteJsonSchemaWalPlan.php`
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralJsonbCheckCurrentSourceDynamicTest.php`
- `php -l lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
- `php -l lanes/libsqlite/tests/SQLiteApplicationJsonSchemaWalCurrentNext43Test.php`
- `php -l lanes/libsqlite/examples/application-json-schema-wal-current-next43.php`
  - Result: no syntax errors in all changed PHP files.
- `php lanes/libsqlite/examples/application-json-schema-wal-current-next43.php --self-test`
  - Result: planned status, one accepted import row, one rejected malformed JSON row, and `inserted_key_names: ["module_json_settings"]`.
- `git diff --check -- lanes/libsqlite`
  - Result: no whitespace errors.

Dependency closure: no new support component is needed; this reuses the existing
JSON schema/WAL planner, key-value WAL import planner, source-neutral guards,
and JSONB CHECK current-source evaluators.

Root harness: not run - isolated micro-slice.
