# full-run-parity-rowvalue-update-delete-limit-dynamic-20260531T063134Z-0

Base accepted HEAD: `7685e747971ca86ceced872addf2e1032378bd34`

## Behavior

Added a fresh generic `app_settings` row-value UPDATE/DELETE LIMIT parity
cluster for scalar subqueries that return a tuple with a `NULL` component:

- `(tenant_id, key_name) <> (SELECT tenant_id, NULL ...)`
- `(tenant_id, key_name) != (SELECT tenant_id, NULL ...)`
- `(tenant_id, key_name) IS NOT ...`
- `(tenant_id, key_name) IS DISTINCT FROM ...`
- false/unknown counterparts for `=`, `IS`, and `IS NOT DISTINCT FROM`
- dynamic outer `LIMIT`, `OFFSET`, and comma-limit windows for both UPDATE and
  DELETE RETURNING.

This extends the existing rowvalue4 dynamic parity file without touching older
domain-shaped fixtures or production APIs. The new expectations preserve
SQLite row-value comparison behavior where `<>`/`!=` can be true before a later
`NULL` component is examined when an earlier tuple component already differs.

## Upstream source

- `/home/claude/port-libs/.upstream-cache/libsqlite/test/rowvalue4.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/limit.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/update.test`
- `/home/claude/port-libs/.upstream-cache/libsqlite/test/delete.test`

## Focused evidence

Before this slice:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `1 test files, 11134 assertions, 0 failures`

After this slice:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteLimitDynamicParityTest.php`
- `1 test files, 11649 assertions, 0 failures`

Focused assertion delta: `+515`.

## Non-overlap

This slice does not repeat accepted row-value BETWEEN subquery parity, empty
scalar subquery parity, ordinal subquery windows, min/max windows, NULLS
placement windows, math-scalar LIMIT/OFFSET, cast/exponent/hex/boolean/bitwise
LIMIT expressions, or older WordPress-shaped savepoint/window fixtures.

## Dependency closure

No new support component is needed. The existing bounded
`SQLiteUpdateDeleteReturningSql` and `SQLiteUpdateDeleteLimitPlan` helpers
already execute the selected row-value scalar-subquery and dynamic LIMIT
behavior.
