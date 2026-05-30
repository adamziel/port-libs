# yield-sqlite-trigger-upsert-returning-savepoint-current-next38

## Behavior

Adds `SQLiteUpsertReturningSavepointPlan`, a bounded native PHP model for
multi-row UPSERT `RETURNING` execution under a current savepoint when triggers
and foreign-key checks can interrupt the statement.

Covered behavior:

- changed rows emit RETURNING projections for `new.*`, `old.*`, `excluded.*`,
  and callable expression columns;
- skipped `DO UPDATE ... WHERE` rows produce yield diagnostics but no RETURNING
  row;
- immediate trigger/FK failure restores parent and child rows to the current
  savepoint image, resets statement changes to zero, and suppresses committed
  RETURNING rows;
- attempted yield diagnostics retain prior row evidence for Application import
  error reporting without treating those rows as committed results;
- deferred FK violations remain visible and do not roll back the current
  savepoint before an outer constraint check.

## Verification

Focused command:

```sh
php tools/run-tests.php lanes/libsqlite/tests/SQLiteUpsertReturningSavepointCurrentNext38Test.php
```

Result:

```text
1 test files, 65 assertions, 0 failures
```

Example smoke:

```sh
php lanes/libsqlite/examples/application-upsert-returning-savepoint.php
```

## Non-Overlap

This avoids accepted standalone UPSERT trigger/FK yield behavior, recursive
savepoint UPSERT behavior, WAL byte truncation, savepoint page-image rollback,
and VFS savepoint rollback application. The new surface is the non-overlapping
`RETURNING` result contract for multi-row UPSERT under a current savepoint,
including immediate rollback suppression versus deferred FK yield evidence.

## Dependency Closure

No new support component is needed. The slice reuses lane-local row-array
UPSERT/trigger/FK semantics and does not require ext/sqlite, shell-outs, or a
shared dependency activation gate.
