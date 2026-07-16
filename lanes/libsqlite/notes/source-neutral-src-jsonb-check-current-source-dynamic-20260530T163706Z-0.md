# source-neutral-src-jsonb-check-current-source-dynamic-20260530T163706Z-0

Source-neutral cleanup for `SQLiteJsonbCheckCurrentNextPlan`.

- Production defaults now use generic `key_value` for JSONB mutation payloads.
- Row identity fallback now uses `setting_id` instead of option-shaped IDs.
- Direct JSONB CHECK tests and examples now use `app_settings`, `setting_id`,
  `key_name`, `key_value`, and `load_policy`.
- Behavior is unchanged: the focused tests still cover JSONB CHECK admission,
  nullable path semantics, OR/NOT grouping, NOT IN, and NOT BETWEEN current/next
  behavior.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteJsonbCheckCurrentNextPlan.php
php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php
php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php
php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php
php -l lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php
php -l lanes/libsqlite/examples/application-jsonb-check-current-next64.php
php -l lanes/libsqlite/examples/application-jsonb-check-current-next67.php
php -l lanes/libsqlite/examples/application-jsonb-check-current-next68.php
php -l lanes/libsqlite/examples/application-jsonb-check-current-next69.php
No syntax errors detected.

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php
4 test files, 315 assertions, 0 failures

php lanes/libsqlite/examples/application-jsonb-check-current-next64.php
php lanes/libsqlite/examples/application-jsonb-check-current-next67.php
php lanes/libsqlite/examples/application-jsonb-check-current-next68.php
php lanes/libsqlite/examples/application-jsonb-check-current-next69.php
All four example smokes emitted generic application JSONB CHECK plans.
```

Dependency closure: no new support component is needed; this reuses the existing
JSONB, JSON mutation, and CHECK-evaluation helpers.
