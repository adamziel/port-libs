# source-neutral-src-jsonb-check-current-source-dynamic-20260601T053527Z-0

Base accepted HEAD: `663e16b4022673e2529b925ce20b45f0a578189e`

This source-neutral cleanup keeps the JSONB generated partial UPSERT helper in
the JSONB current-source family generic. Earlier JSONB CHECK cleanup had
already neutralized `SQLiteJsonbCheckCurrentNextPlan`, so this slice removes
the remaining adjacent production defaults in
`SQLiteJsonbGeneratedPartialUpsertPlan`.

Changed behavior:

- `SQLiteJsonbGeneratedPartialUpsertPlan::plan()` now defaults to
  `key_name`, `key_value`, and `load_policy` instead of historical
  option/load-policy-shaped columns.
- The helper validates neutral dynamic `keyColumn`, `jsonColumn`, and
  `copyColumns` options and uses those columns for match routing, JSONB
  mutation input, and incoming metadata propagation.
- The direct test and example now use generic `app_settings` fixtures while
  preserving the same generated-column partial-index UPSERT behavior.

Verification:

```text
php -l lanes/libsqlite/src/SQLiteJsonbGeneratedPartialUpsertPlan.php
php -l lanes/libsqlite/tests/SQLiteJsonbGeneratedPartialUpsertCurrentNext49Test.php
php -l lanes/libsqlite/examples/application-jsonb-generated-partial-upsert-current-next49.php
```

Result: all three files reported no syntax errors.

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbGeneratedPartialUpsertCurrentNext49Test.php
```

Result: `1 test files, 59 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext64Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext67Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext68Test.php lanes/libsqlite/tests/SQLiteJsonbCheckCurrentNext69Test.php lanes/libsqlite/tests/SQLiteJsonbGeneratedPartialUpsertCurrentNext49Test.php
```

Result: `5 test files, 390 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php lanes/libsqlite/tests/SQLiteEncodingSourceNeutralDefaultsTest.php
```

Result: `2 test files, 6 assertions, 0 failures`.

```text
php lanes/libsqlite/examples/application-jsonb-generated-partial-upsert-current-next49.php
```

Result: emitted generic `app_settings` UPSERT routing with `changes: 3`.

Dependency closure: no new support component is needed. This reuses the
existing JSONB mutation, JSON extraction, generated-column, and partial-index
helpers.

Root harness: not run - isolated micro-slice.
