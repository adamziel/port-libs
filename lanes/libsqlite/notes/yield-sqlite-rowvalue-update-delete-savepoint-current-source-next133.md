# rowvalue-update-delete-savepoint-current-source-next133

## Behavior

Adds native PHP support for SQLite row-value `IS` / `IS NOT` predicates in
`SQLiteUpdateDeleteReturningSql` for both UPDATE/DELETE `WHERE` clauses and
RETURNING expressions.

The slice covers NULL-safe row-value semantics:

- `(status, bucket) IS (NULL, NULL)` matches only rows where both elements are
  NULL.
- `(status, bucket) IS NOT ('live', 'core')` uses NULL-safe non-identity rather
  than ordinary comparison UNKNOWN behavior.
- RETURNING expressions evaluate against the row image appropriate for UPDATE
  and DELETE, then savepoint rollback restores the current source while keeping
  attempted next-source evidence.

## Focused Evidence

Command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueUpdateDeleteSavepointCurrentSourceNext133Test.php
```

Result:

```text
Focused test run: 1 selected test files (root lock skipped)
1 test files, 59 assertions, 0 failures
```

Expected dashboard movement: `phpPass` +59, from 56029 to 56088. Mapped
coverage remains unchanged at 606 / 1589; this patch does not claim a new
manifest row.

## Application Smoke

`lanes/libsqlite/examples/application-rowvalue-is-savepoint-current-source-next133.php`
models copied `wp_options` cleanup where nullable staged option metadata must
distinguish `NULL IS NULL` from ordinary row-value comparison UNKNOWN inside a
savepoint.

## Non-Overlap

This avoids accepted row-value and savepoint clusters:

- next126 row-value UPDATE/DELETE savepoint rollback.
- next128/next130 row-value RETURNING conflict handling.
- next131 row-value UPSERT savepoint handling.

The new surface is narrower: NULL-safe row-value `IS` / `IS NOT` evaluation in
the shared UPDATE/DELETE RETURNING executor.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP
UPDATE/DELETE RETURNING, row-value expression parsing, and savepoint
current-source planning.
