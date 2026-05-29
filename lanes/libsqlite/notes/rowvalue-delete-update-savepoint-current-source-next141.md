# Row-Value DELETE/UPDATE Savepoint Current Source Next141

This slice adds a bounded current-source proof for SQLite row-value
`BETWEEN`/`NOT BETWEEN` predicates across `DELETE` then `UPDATE` statements
inside a savepoint.

The covered WordPress path is an import cleanup batch over copied
`wp_options` rows:

- a `DELETE ... RETURNING` removes the first transient row selected by a
  row-value `BETWEEN` range;
- a following `UPDATE ... RETURNING` evaluates row-value `NOT BETWEEN` against
  the statement current source, so the already deleted row is not selected;
- a final `DELETE ... RETURNING` sees rows updated earlier in the savepoint;
- a duplicate-key failure rolls the current source back to the savepoint image
  while preserving attempted next-source diagnostics.

Focused evidence:

- `php tools/run-tests.php lanes/libsqlite/tests/SQLiteRowValueDeleteUpdateSavepointCurrentSourceNextTest.php`
- `php lanes/libsqlite/examples/wordpress-rowvalue-delete-update-savepoint-between-cleanup.php`

Non-overlap:

- avoids accepted row-value IS/IS NOT next133, row-value conflict/RETURNING
  next137/next138, UPSERT row-value clusters, savepoint page-image/WAL byte
  rollback clusters, and accepted DELETE/UPDATE LIMIT/RETURNING basics by
  proving row-value `BETWEEN` and `NOT BETWEEN` current-source behavior across
  chained DELETE/UPDATE statements in one savepoint.

Dependency closure:

- no new support component is needed; this reuses the bounded native
  `SQLiteUpdateDeleteReturningSql` row-array executor and savepoint image model.
