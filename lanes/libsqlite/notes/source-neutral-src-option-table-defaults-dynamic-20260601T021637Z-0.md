# Source-neutral option table defaults

## Scope

Neutralized a bounded set of production-source option-table defaults and traces:

- `SQLitePragmaIntegrityPartialIndexCurrentSourceNext` now defaults to `app_settings`, `idx_app_settings_partial`, and `setting_id` row identifiers.
- `SQLiteAttachTempWalSchemaTriggerPlan` now defaults prepared table tracking to `app_settings`.
- Compound current/next table traces now read `app_settings` and `key_name` instead of the historical option table shape.

Direct tests and examples were updated to use `setting_id`, `key_name`, `key_value`, `tenant_id`, and `load_policy` terminology. No compatibility alias or legacy table map was added.

## Verification

- `php -l` on changed PHP files => no syntax errors.
- `php tools/run-tests.php lanes/libsqlite/tests/SQLitePragmaIntegrityPartialIndexCurrentSourceNextTest.php lanes/libsqlite/tests/SQLiteCompoundWindowExceptOrderCurrentSourceTest.php lanes/libsqlite/tests/SQLiteCompoundIntersectLagLeadRecursiveLimitCurrentSourceTest.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php` => `5 test files, 532 assertions, 0 failures`.
- `php lanes/libsqlite/examples/application-pragma-integrity-partial-index-current-source-next.php --self-test` => passed.
- `php lanes/libsqlite/examples/application-compound-window-except-order-current-source.php --self-test` => passed.
- `php lanes/libsqlite/examples/application-compound-intersect-lag-lead-recursive-limit-current-source.php` => passed.

## Dependency Closure

No new support component is needed. This cleanup preserves existing native PHP SELECT, PRAGMA integrity, WAL schema-cache, and compound/window behavior while removing one production-source option-table default surface.
