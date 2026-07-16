# Source-Neutral Option Table Defaults Dynamic

Micro-slice: `source-neutral-src-option-table-defaults-dynamic-20260601T132316Z-0`

Scope:
- Neutralized `SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan()` byte-order diagnostics from `option_name` expression text to generic `key_name`.
- Migrated the directly coupled current-source next157 UTF-16 test/example rows from `option_id` and `option_name_bytes` to `setting_id` and `key_name_bytes`.
- Added a targeted source-neutral guard that scans the `keyValueRowKeyPlan()` source block for legacy option/table defaults.

Evidence:
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext157Test.php` => `1 test files, 94 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php` => `1 test files, 45 assertions, 0 failures`.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `1 test files, 6 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-utf16-nocase-like-rtrim-current-source-next157.php --self-test` emitted the generic application settings summary and passed its assertions.
- `php -l` passed for the changed source, test, and example PHP files.
- `git diff --check -- lanes/libsqlite` passed.

Dependency closure:
- No new support component needed; this reuses the existing UTF-16 decode, LIKE prefix planning, RTRIM key normalization, and current-source byte-order invalidation helpers.

Exclusions:
- The broader UTF-16 plan file still contains older option-shaped corpus blocks outside this bounded next157/default-helper slice; those ranges are covered by separate source-neutral guard tests and should be cleaned in later bounded batches.
