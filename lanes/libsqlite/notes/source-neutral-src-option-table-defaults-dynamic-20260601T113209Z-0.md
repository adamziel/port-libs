# source-neutral-src-option-table-defaults-dynamic-20260601T113209Z-0

Status: ready for integration.

Source-neutral cleanup:

- Replaced trigger foreign-key returning default row-id handling with generic `setting_id` defaults in `SQLiteTriggerForeignKeyReturningPlan` and `SQLiteTriggerReturningForeignKeySavepointPlan`.
- Updated direct legacy trigger fixtures to pass `option_id` explicitly where the existing coverage intentionally preserves historical option-shaped rows, and neutralized the coupled example to use generic `setting_id` application settings rows.
- Replaced the UTF-16 source-pattern helper contract from `option_id` / `option_value_bytes` to `setting_id` / `key_value_bytes`, including diagnostic output and invalidation reasons.
- Updated the source-neutral guard and direct UTF-16 source-pattern test/example to exercise generic application settings rows.

Verification:

- `php -l lanes/libsqlite/src/SQLiteTriggerForeignKeyReturningPlan.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/src/SQLiteTriggerReturningForeignKeySavepointPlan.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/src/SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/tests/SQLiteTriggerForeignKeyReturningCurrentNext33Test.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/tests/SQLiteTriggerReturningForeignKeySavepointCurrentNext54Test.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Test.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/examples/application-trigger-returning-foreignkey-savepoint-current-next54.php`
  - no syntax errors detected
- `php -l lanes/libsqlite/examples/application-utf16-source-pattern-like-current-source-next202.php`
  - no syntax errors detected
- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteUtf16NocaseLikeRtrimCurrentSourceNext202Test.php lanes/libsqlite/tests/SQLiteTriggerForeignKeyReturningCurrentNext33Test.php lanes/libsqlite/tests/SQLiteTriggerReturningForeignKeySavepointCurrentNext54Test.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php`
  - `5 test files, 261 assertions, 0 failures`
- `php lanes/libsqlite/examples/application-trigger-returning-foreignkey-savepoint-current-next54.php --self-test`
  - `application-trigger-returning-foreignkey-savepoint-current-next54 self-test passed`
- `php lanes/libsqlite/examples/application-utf16-source-pattern-like-current-source-next202.php`
  - emitted generic `rhs-pattern-source-setting-id-changed` source-pattern invalidation evidence
- `git diff --check -- lanes/libsqlite`
  - passed

Dependency closure: no new support component is needed. This reuses the existing trigger foreign-key returning, savepoint rollback preview, UTF-16 decode, source-row pattern extraction, ASCII NOCASE LIKE prefix range, and RTRIM expression-key helpers.

Root harness: not run - isolated micro-slice.
