# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T214945Z-0

Base accepted HEAD: `c7ca7ac45660966d9eecf84ad3eaffea66691f11`

## Scope

This slice ports one upstream-backed dynamic LIMIT/OFFSET behavior cluster for row-value UPDATE/DELETE execution: `unhex()` BLOB scalar handling inside LIMIT expressions.

Source truth:

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/unhex.test` for `unhex()` one-arg/two-arg decoding, ignored characters, empty BLOB, malformed input returning NULL, and arity errors.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`, `e_update.test`, and `e_delete.test` for LIMIT/OFFSET expression behavior on UPDATE/DELETE.
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for row-value tuple-source DML shape.

## Behavior

`SQLiteUpdateDeleteReturningSql` now admits `unhex()` in dynamic UPDATE/DELETE LIMIT and OFFSET expressions when its BLOB result is consumed by byte-aware scalar wrappers:

- `length(unhex(...))`
- `octet_length(unhex(...))`
- `hex(unhex(...))`
- `typeof(unhex(...))`

Direct BLOB LIMIT results remain rejected, and malformed `unhex()` cases that return NULL still fail integer LIMIT coercion. The focused tests cover UPDATE row selection, DELETE tuple-subquery selection, two-argument ignored-character decoding, lowercase/uppercase inputs, empty BLOBs, direct BLOB rejection, odd digits, invalid hex digits, NULL arguments, and invalid arity.

## Red-First Evidence

Before the source change, this accepted-head probe failed because `unhex()` was not available to the LIMIT expression evaluator:

```text
php -r 'require "tools/bootstrap.php"; try { var_export(PortLibs\LibSqlite\SQLiteUpdateDeleteReturningSql::parse("DELETE FROM app_settings RETURNING setting_id LIMIT hex(unhex('\''3032'\''))")); echo "\n"; } catch (Throwable $e) { echo get_class($e).": ".$e->getMessage()."\n"; }'
InvalidArgumentException: SQLite UPDATE/DELETE LIMIT expressions must evaluate to an integer
```

Baseline focused parity file before the change:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
1 test files, 19297 assertions, 0 failures
```

## Verification

Focused parity after the change:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
1 test files, 19947 assertions, 0 failures
```

Focused assertion delta: `+650`.
Focused PASS-case delta: `+109`.

Adjacent row-value/update-delete dynamic family:

```text
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitBindParameterDynamicTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicMatrixTest.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php lanes/libsqlite/tests/SQLiteUpdateDeleteReturningSqlTest.php
5 test files, 20901 assertions, 0 failures
```

Syntax, source-neutral guard, and diff hygiene:

```text
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
No syntax errors detected in lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php

php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php

php -r 'json_decode(file_get_contents("lanes/libsqlite/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "valid json\n";'
valid json

php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
1 test files, 3 assertions, 0 failures

git diff --check -- lanes/libsqlite
no output
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This patch does not repeat prior dynamic LIMIT clusters for arithmetic, CAST, CASE, date/time wrappers, concat, JSON/JSONB scalars, `iif()`/`if()`, `zeroblob()`, `randomblob()`, bind parameters, LIKE/GLOB operators, or upstream runner metadata. It only adds `unhex()` BLOB scalar parity for generic row-value UPDATE/DELETE LIMIT/OFFSET expression evaluation.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP components: `SQLiteCoreScalarFunction::unhex()`, `SQLiteBlobValue`, `SQLiteUpdateDeleteReturningSql` parsing/execution, and the existing row-value tuple-source test fixtures.
