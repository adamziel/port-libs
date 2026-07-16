# Row-Value UPDATE/DELETE LIMIT Dynamic EXISTS Parity

Micro-slice: `full-run-parity-rowvalue-update-delete-limit-dynamic-20260601T210858Z-0`

Base accepted HEAD: `a741eea1b44d6a0e89ff8e144d4e32e5b55a9f86`

## Scope

- Ported a bounded upstream SQLite expression behavior into the row-value `UPDATE`/`DELETE` dynamic `LIMIT`/`OFFSET` evaluator.
- Source truth:
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_expr.test`
  - `e_expr-19.2.*`: `EXISTS ( SELECT ... )` returns 1 when the no-FROM SELECT produces a row, including `SELECT NULL` and multi-column projections.
  - `e_expr-19.3.*`: `EXISTS ( SELECT ... WHERE false-or-null )` returns 0 when the no-FROM SELECT produces no row.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/e_delete.test` for `UPDATE_DELETE_LIMIT` expression admission.
  - `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test` for row-value tuple subquery selection.

## Behavior

- `SQLiteUpdateDeleteReturningSql` now evaluates whole `EXISTS(SELECT ...)` expressions in dynamic `LIMIT`/`OFFSET` when the subquery is a no-FROM constant SELECT.
- The no-FROM EXISTS path honors a top-level `WHERE` clause using SQLite truthiness:
  - `EXISTS(SELECT 1)` -> 1
  - `EXISTS(SELECT NULL)` -> 1
  - `EXISTS(SELECT 1 WHERE 0)` -> 0
  - `EXISTS(SELECT 1 WHERE NULL)` -> 0
  - `NOT EXISTS(SELECT 1 WHERE 0)` -> 1
- FROM-backed EXISTS subqueries remain rejected in this bounded evaluator because table context is not wired through dynamic `LIMIT` expression parsing.
- Also tightened word-operator comparison splitting so `EXISTS(...) + n` is not misparsed as an outer `IS` comparison inside the `EXISTS` token.

## Red-First Evidence

Command before the source edit:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitExistsDynamicTest.php
```

Result before the source edit:

```text
1 test files, 38 assertions, 73 failures
```

Representative failing behavior before the fix:

- `EXISTS(SELECT 1 WHERE 0)` parsed as `1`.
- `NOT EXISTS(SELECT 1 WHERE 0)` parsed as `0`.
- Arithmetic expressions around `EXISTS(...)` failed with `SQLite UPDATE/DELETE LIMIT arithmetic terms must be numeric`.

## Verification

Passing focused behavior command after the fix:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitExistsDynamicTest.php
```

Result:

```text
1 test files, 411 assertions, 0 failures
```

Focused family and guard commands after the fix:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimit*.php lanes/libsqlite/tests/SQLiteUpdateDeleteLimitDynamicExpressionTest.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteNoDomainSpecificApiTest.php
git diff --check -- lanes/libsqlite
```

Results:

```text
16 test files, 25967 assertions, 0 failures
1 test files, 8 assertions, 0 failures
git diff --check: clean
```

Changed PHP lint:

```sh
php -l lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
php -l lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitExistsDynamicTest.php
```

Results:

```text
No syntax errors detected in lanes/libsqlite/src/SQLiteUpdateDeleteReturningSql.php
No syntax errors detected in lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitExistsDynamicTest.php
```

PASS-line delta:

- Added 85 focused TestRunner PASS cases.
- `lane-status.json` `phpPass`: `6270884 -> 6270969`.
- Mapped coverage unchanged at `1589 / 1589`.

## Dependency Closure

No new support component is needed. This slice reuses the existing bounded dynamic `LIMIT` expression evaluator, top-level keyword scanner, SQLite truthiness helper, and row-value UPDATE/DELETE execution harness.

## Non-Overlap

This does not repeat accepted dynamic LIMIT slices for bind parameters, cast affinity, collations, current date/time, DISTINCT, JSON mutation, LIKE/GLOB, random, timediff, unistr, aggregate tuples, or BETWEEN precedence. It is limited to no-FROM `EXISTS`/`NOT EXISTS` subquery truthiness in dynamic row-value UPDATE/DELETE LIMIT/OFFSET expressions.
