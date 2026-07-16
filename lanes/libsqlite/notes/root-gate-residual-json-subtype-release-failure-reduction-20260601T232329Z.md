# Root-Gate Residual JSON Subtype Failure Reduction

Base accepted HEAD: `e92015d5f1d2545bb6a0e1bbacb4f4ca2f995a63`

Micro-slice: `root-gate-residual-release-failure-reduction-20260601T232329Z`

## Source Truth

- Hydrated upstream SQLite: `/home/claude/port-libs/.upstream-cache/libsqlite/test/json101.test`
- Relevant upstream sections: `json101-5.10` and `json101-5.11`, covering `json_tree()`/`json_each()` value subtype behavior when container values flow into `json_insert()` and `json_quote()`.

## Red-First Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Result before the fix:

```text
1 test files, 9489 assertions, 16 failures
```

JSON residual failures removed by this slice:

- JSON aggregate object null-label validation expected an exception.
- JSON aggregate dispatch expected empty object SQL results to decode as arrays in the public JSONB decode contract.
- `json_each()` direct table rows expected SQL text for object/array `value` columns.
- JSON table hidden-constraint planner rows expected SQL text for object/array `value` columns.
- JSONB patch direct decode expected empty object results as arrays.

## Patch Summary

- `SQLiteJsonAggregate` now lets null object labels reach the existing object-label validator instead of silently skipping them.
- `SQLiteJsonEach` and `SQLiteJsonTree` keep direct API container `value` columns as SQL JSON text, while `SQLiteSelectSql` wraps those values back into `SQLiteJsonSubtypeValue` for SELECT execution.
- `SQLiteJsonB::decode()` remains array-friendly for empty objects, while `decodeForJsonEncoding()` preserves empty-object identity for SQL JSON text output.
- JSON mutation and array-insert text result paths use `decodeForJsonEncoding()` so subtype-driven `json_insert()` preserves `{}` instead of converting it to `[]`.
- JSON table functions use `SQLiteJsonInspection::locatePathForJsonEncoding()` for JSONB sources so SQL-visible container values preserve empty-object identity.

## Verification

Commands:

```sh
php -l lanes/libsqlite/src/SQLiteJsonEach.php
php -l lanes/libsqlite/src/SQLiteJsonTree.php
php -l lanes/libsqlite/src/SQLiteJsonAggregate.php
php -l lanes/libsqlite/src/SQLiteJsonB.php
php -l lanes/libsqlite/src/SQLiteSelectSql.php
php -l lanes/libsqlite/src/SQLiteJsonMutation.php
php -l lanes/libsqlite/src/SQLiteJsonArrayInsert.php
php -l lanes/libsqlite/src/SQLiteJsonInspection.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRealUpstreamJson101ValueSubtypeSelectSqlDynamic20260601Test.php
php tools/run-tests.php lanes/libsqlite/tests/SQLiteHeaderTest.php
```

Results:

```text
SQLiteRealUpstreamJson101ValueSubtypeSelectSqlDynamic20260601Test.php: 1 test files, 36009 assertions, 0 failures
SQLiteHeaderTest.php: 1 test files, 9392 assertions, 11 failures
```

The post-fix header failures are the remaining non-JSON residual buckets: scalar guard exceptions, window diagnostics, grouped aggregate ordering, scalar subquery counts, residual WHERE/join rejection, compound boolean typing/order, SELECT query-plan scalar output, UPDATE FROM routing, and malformed UPDATE/DELETE LIMIT rejection.

## Dependency Closure

No new support component is needed. The slice reuses existing JSONB decode modes, JSON table functions, and SELECT SQL expression dispatch.

## Counter Discipline

This patch claims no `phpPass` or mapped-coverage growth. It reduces the focused residual failure count from `16` to `11`.
