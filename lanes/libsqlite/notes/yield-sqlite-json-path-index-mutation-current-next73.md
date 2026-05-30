# SQLite JSON path index mutation current-next73

## Behavior

- Adds `column` support to `SQLiteJsonPathIndexedUpdatePlan` updates so JSON
  mutation current/next index maintenance can target a non-`option_value`
  source column, such as a Application staging `payload` column.
- Preserves existing default `option_value` behavior when `column` is omitted.
- Keeps unique-index conflict checks, collation metadata, reverse array path
  keys, JSONB/subtype mutation values, and sequential current-image updates
  working across mixed `payload` and `option_value` indexes.

## Evidence

```text
php -l lanes/libsqlite/src/SQLiteJsonPathIndexedUpdatePlan.php
No syntax errors detected in lanes/libsqlite/src/SQLiteJsonPathIndexedUpdatePlan.php

php -l lanes/libsqlite/tests/SQLiteJsonPathIndexMutationCurrentNext73Test.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteJsonPathIndexMutationCurrentNext73Test.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteJsonPathIndexMutationCurrentNext73Test.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 41 assertions, 0 failures
```

## Application Smoke

```text
php lanes/libsqlite/examples/application-json-path-index-mutation-current-next73.php
```

The smoke reports copied plugin settings where `payload` JSON mutations update
the `idx_plugin_payload_enabled`, `idx_plugin_payload_rank`, and
`idx_plugin_payload_channel` current/next entries while the separate
`option_value` autoload index remains unchanged.

## Non-overlap

This avoids accepted JSONB CHECK optional path/SQL NULL admission, JSON table
visible/hidden constraints, JSON table cursor/source wiring, recursive JSON
SELECT materialization, and prior JSON path indexed `option_value` mutation
coverage. It is specifically the remaining generated/indexed JSON path mutation
case where current/next index maintenance follows an explicitly updated JSON
payload column.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON,
JSONB, JSON subtype, and indexed JSON path planning helpers.
