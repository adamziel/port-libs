# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T144708Z-0

## Status

Implemented JSON scalar expression parity for dynamic `UPDATE`/`DELETE`
`LIMIT` and `OFFSET` evaluation in `SQLiteUpdateDeleteReturningSql`.

This is a generic app-setting behavior slice. It does not add WordPress-named
classes, methods, examples, fixture APIs, or source defaults.

## Source Truth

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
  - `json_extract`, `json_array_length`, `json_valid`,
    `json_error_position`, `json_type`, and `json_quote` scalar behavior.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/json105.test`
  - reverse array index path behavior such as `$[#-1]`.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
  - expression-driven LIMIT/OFFSET windows.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
  - row-value tuple subquery membership for DML.

## Focused Delta

Baseline before edits:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
1 test files, 17530 assertions, 0 failures
```

After edits:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
1 test files, 18236 assertions, 0 failures
```

Focused assertion delta: `+706`.

## Verification

```text
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php

php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
1 test files, 18236 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php
1 test files, 625 assertions, 0 failures

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
1 test files, 3 assertions, 0 failures

git diff --check -- lanes/libsqlite
passed
```

The legacy prompt-named guard
`lanes/libsqlite/tests/SQLiteNoWordPressSpecificApiTest.php` is not present in
this worktree; `SQLiteNoDomainSpecificApiTest.php` is the available guard and
passed.

## Behavior Covered

- Dynamic outer `UPDATE ... ORDER BY ... LIMIT <json scalar> OFFSET <json scalar>`
  windows over generic `app_settings` rows.
- Dynamic row-value `DELETE ... WHERE (tenant_id, key_name) IN (SELECT ...
  LIMIT <json scalar> OFFSET <json scalar>)` tuple windows.
- Parsed integer LIMIT/OFFSET results from `json_extract`,
  `json_array_length`, `json_valid`, `json_error_position`, `json_type`
  predicates, `json_quote`, and reverse array paths.
- Rejection of missing paths, invalid JSON input to extract, text-valued JSON
  limits, NULL JSON validity results, text-valued JSON quotes, bad arity, and
  non-text JSON path arguments.

## Non-Overlap

This slice avoids accepted dynamic LIMIT clusters for arithmetic, casts,
booleans, NULL placement, string functions, date/time, math, LIKE/GLOB,
concat, introspection scalars, SELECT SQL text, JSON table cursor/source
wiring, and JSON visible/hidden constraint pushdown. The only new executor
surface is JSON scalar evaluation inside DML LIMIT/OFFSET expressions.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP JSON
helpers: `SQLiteJsonExtract`, `SQLiteJsonInspection`, `SQLiteJsonValidity`,
`SQLiteJsonErrorPosition`, and `SQLiteJsonQuote`.
