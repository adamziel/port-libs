# source-neutral-src-jsonb-check-current-source-dynamic-20260601T035015Z-0

Base accepted HEAD: `bf75a27f708d456a2f08c9c540bce1189ab451a6`

Source-neutral cleanup for the bounded JSONB current-source operator surface.
`SQLiteJsonbCheckCurrentNextPlan` was already neutral in this worktree, so this
slice removed the remaining adjacent JSONB current-source production defaults
that still used option-shaped row names.

Changed behavior:

- `SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan::compare()` now
  defaults to `key_value` and `setting_id` instead of option-shaped JSON and
  identity columns.
- `SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan::plan()` now defaults
  to `setting_id` for row identity.
- Direct tests and examples now use `app_settings`, `setting_id`, `key_name`,
  `key_value`, `load_policy`, and feature JSON payloads while preserving the
  same malformed JSONB, JSON path operator, SELECT executor, and generated-index
  behavior assertions.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteJsonbPathOperatorMalformedCurrentSourceNextPlan.php
php -l lanes/libsqlite/src/SQLiteJsonbGeneratedIndexOperatorCurrentSourceNextPlan.php
php -l lanes/libsqlite/tests/SQLiteJsonbPathOperatorMalformedCurrentSourceNext106Test.php
php -l lanes/libsqlite/tests/SQLiteJsonbGeneratedIndexOperatorCurrentSourceNext107Test.php
php -l lanes/libsqlite/examples/application-jsonb-path-operator-malformed-current-source-next106.php
php -l lanes/libsqlite/examples/application-jsonb-generated-index-operator-current-source-next107.php
```

All lint commands reported no syntax errors.

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbPathOperatorMalformedCurrentSourceNext106Test.php lanes/libsqlite/tests/SQLiteJsonbGeneratedIndexOperatorCurrentSourceNext107Test.php
```

Result: `2 test files, 140 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php
```

Result: `2 test files, 5 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteSourceNeutralOptionTableDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteSourceNeutralTriggerUpsertViewDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteSourceNeutralRowValueSavepointDefaultsDynamicTest.php lanes/libsqlite/tests/SQLiteTenantSavepointWalSourceNeutralTest.php
```

Result: `4 test files, 54 assertions, 0 failures`.

```text
php lanes/libsqlite/examples/application-jsonb-path-operator-malformed-current-source-next106.php
php lanes/libsqlite/examples/application-jsonb-generated-index-operator-current-source-next107.php
git diff --check -- lanes/libsqlite
```

Both example smokes emitted generic `app_settings` JSONB plans and
`git diff --check` passed.

Dependency closure: no new support component is needed. This reuses the
existing JSONB, JSON path, generated-index, and SELECT executor helpers.

Root harness: not run - isolated micro-slice.
